<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Requests\TicketLotRequest;
use App\Models\Event;
use App\Models\TicketLot;
use App\Models\TicketType;
use Illuminate\Http\RedirectResponse;

class TicketLotController extends Controller
{
    private function ensureOwned(Event $event, ?TicketType $type = null, ?TicketLot $lot = null): void
    {
        abort_unless($event->organizer_id === app('currentOrganizer')->id, 404);

        if ($type) {
            abort_unless($type->event_id === $event->id, 404);
        }
        if ($lot) {
            abort_unless($lot->ticketType->event_id === $event->id, 404);
        }
    }

    public function store(TicketLotRequest $request, Event $event, TicketType $type): RedirectResponse
    {
        $this->ensureOwned($event, $type);

        $data = $request->lotData();
        $data['sort'] = $request->filled('sort') ? $data['sort'] : $type->lots()->count();

        $type->lots()->create($data);

        return back()->with('success', "Lote «{$data['name']}» agregado a {$type->name}.");
    }

    public function update(TicketLotRequest $request, Event $event, TicketLot $lot): RedirectResponse
    {
        $this->ensureOwned($event, lot: $lot);

        // El estado se elige entre activo y pausado (un lote "agotado" vuelve a activo al ampliar el cupo)
        $lot->update($request->lotData());

        return back()->with('success', "Lote «{$lot->name}» actualizado.");
    }

    public function destroy(Event $event, TicketLot $lot): RedirectResponse
    {
        $this->ensureOwned($event, lot: $lot);

        if ($lot->sold > 0 || $lot->reserved > 0 || $lot->orderItems()->exists()) {
            return back()->with('error', 'No se puede eliminar un lote con ventas. Podés pausarlo.');
        }

        if ($lot->ticketType->lots()->count() === 1) {
            return back()->with('error', 'Cada tipo de entrada necesita al menos un lote.');
        }

        $name = $lot->name;
        $lot->delete();

        return back()->with('success', "Lote «{$name}» eliminado.");
    }
}
