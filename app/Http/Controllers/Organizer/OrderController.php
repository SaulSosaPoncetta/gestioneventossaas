<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Jobs\RefundEventOrders;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organizer;
use App\Services\CheckoutService;
use App\Services\MercadoPagoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class OrderController extends Controller
{
    public function __construct(
        private CheckoutService $checkout,
        private MercadoPagoService $mp,
    ) {
    }

    private function organizer(): Organizer
    {
        return app('currentOrganizer');
    }

    private function ensureOwned(Order|Event $model): void
    {
        abort_unless($model->organizer_id === $this->organizer()->id, 404);
    }

    private function filtered(Request $request)
    {
        return $this->organizer()->orders()
            ->with('event:id,title,starts_at')
            ->when($request->filled('event'), fn ($q) => $q->where('event_id', $request->integer('event')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')),
                fn ($q) => $q->whereIn('status', [Order::STATUS_PAID, Order::STATUS_REFUNDED, Order::STATUS_PENDING]))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . trim($request->input('q')) . '%';
                $q->where(fn ($w) => $w->where('buyer_name', 'like', $term)
                    ->orWhere('buyer_email', 'like', $term)
                    ->orWhere('buyer_dni', 'like', $term)
                    ->orWhere('uuid', 'like', $term)
                    ->orWhere('mp_payment_id', 'like', $term));
            })
            ->latest();
    }

    public function index(Request $request): View
    {
        $orders = $this->filtered($request)->paginate(25)->withQueryString();
        $events = $this->organizer()->events()->orderByDesc('starts_at')->get(['id', 'title', 'starts_at']);

        return view('organizer.orders-index', compact('orders', 'events'));
    }

    public function show(Order $order): View
    {
        $this->ensureOwned($order);

        $order->load([
            'event.venue', 'coupon', 'payments',
            'items.ticketType', 'items.eventSeat.seat.section',
            'tickets.ticketType', 'tickets.eventSeat.seat', 'tickets.transfers',
        ]);

        return view('organizer.orders-show', compact('order'));
    }

    /** Devolución total de una compra: reintegro en Mercado Pago + anulación de entradas */
    public function refund(Request $request, Order $order): RedirectResponse
    {
        $this->ensureOwned($order);

        if (! $order->isPaid()) {
            return back()->with('error', 'Solo se pueden devolver compras pagas.');
        }

        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:300']])['reason'] ?? null;

        try {
            if ((float) $order->total > 0 && $order->mp_payment_id) {
                $this->mp->refund($order->organizer, $order->mp_payment_id);
            }
        } catch (Throwable $e) {
            Log::warning('Devolución MP: ' . $e->getMessage(), ['order' => $order->uuid]);

            return back()->with('error', 'Mercado Pago no aceptó la devolución: ' . $e->getMessage());
        }

        $this->checkout->refundOrder($order, $reason ?: 'La productora anuló tu compra y te devolvió el dinero.');

        return back()->with('success', 'Compra devuelta: las entradas quedaron anuladas y el cupo volvió a la venta.');
    }

    /** Reenvía las entradas al comprador */
    public function resend(Order $order): RedirectResponse
    {
        $this->ensureOwned($order);
        abort_unless($order->isPaid(), 404);

        $this->checkout->sendTickets($order);

        return back()->with('success', 'Entradas reenviadas a ' . $order->buyer_email . '.');
    }

    /** Devuelve todas las compras de un evento cancelado (en segundo plano) */
    public function refundEvent(Event $event): RedirectResponse
    {
        $this->ensureOwned($event);

        if ($event->status !== Event::STATUS_CANCELLED) {
            return back()->with('error', 'Primero cancelá el evento.');
        }

        $progress = Cache::get(RefundEventOrders::progressKey($event->id));
        if ($progress && $progress['running']) {
            return back()->with('info', 'Las devoluciones ya están en curso.');
        }

        RefundEventOrders::dispatch($event);

        return back()->with('success', 'Se iniciaron las devoluciones de todas las compras. Vas a ver el avance en esta pantalla.');
    }

    /** Exporta las órdenes filtradas a CSV (se abre con Excel) */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->filtered($request);
        $statuses = ['pending' => 'Pendiente', 'paid' => 'Pagada', 'refunded' => 'Devuelta', 'expired' => 'Vencida', 'cancelled' => 'Cancelada'];

        return response()->streamDownload(function () use ($query, $statuses) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM para que Excel lea los acentos
            fputcsv($out, ['Compra', 'Fecha', 'Evento', 'Estado', 'Comprador', 'Email', 'DNI', 'Teléfono', 'Entradas',
                'Subtotal', 'Descuento', 'Cargo servicio', 'Total', 'Retención plataforma', 'Neto productora', 'Pago MP'], ';');

            $query->chunk(500, function ($orders) use ($out, $statuses) {
                foreach ($orders as $o) {
                    fputcsv($out, [
                        strtoupper(substr($o->uuid, 0, 8)),
                        ($o->paid_at ?? $o->created_at)->format('d/m/Y H:i'),
                        $o->event->title,
                        $statuses[$o->status] ?? $o->status,
                        $o->buyer_name,
                        $o->buyer_email,
                        $o->buyer_dni,
                        $o->buyer_phone,
                        $o->quantity,
                        $this->num($o->subtotal),
                        $this->num($o->discount),
                        $this->num($o->service_fee),
                        $this->num($o->total),
                        $this->num($o->platform_fee),
                        $this->num($o->organizerAmount()),
                        $o->mp_payment_id,
                    ], ';');
                }
            });

            fclose($out);
        }, 'ordenes-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function num($value): string
    {
        return number_format((float) $value, 2, ',', '');
    }
}
