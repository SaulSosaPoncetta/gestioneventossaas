<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Requests\TicketTypeRequest;
use App\Models\Event;
use App\Models\EventSeat;
use App\Models\OrderItem;
use App\Models\TicketType;
use App\Services\EventSeatService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class TicketTypeController extends Controller
{
    public function __construct(private EventSeatService $seats)
    {
    }

    private function ensureOwned(Event $event, ?TicketType $type = null): void
    {
        abort_unless($event->organizer_id === app('currentOrganizer')->id, 404);

        if ($type) {
            abort_unless($type->event_id === $event->id, 404);
        }
    }

    public function store(TicketTypeRequest $request, Event $event): RedirectResponse
    {
        $this->ensureOwned($event);

        $data = $request->validated();

        DB::transaction(function () use ($event, $data) {
            $type = $event->ticketTypes()->create([
                'name'             => $data['name'],
                'description'      => $data['description'] ?? null,
                'venue_section_id' => $data['venue_section_id'] ?? null,
                'max_per_order'    => $data['max_per_order'] ?? null,
                'is_visible'       => $data['is_visible'],
                'sort'             => $data['sort'] ?? $event->ticketTypes()->count(),
            ]);

            $type->load('section');

            $type->lots()->create([
                'name'     => $data['lot_name'],
                'price'    => $data['lot_price'],
                'quantity' => $type->isSeated() ? null : $data['lot_quantity'],
                'status'   => 'active',
                'sort'     => 0,
            ]);
        });

        $this->syncSeats($event);

        return back()->with('success', "Tipo de entrada «{$data['name']}» creado.");
    }

    public function update(TicketTypeRequest $request, Event $event, TicketType $type): RedirectResponse
    {
        $this->ensureOwned($event, $type);

        $data = $request->validated();

        // Con ventas no se cambia el sector (las entradas emitidas quedarían inconsistentes)
        $hasSales = $type->tickets()->exists() || $type->lots()->where('reserved', '>', 0)->exists();
        if ($hasSales && (int) ($data['venue_section_id'] ?? 0) !== (int) $type->venue_section_id) {
            return back()->with('error', 'No se puede cambiar el sector de un tipo de entrada que ya tiene ventas.');
        }

        $type->update([
            'name'             => $data['name'],
            'description'      => $data['description'] ?? null,
            'venue_section_id' => $data['venue_section_id'] ?? null,
            'max_per_order'    => $data['max_per_order'] ?? null,
            'is_visible'       => $data['is_visible'],
            'sort'             => $data['sort'] ?? $type->sort,
        ]);

        // Si el tipo pasó a un sector numerado, sus lotes dejan de tener cupo propio
        if ($type->fresh('section')->isSeated()) {
            $type->lots()->update(['quantity' => null]);
        }

        $this->syncSeats($event);

        return back()->with('success', "Tipo de entrada «{$type->name}» actualizado.");
    }

    public function destroy(Event $event, TicketType $type): RedirectResponse
    {
        $this->ensureOwned($event, $type);

        $used = $type->tickets()->exists()
            || OrderItem::where('ticket_type_id', $type->id)->exists();

        if ($used) {
            return back()->with('error', 'No se puede eliminar: el tipo de entrada ya tiene órdenes. Podés ocultarlo o pausar sus lotes.');
        }

        $name = $type->name;

        DB::transaction(function () use ($type) {
            EventSeat::where('ticket_type_id', $type->id)->delete();
            $type->delete();
        });

        $this->syncSeats($event);

        return back()->with('success', "Tipo de entrada «{$name}» eliminado.");
    }

    private function syncSeats(Event $event): void
    {
        if ($this->seats->shouldSync($event)) {
            $this->seats->sync($event);
        }
    }
}
