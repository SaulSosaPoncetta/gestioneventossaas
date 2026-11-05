<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventSeat;
use App\Models\Seat;
use App\Models\TicketType;
use Illuminate\Support\Facades\DB;

/**
 * Estado de las butacas de un evento numerado (tabla event_seats).
 *
 * Cada butaca activa de un sector numerado que esté asociado a un tipo de entrada
 * del evento tiene una fila en event_seats. Se sincroniza al publicar el evento y
 * cada vez que cambian los tipos de entrada de un evento publicado.
 */
class EventSeatService
{
    /** @return array{created:int, updated:int, removed:int} */
    public function sync(Event $event): array
    {
        $result = ['created' => 0, 'updated' => 0, 'removed' => 0];

        if (! $event->is_seated || ! $event->venue_id) {
            return $result;
        }

        return DB::transaction(function () use ($event, $result) {
            // Tipo de entrada asignado a cada sector numerado del lugar
            $typeBySection = TicketType::where('event_id', $event->id)
                ->whereNotNull('venue_section_id')
                ->whereHas('section', fn ($q) => $q->where('type', 'seated')->where('venue_id', $event->venue_id))
                ->pluck('id', 'venue_section_id');

            $seats = Seat::whereIn('venue_section_id', $typeBySection->keys())
                ->where('is_active', true)
                ->get(['id', 'venue_section_id']);

            $wanted = $seats->mapWithKeys(fn (Seat $s) => [$s->id => $typeBySection[$s->venue_section_id]]);

            $existing = EventSeat::where('event_id', $event->id)
                ->lockForUpdate()
                ->get(['id', 'seat_id', 'ticket_type_id', 'status', 'order_id'])
                ->keyBy('seat_id');

            // Altas
            $now = now();
            $rows = [];
            foreach ($wanted as $seatId => $typeId) {
                if (! $existing->has($seatId)) {
                    $rows[] = [
                        'event_id'       => $event->id,
                        'seat_id'        => $seatId,
                        'ticket_type_id' => $typeId,
                        'status'         => EventSeat::STATUS_AVAILABLE,
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ];
                }
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                EventSeat::insert($chunk);
            }
            $result['created'] = count($rows);

            // Cambios de tipo y bajas (solo butacas libres o bloqueadas, nunca vendidas o retenidas)
            $free = [EventSeat::STATUS_AVAILABLE, EventSeat::STATUS_BLOCKED];

            foreach ($existing as $seatId => $es) {
                if (! in_array($es->status, $free, true) || $es->order_id) {
                    continue;
                }

                if (! $wanted->has($seatId)) {
                    $es->delete();
                    $result['removed']++;
                } elseif ((int) $es->ticket_type_id !== (int) $wanted[$seatId]) {
                    $es->update(['ticket_type_id' => $wanted[$seatId]]);
                    $result['updated']++;
                }
            }

            return $result;
        });
    }

    /** Debe sincronizarse ahora (evento numerado ya publicado o pausado) */
    public function shouldSync(Event $event): bool
    {
        return $event->is_seated
            && in_array($event->status, [Event::STATUS_PUBLISHED, Event::STATUS_PAUSED], true);
    }

    /** Cantidad de butacas por tipo y estado: [type_id => [status => n]] */
    public function countsByType(Event $event): array
    {
        return EventSeat::where('event_id', $event->id)
            ->select('ticket_type_id', 'status', DB::raw('count(*) as total'))
            ->groupBy('ticket_type_id', 'status')
            ->get()
            ->groupBy('ticket_type_id')
            ->map(fn ($rows) => $rows->pluck('total', 'status')->map(fn ($n) => (int) $n)->all())
            ->all();
    }
}
