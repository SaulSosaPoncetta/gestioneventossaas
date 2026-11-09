<?php

namespace App\Services;

use App\Models\Checkin;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Control de ingreso: valida QR (o código manual) y registra el ingreso.
 *
 * - Es atómico: si dos puertas escanean la misma entrada a la vez, solo una la acepta.
 * - Es idempotente por client_uuid: un escaneo sincronizado dos veces se registra una sola vez.
 * - Todos los escaneos (válidos y rechazados) quedan en la tabla checkins.
 */
class CheckinService
{
    public function __construct(private TicketQrService $qr)
    {
    }

    /**
     * @param array $meta client_uuid, gate, device, scanned_at, offline
     * @return array{result:string, message:string, ticket:?array, checked_in_at:?string}
     */
    public function scan(Event $event, string $input, User $operator, array $meta = []): array
    {
        $input = trim($input);

        // Escaneo repetido (sincronización offline reintentada): devuelve lo ya registrado
        if (! empty($meta['client_uuid'])) {
            $previous = Checkin::with('ticket.ticketType', 'ticket.eventSeat.seat.section')
                ->where('client_uuid', $meta['client_uuid'])
                ->first();

            if ($previous) {
                return $this->response($previous->result, $previous->ticket, $this->extraMessage($previous->result, $previous->ticket));
            }
        }

        [$result, $ticket, $message] = $this->evaluate($event, $input);

        if ($result === Checkin::RESULT_OK) {
            $scannedAt = $this->scannedAt($meta['scanned_at'] ?? null);

            // Solo una puerta puede pasar la entrada de "válida" a "usada"
            $updated = Ticket::whereKey($ticket->id)
                ->where('status', Ticket::STATUS_VALID)
                ->update([
                    'status'        => Ticket::STATUS_USED,
                    'checked_in_at' => $scannedAt,
                    'checked_in_by' => $operator->id,
                    'gate'          => $meta['gate'] ?? null,
                ]);

            $ticket->refresh();

            if ($updated === 0) {
                $result = Checkin::RESULT_ALREADY_USED;
                $message = $this->extraMessage($result, $ticket);
            }
        }

        Checkin::create([
            'client_uuid'  => $meta['client_uuid'] ?? null,
            'event_id'     => $event->id,
            'ticket_id'    => $ticket?->id,
            'user_id'      => $operator->id,
            'scanned_code' => mb_substr($input, 0, 255),
            'result'       => $result,
            'gate'         => $meta['gate'] ?? null,
            'device'       => isset($meta['device']) ? mb_substr($meta['device'], 0, 80) : null,
            'offline'      => (bool) ($meta['offline'] ?? false),
            'scanned_at'   => $this->scannedAt($meta['scanned_at'] ?? null),
        ]);

        return $this->response($result, $ticket, $message);
    }

    /** @return array{0:string, 1:?Ticket, 2:?string} */
    private function evaluate(Event $event, string $input): array
    {
        $version = null;

        if (str_starts_with($input, TicketQrService::PREFIX . '.')) {
            $parsed = $this->qr->parse($input);

            if ($parsed && $parsed['event_id'] !== $event->id) {
                return [Checkin::RESULT_WRONG_EVENT, null, 'El QR es de otro evento.'];
            }

            $data = $this->qr->verify($input, $event);
            if (! $data) {
                return [Checkin::RESULT_INVALID, null, 'QR adulterado o inválido.'];
            }

            $code = $data['code'];
            $version = $data['version'];
        } elseif (preg_match('/^[A-Za-z0-9]{6,16}$/', $input)) {
            // Código tipeado a mano (está impreso debajo del QR)
            $code = strtoupper($input);
        } else {
            return [Checkin::RESULT_INVALID, null, 'No es una entrada de este sistema.'];
        }

        $ticket = Ticket::with(['ticketType', 'eventSeat.seat.section'])
            ->where('event_id', $event->id)
            ->where('code', $code)
            ->first();

        if (! $ticket) {
            // ¿Existe pero es de otro evento? (código manual)
            $other = Ticket::where('code', $code)->exists();

            return $other
                ? [Checkin::RESULT_WRONG_EVENT, null, 'La entrada es de otro evento.']
                : [Checkin::RESULT_INVALID, null, 'La entrada no existe.'];
        }

        if ($version !== null && $version !== $ticket->version) {
            return [Checkin::RESULT_INVALID, $ticket, 'QR anulado: la entrada fue transferida y tiene un QR nuevo.'];
        }

        if ($ticket->status === Ticket::STATUS_CANCELLED) {
            return [Checkin::RESULT_CANCELLED, $ticket, 'La entrada está anulada.'];
        }

        if ($ticket->isUsed()) {
            return [Checkin::RESULT_ALREADY_USED, $ticket, $this->extraMessage(Checkin::RESULT_ALREADY_USED, $ticket)];
        }

        return [Checkin::RESULT_OK, $ticket, null];
    }

