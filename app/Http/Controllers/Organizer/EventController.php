<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventRequest;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organizer;
use App\Models\Ticket;
use App\Services\EventSeatService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EventController extends Controller
{
    public function __construct(private EventSeatService $seats)
    {
    }

    private function organizer(): Organizer
    {
        return app('currentOrganizer');
    }

    /** El evento tiene que ser de la productora activa */
    private function ensureOwned(Event $event): void
    {
        abort_unless($event->organizer_id === $this->organizer()->id, 404);
    }

    /** Con ventas no se puede cambiar el lugar ni el modo con butacas */
    private function hasSales(Event $event): bool
    {
        return $event->orders()->whereIn('status', [Order::STATUS_PAID, Order::STATUS_PENDING])->exists();
    }

    /* ---------------------------------------------------------------- */

    public function index(Request $request): View
    {
        $events = $this->organizer()->events()
            ->with('venue:id,name,city')
            ->withCount(['tickets as sold_count' => fn ($q) => $q->where('status', '!=', Ticket::STATUS_CANCELLED)])
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%' . $request->input('q') . '%'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->input('when') === 'past', fn ($q) => $q->where('starts_at', '<', now())->orderByDesc('starts_at'))
            ->when($request->input('when') !== 'past', fn ($q) => $q->where('starts_at', '>=', now()->startOfDay())->orderBy('starts_at'))
            ->paginate(15)
            ->withQueryString();

        return view('organizer.events-index', compact('events'));
    }

    public function create(): View
    {
        $event = new Event([
            'max_tickets_per_order' => min(10, (int) config('geventos.max_entradas_por_orden')),
            'allow_transfers'       => true,
        ]);

        return view('organizer.events-form', [
            'event'    => $event,
            'venues'   => $this->organizer()->venues()->orderBy('name')->get(),
            'hasSales' => false,
        ]);
    }

    public function store(EventRequest $request): RedirectResponse
    {
        $data = $request->eventData();

        if ($request->hasFile('cover')) {
            $data['cover_path'] = $this->storeCover($request->file('cover'));
        }

        $data['status'] = Event::STATUS_DRAFT;

        $event = $this->organizer()->events()->create($data);

        return redirect()->route('organizer.events.show', $event)
            ->with('success', 'Evento creado como borrador. Ahora cargá los tipos de entrada para poder publicarlo.');
    }

    public function show(Event $event): View
    {
        $this->ensureOwned($event);

        $event->load(['venue.sections', 'ticketTypes.lots', 'ticketTypes.section']);

        $stats = [
            'sold'       => $event->tickets()->where('status', '!=', Ticket::STATUS_CANCELLED)->count(),
            'checked_in' => $event->tickets()->where('status', Ticket::STATUS_USED)->count(),
            'orders'     => $event->orders()->paid()->count(),
            'revenue'    => (float) $event->orders()->paid()->sum('subtotal') - (float) $event->orders()->paid()->sum('discount'),
        ];

        // Entradas emitidas por tipo y estado de butacas por tipo (eventos numerados)
        $soldByType = $event->tickets()
            ->where('status', '!=', Ticket::STATUS_CANCELLED)
            ->selectRaw('ticket_type_id, count(*) as total')
            ->groupBy('ticket_type_id')
            ->pluck('total', 'ticket_type_id');

        $seatCounts = $event->is_seated ? $this->seats->countsByType($event) : [];
        $sections = $event->venue?->sections ?? collect();

        return view('organizer.events-show', compact('event', 'stats', 'soldByType', 'seatCounts', 'sections'));
    }

    public function edit(Event $event): View
    {
        $this->ensureOwned($event);

        return view('organizer.events-form', [
            'event'    => $event,
            'venues'   => $this->organizer()->venues()->orderBy('name')->get(),
            'hasSales' => $this->hasSales($event),
        ]);
    }

    public function update(EventRequest $request, Event $event): RedirectResponse
    {
        $this->ensureOwned($event);

        $data = $request->eventData();

        if ($this->hasSales($event)) {
            unset($data['venue_id'], $data['is_seated']);
        }

        if ($request->boolean('remove_cover') && $event->cover_path) {
            Storage::disk('public')->delete($event->cover_path);
            $data['cover_path'] = null;
        }

        if ($request->hasFile('cover')) {
            if ($event->cover_path) {
                Storage::disk('public')->delete($event->cover_path);
            }
            $data['cover_path'] = $this->storeCover($request->file('cover'));
        }

        $event->update($data);

        if ($this->seats->shouldSync($event)) {
            $this->seats->sync($event);
        }

        return redirect()->route('organizer.events.show', $event)->with('success', 'Evento actualizado.');
    }

    /** Publicar, pausar, volver a borrador o cancelar */
    public function status(Request $request, Event $event): RedirectResponse
    {
        $this->ensureOwned($event);

        $action = $request->validate([
            'action' => ['required', Rule::in(['publish', 'pause', 'draft', 'cancel'])],
        ])['action'];

        $organizer = $this->organizer();

        switch ($action) {
            case 'publish':
                if (! $organizer->isActive()) {
                    return back()->with('error', 'Tu productora todavía no está aprobada para publicar eventos.');
                }
                if ($event->starts_at->isPast()) {
                    return back()->with('error', 'No se puede publicar un evento que ya empezó.');
                }
                if (! $event->lots()->exists()) {
                    return back()->with('error', 'Cargá al menos un tipo de entrada con su lote de precio antes de publicar.');
                }
                if ($event->is_seated) {
                    $hasSeatedType = $event->ticketTypes()
                        ->whereHas('section', fn ($q) => $q->where('type', 'seated'))
                        ->exists();
                    if (! $hasSeatedType) {
                        return back()->with('error', 'El evento es numerado: asociá al menos un tipo de entrada a un sector con butacas.');
                    }
                }
                $event->update([
                    'status'       => Event::STATUS_PUBLISHED,
                    'published_at' => $event->published_at ?? now(),
                ]);
                $msg = 'Evento publicado.';
                if ($event->is_seated) {
                    $this->seats->sync($event);
                    $total = $event->eventSeats()->count();
                    $msg .= " Butacas habilitadas para la venta: {$total}.";
                }
                if (! $organizer->isMpConnected()) {
                    $msg .= ' Vinculá tu cuenta de Mercado Pago para que se puedan comprar entradas.';
                }
                break;

            case 'pause':
                abort_unless($event->isPublished(), 422);
                $event->update(['status' => Event::STATUS_PAUSED]);
                $msg = 'Venta pausada. El evento sigue visible pero no se pueden comprar entradas.';
                break;

            case 'draft':
                if ($event->orders()->exists()) {
                    return back()->with('error', 'El evento ya tiene órdenes: podés pausarlo, pero no volverlo a borrador.');
                }
                $event->update(['status' => Event::STATUS_DRAFT]);
                // Sin órdenes, las butacas del evento se liberan para poder editar el mapa del lugar
                $event->eventSeats()->delete();
                $msg = 'El evento volvió a borrador.';
                break;

            case 'cancel':
                $event->update(['status' => Event::STATUS_CANCELLED]);
                $msg = $event->orders()->paid()->exists()
                    ? 'Evento cancelado. Tiene entradas vendidas: las devoluciones se gestionan desde Órdenes.'
                    : 'Evento cancelado.';
                break;
        }

        return back()->with('success', $msg);
    }

    public function destroy(Event $event): RedirectResponse
    {
        $this->ensureOwned($event);

        if ($event->orders()->exists()) {
            return back()->with('error', 'No se puede eliminar un evento con órdenes. Podés cancelarlo.');
        }

        if ($event->cover_path) {
            Storage::disk('public')->delete($event->cover_path);
        }

        $event->delete();

        return redirect()->route('organizer.events.index')->with('success', 'Evento eliminado.');
    }

    /* ---------------------------------------------------------------- */

    private function storeCover(UploadedFile $file): string
    {
        return $file->store('events/' . $this->organizer()->id, 'public');
    }
}
