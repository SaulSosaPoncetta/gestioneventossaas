<?php

namespace App\Services;

use App\Mail\TicketTransferredMail;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketTransfer;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Datos del asistente (entradas nominativas) y transferencias de titular.
 *
 * - Primera carga de nombre y DNI: libre.
 * - Corregir el nombre manteniendo el DNI: libre.
 * - Cambiar el DNI de una entrada ya asignada, o transferirla: cuenta como
 *   transferencia, sube la versión de la entrada y el QR anterior deja de valer.
 */
class TicketTransferService
{
    public function closingAt(Event $event): Carbon
    {
        return $event->starts_at->copy()->subHours((int) config('geventos.transferencias_cierre_horas', 2));
    }

    public function maxTransfers(): int
    {
        return (int) config('geventos.transferencias_maximas', 3);
    }

    /** Motivo por el que no se puede modificar la entrada (null = se puede) */
    public function lockReason(Ticket $ticket): ?string
    {
        $ticket->loadMissing('event');
        $event = $ticket->event;

        return match (true) {
            $ticket->isUsed()                                   => 'La entrada ya se usó para ingresar.',
            $ticket->status === Ticket::STATUS_CANCELLED        => 'La entrada está anulada.',
            in_array($event->status, [Event::STATUS_CANCELLED, Event::STATUS_FINISHED], true) => 'El evento ya no admite cambios.',
            $this->closingAt($event)->isPast()                  => 'Los cambios de asistente cerraron ' . config('geventos.transferencias_cierre_horas', 2) . ' horas antes del evento.',
            default                                             => null,
        };
    }

    /** Motivo por el que no se puede transferir (null = se puede) */
    public function transferLockReason(Ticket $ticket): ?string
    {
        if ($reason = $this->lockReason($ticket)) {
            return $reason;
        }
        if (! $ticket->event->allow_transfers) {
            return 'El organizador no permite transferir las entradas de este evento.';
        }
        if ($ticket->transfersCount() >= $this->maxTransfers()) {
            return 'La entrada ya alcanzó el máximo de ' . $this->maxTransfers() . ' transferencias.';
        }

        return null;
    }

    /**
     * Carga o corrige los datos del asistente.
     * Si cambia el DNI de una entrada ya asignada se registra como transferencia.
     *
     * @return string assigned | updated | transferred
     */
    public function assign(Ticket $ticket, string $name, ?string $dni, ?string $email): string
    {
        $this->guard($this->lockReason($ticket));

        $dni = $this->cleanDni($dni);
        $email = $email ? strtolower(trim($email)) : null;

        if ($ticket->event->is_nominative && ! $dni) {
            $this->fail('El DNI del asistente es obligatorio.');
        }

        // Cambio de titular: va por transferencia. Si el email es de otra persona se le envía
        // la entrada; si no hay email, el QR nuevo queda en manos del comprador.
        if ($ticket->attendee_dni && $dni && $dni !== $ticket->attendee_dni) {
            $ticket->loadMissing('order');
            $to = $email ?: $ticket->order->buyer_email;
            $send = strcasecmp($to, $ticket->order->buyer_email) !== 0;

            $this->transfer($ticket, $name, $dni, $to, send: $send);

            return 'transferred';
        }

        $this->ensureUniqueDni($ticket, $dni);

        $first = empty($ticket->attendee_dni) && empty($ticket->attendee_name);

        $ticket->update([
            'attendee_name'  => trim($name),
            'attendee_dni'   => $dni,
            'attendee_email' => $email ?? $ticket->attendee_email,
        ]);

        return $first ? 'assigned' : 'updated';
    }

    /** Transfiere la entrada a otra persona: nuevo QR y, si $send, se la envía por mail */
    public function transfer(Ticket $ticket, string $name, ?string $dni, ?string $email, bool $send = true): Ticket
    {
        $this->guard($this->transferLockReason($ticket));

        $dni = $this->cleanDni($dni);
        $email = $email ? strtolower(trim($email)) : null;

        if ($ticket->event->is_nominative && ! $dni) {
            $this->fail('El DNI del nuevo titular es obligatorio.');
        }
        if ($send && ! $email) {
            $this->fail('Indicá el email del nuevo titular para enviarle la entrada.');
        }

        $this->ensureUniqueDni($ticket, $dni);

        DB::transaction(function () use ($ticket, $name, $dni, $email) {
            $locked = Ticket::whereKey($ticket->id)->lockForUpdate()->first();

            if (! $locked->isValid()) {
                $this->fail('La entrada ya no se puede transferir.');
            }

            TicketTransfer::create([
                'ticket_id'    => $locked->id,
                'from_user_id' => $locked->holder_user_id,
                'to_user_id'   => $email ? User::where('email', $email)->value('id') : null,
                'from_name'    => $locked->attendee_name,
                'from_dni'     => $locked->attendee_dni,
                'to_name'      => trim($name),
                'to_dni'       => (string) $dni,
                'to_email'     => (string) $email,
            ]);

            $locked->update([
                'attendee_name'  => trim($name),
                'attendee_dni'   => $dni,
                'attendee_email' => $email,
                'holder_user_id' => $email ? User::where('email', $email)->value('id') : null,
                'version'        => $locked->version + 1, // invalida el QR anterior
            ]);
        });

        $ticket->refresh();

        if ($send) {
            try {
                Mail::to($email, $ticket->attendee_name)->queue(new TicketTransferredMail($ticket));
            } catch (Throwable $e) {
                Log::error('Mail de transferencia: ' . $e->getMessage(), ['ticket' => $ticket->code]);
            }
        }

        return $ticket;
    }

    /* ---------------------------------------------------------------- */

    /** En eventos nominativos un mismo DNI no puede tener dos entradas del evento */
    private function ensureUniqueDni(Ticket $ticket, ?string $dni): void
    {
        if (! $dni || ! $ticket->event->is_nominative) {
            return;
        }

        $taken = Ticket::where('event_id', $ticket->event_id)
            ->where('attendee_dni', $dni)
            ->where('status', '!=', Ticket::STATUS_CANCELLED)
            ->whereKeyNot($ticket->id)
            ->exists();

        if ($taken) {
            $this->fail("Ya hay otra entrada para este evento a nombre del DNI {$dni}.");
        }
    }

    private function cleanDni(?string $dni): ?string
    {
        $dni = preg_replace('/\D/', '', (string) $dni);

        return $dni !== '' ? $dni : null;
    }

    private function guard(?string $reason): void
    {
        if ($reason) {
            $this->fail($reason);
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['ticket' => $message]);
    }
}