    private function extraMessage(string $result, ?Ticket $ticket): ?string
    {
        if ($result === Checkin::RESULT_ALREADY_USED && $ticket?->checked_in_at) {
            return 'Ya ingresó el ' . $ticket->checked_in_at->format('d/m H:i:s')
                . ($ticket->gate ? ' por ' . $ticket->gate : '') . '.';
        }

        return null;
    }

    private function response(string $result, ?Ticket $ticket, ?string $message = null): array
    {
        return [
            'result'        => $result,
            'ok'            => $result === Checkin::RESULT_OK,
            'message'       => $message ?? Checkin::MESSAGES[$result] ?? $result,
            'ticket'        => $ticket ? $this->ticketData($ticket) : null,
            'checked_in_at' => $ticket?->checked_in_at?->toIso8601String(),
        ];
    }

    /** Datos de una entrada para el escáner (también se usan en el paquete offline) */
    public function ticketData(Ticket $ticket): array
    {
        $seat = $ticket->eventSeat?->seat;

        return [
            'code'          => $ticket->code,
            'version'       => $ticket->version,
            'status'        => $ticket->status,
            'type'          => $ticket->ticketType?->name,
            'seat'          => $seat ? ($seat->section?->name . ' · Fila ' . $seat->row_label . ' · Butaca ' . $seat->number) : null,
            'name'          => $ticket->attendee_name,
            'dni'           => $ticket->attendee_dni,
            'checked_in_at' => $ticket->checked_in_at?->toIso8601String(),
            'gate'          => $ticket->gate,
        ];
    }

    /** Paquete para trabajar sin conexión (completo o solo lo cambiado desde $since) */
    public function package(Event $event, ?Carbon $since = null): array
    {
        $tickets = Ticket::with(['ticketType', 'eventSeat.seat.section'])
            ->where('event_id', $event->id)
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->orderBy('id')
            ->get();

        return [
            'server_time' => now()->toIso8601String(),
            'full'        => $since === null,
            'event'       => $this->eventData($event),
            'tickets'     => $tickets->map(fn (Ticket $t) => $this->ticketData($t))->values(),
        ];
    }

    public function eventData(Event $event): array
    {
        $event->loadMissing('venue');

        return [
            'id'            => $event->id,
            'title'         => $event->title,
            'starts_at'     => $event->starts_at->toIso8601String(),
            'doors_open_at' => $event->doors_open_at?->toIso8601String(),
            'venue'         => $event->venue?->name,
            'nominative'    => $event->is_nominative,
            'secret'        => $event->checkin_secret, // para validar la firma del QR sin conexión
        ];
    }

    public function stats(Event $event): array
    {
        $row = Ticket::where('event_id', $event->id)
            ->selectRaw("SUM(status != 'cancelled') as total, SUM(status = 'used') as used")
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'used'  => (int) ($row->used ?? 0),
        ];
    }

    private function scannedAt(?string $value): Carbon
    {
        if (! $value) {
            return now();
        }

        try {
            $date = Carbon::parse($value);
        } catch (\Throwable) {
            return now();
        }

        // No se aceptan horas futuras (reloj del celular adelantado)
        return $date->isFuture() ? now() : $date;
    }
}
