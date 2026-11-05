<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventSeat;
use App\Models\TicketType;
use App\Services\CheckoutService;
use App\Services\SeatMapService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicEventController extends Controller
{
    public function __construct(
        private CheckoutService $checkout,
        private SeatMapService $map,
    ) {
    }

    /** Cartelera */
    public function home(Request $request): View
    {
        $events = Event::published()
            ->upcoming()
            ->with(['venue:id,name,city,province', 'organizer:id,name'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->input('q') . '%';
                $q->where(fn ($w) => $w->where('title', 'like', $term)->orWhere('summary', 'like', $term));
            })
            ->when($request->filled('categoria'), fn ($q) => $q->where('category', $request->input('categoria')))
            ->orderBy('starts_at')
            ->paginate(12)
            ->withQueryString();

        return view('home', compact('events'));
    }

    /** Página del evento con la compra */
    public function show(Request $request, Event $event): View
    {
        $visible = [Event::STATUS_PUBLISHED, Event::STATUS_PAUSED, Event::STATUS_FINISHED, Event::STATUS_CANCELLED];
        $isTeam = $request->user()?->canManageOrganizer($event->organizer_id) ?? false;

        abort_unless(in_array($event->status, $visible, true) || $isTeam, 404);

        // Libera reservas vencidas para mostrar disponibilidad real
        $this->checkout->releaseExpired($event->id);

        $event->load([
            'venue',
            'organizer',
            'ticketTypes' => fn ($q) => $q->where('is_visible', true)->with(['lots', 'section']),
        ]);

        $saleMessage = $this->saleMessage($event);
        $canBuy = $saleMessage === null;

        $freeSeats = $event->is_seated
            ? EventSeat::where('event_id', $event->id)->where('status', EventSeat::STATUS_AVAILABLE)
                ->selectRaw('ticket_type_id, count(*) as total')->groupBy('ticket_type_id')->pluck('total', 'ticket_type_id')
            : collect();

        $maxOrder = $event->maxPerOrder();

        $types = $event->ticketTypes->map(function (TicketType $type) use ($freeSeats, $maxOrder) {
            $lot = $type->currentLot();
            $seated = $type->isSeated();
            $available = $seated ? (int) ($freeSeats[$type->id] ?? 0) : ($lot?->available() ?? 0);

            return [
                'id'            => $type->id,
                'name'          => $type->name,
                'description'   => $type->description,
                'seated'        => $seated,
                'section_name'  => $type->section?->name,
                'section_color' => $type->section?->color,
                'section_id'    => $type->venue_section_id,
                'price'         => $lot ? (float) $lot->price : null,
                'lot_name'      => $lot?->name,
                'available'     => $lot ? $available : 0,
                'max'           => min($maxOrder, $type->max_per_order ?: $maxOrder, max(0, $available)),
                'sold_out'      => ! $lot || $available <= 0,
            ];
        })->values();

        $mapData = ($event->is_seated && $event->venue && $types->contains('seated', true))
            ? $this->mapData($event, $types)
            : null;

        $jsConfig = [
            'fee'       => $event->effectiveServiceFee(),
            'maxOrder'  => $maxOrder,
            'couponUrl' => route('checkout.coupon', $event),
        ];

        return view('public.event', compact('event', 'types', 'mapData', 'saleMessage', 'canBuy', 'jsConfig', 'isTeam'));
    }

    /** Mapa del lugar con el estado de cada butaca y el precio de cada sector */
    private function mapData(Event $event, $types): array
    {
        $data = $this->map->mapData($event->venue);
        $states = EventSeat::where('event_id', $event->id)->pluck('status', 'seat_id');
        $bySection = $types->where('seated', true)->keyBy('section_id');

        $data['sections'] = collect($data['sections'])->map(function ($section) use ($states, $bySection) {
            $type = $bySection->get($section['id']);

            $section['type_id'] = $type['id'] ?? null;
            $section['type_name'] = $type['name'] ?? $section['name'];
            $section['price'] = $type['price'] ?? null;

            $section['seats'] = collect($section['seats'])->map(function ($seat) use ($states) {
                $seat['st'] = $states[$seat['i']] ?? null;
                if ($seat['st'] === null) {
                    $seat['a'] = false;
                }

                return $seat;
            })->values();

            return $section;
        })->values()->all();

        return $data;
    }

    /** Motivo por el que no se puede comprar (null = se puede) */
    private function saleMessage(Event $event): ?string
    {
        return match (true) {
            $event->status === Event::STATUS_CANCELLED => 'Este evento fue cancelado.',
            $event->status === Event::STATUS_FINISHED,
            $event->starts_at->isPast()                => 'Este evento ya se realizó.',
            $event->status === Event::STATUS_DRAFT     => 'Vista previa: el evento todavía no está publicado.',
            $event->status === Event::STATUS_PAUSED    => 'La venta está pausada momentáneamente.',
            $event->sales_start_at?->isFuture() === true => 'La venta comienza el ' . $event->sales_start_at->format('d/m/Y') . ' a las ' . $event->sales_start_at->format('H:i') . ' hs.',
            $event->sales_end_at?->isPast() === true   => 'La venta online finalizó.',
            ! $event->organizer->canSell()             => 'La venta online de este evento todavía no está habilitada.',
            $event->ticketTypes->isEmpty()             => 'Todavía no hay entradas a la venta.',
            default                                    => null,
        };
    }
}
