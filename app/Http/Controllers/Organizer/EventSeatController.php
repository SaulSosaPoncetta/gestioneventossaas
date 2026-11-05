<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventSeat;
use App\Services\EventSeatService;
use App\Services\SeatMapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Butacas de un evento numerado: ver el estado de la sala y bloquear/liberar
 * butacas (invitaciones, prensa, técnica, butacas con visión reducida...).
 */
class EventSeatController extends Controller
{
    public function __construct(
        private SeatMapService $map,
        private EventSeatService $seats,
    ) {
    }

    private function ensureOwned(Event $event): void
    {
        abort_unless($event->organizer_id === app('currentOrganizer')->id, 404);
        abort_unless($event->is_seated && $event->venue, 404);
    }

    public function show(Event $event): View
    {
        $this->ensureOwned($event);

        $mapData = $this->map->mapData($event->venue);

        $states = EventSeat::where('event_id', $event->id)->pluck('status', 'seat_id');

        // Se agrega el estado de cada butaca en el evento ("st"); las que no participan quedan inactivas
        $mapData['sections'] = collect($mapData['sections'])->map(function ($section) use ($states) {
            $section['seats'] = collect($section['seats'])->map(function ($seat) use ($states) {
                $seat['st'] = $states[$seat['i']] ?? null;
                if ($seat['st'] === null) {
                    $seat['a'] = false;
                }

                return $seat;
            })->values();

            return $section;
        })->values()->all();

        $counts = $states->countBy()->all();
        $synced = $states->isNotEmpty();

        return view('organizer.event-seats', compact('event', 'mapData', 'counts', 'synced'));
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $this->ensureOwned($event);

        $data = $request->validate([
            'action'     => ['required', Rule::in(['block', 'release', 'sync'])],
            'seat_ids'   => ['required_unless:action,sync', 'array'],
            'seat_ids.*' => ['integer'],
        ], [
            'seat_ids.required_unless' => 'Seleccioná al menos una butaca en el mapa.',
        ]);

        if ($data['action'] === 'sync') {
            $r = $this->seats->sync($event);

            return back()->with('success', "Butacas sincronizadas: {$r['created']} nuevas, {$r['updated']} actualizadas, {$r['removed']} quitadas.");
        }

        // Solo se tocan butacas libres o bloqueadas: nunca vendidas ni retenidas por una compra en curso
        [$from, $to] = $data['action'] === 'block'
            ? [EventSeat::STATUS_AVAILABLE, EventSeat::STATUS_BLOCKED]
            : [EventSeat::STATUS_BLOCKED, EventSeat::STATUS_AVAILABLE];

        $affected = EventSeat::where('event_id', $event->id)
            ->whereIn('seat_id', $data['seat_ids'])
            ->where('status', $from)
            ->update(['status' => $to]);

        $verb = $data['action'] === 'block' ? 'bloquearon' : 'liberaron';

        return back()->with('success', "Se {$verb} {$affected} butacas.");
    }
}
