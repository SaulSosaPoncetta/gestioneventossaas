<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Models\Coupon;
use App\Models\Event;
use App\Models\Order;
use App\Services\CheckoutService;
use App\Services\MercadoPagoService;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CheckoutController extends Controller
{
    public function __construct(
        private CheckoutService $checkout,
        private MercadoPagoService $mp,
        private TicketService $tickets,
    ) {
    }

    /** Valida un cupón desde la página del evento (AJAX) */
    public function coupon(Request $request, Event $event): JsonResponse
    {
        $data = $request->validate([
            'code'     => ['required', 'string', 'max:40'],
            'quantity' => ['nullable', 'integer', 'min:0', 'max:50'],
        ]);

        $coupon = Coupon::where('organizer_id', $event->organizer_id)
            ->where('code', strtoupper(trim($data['code'])))
            ->first();

        if (! $coupon) {
            return response()->json(['ok' => false, 'message' => 'El cupón no existe.']);
        }

        $reason = $coupon->rejectionReason($event, max(1, (int) ($data['quantity'] ?? 1)));

        // Si solo falta sumar entradas, se acepta igual y se avisa el mínimo
        if ($reason && ! str_contains($reason, 'requiere al menos')) {
            return response()->json(['ok' => false, 'message' => $reason]);
        }

        $label = $coupon->type === Coupon::TYPE_PERCENT
            ? rtrim(rtrim(number_format((float) $coupon->value, 2, ',', ''), '0'), ',') . '% de descuento'
            : '$ ' . number_format((float) $coupon->value, 2, ',', '.') . ' de descuento';

        return response()->json([
            'ok'          => true,
            'code'        => $coupon->code,
            'type'        => $coupon->type,
            'value'       => (float) $coupon->value,
            'min_tickets' => $coupon->min_tickets,
            'message'     => "Cupón aplicado: {$label}" . ($coupon->min_tickets > 1 ? " (comprando {$coupon->min_tickets} entradas o más)" : '') . '.',
        ]);
    }

    /** Reserva las entradas y envía a pagar a Mercado Pago */
    public function store(CheckoutRequest $request, Event $event): RedirectResponse
    {
        $order = $this->checkout->reserve(
            $event,
            $request->buyer(),
            $request->input('quantities', []),
            $request->input('seats', []),
            $request->input('coupon'),
            $request->user(),
            $request->ip(),
        );

        // Entradas sin cargo: se confirman sin pasar por Mercado Pago
        if ((float) $order->total <= 0) {
            $this->checkout->confirmPayment($order);

            return redirect()->route('checkout.show', $order)->with('success', '¡Listo! Tus entradas sin cargo quedaron confirmadas.');
        }

        return $this->redirectToPayment($order);
    }

    /** Estado de la compra */
    public function show(Order $order): View
    {
        if ($order->reservationExpired()) {
            $this->checkout->releaseOrder($order, Order::STATUS_EXPIRED);
        }

        $order->load(['event.venue', 'items.ticketType', 'items.eventSeat.seat', 'payments']);

        $tickets = $order->isPaid() ? $this->tickets->printable($order) : collect();
        $qrs = $this->tickets->qrImages($tickets, 260);

        return view('public.order', compact('order', 'tickets', 'qrs'));
    }

    /** Descarga de las entradas en PDF */
    public function pdf(Order $order): Response
    {
        abort_unless($order->isPaid(), 404);

        return $this->tickets->pdf($order)->download($this->tickets->pdfFilename($order));
    }

    /** Reenvío de las entradas al email del comprador */
    public function resend(Order $order): RedirectResponse
    {
        abort_unless($order->isPaid(), 404);

        $this->checkout->sendTickets($order);

        return back()->with('success', 'Te reenviamos las entradas a ' . $order->buyer_email . '.');
    }

    /** Reintentar el pago de una orden pendiente */
    public function pay(Order $order): RedirectResponse
    {
        if (! $order->isPending() || $order->reservationExpired()) {
            return redirect()->route('checkout.show', $order)->with('error', 'Esta compra ya no se puede pagar.');
        }

        return $this->redirectToPayment($order);
    }

    /** Vuelta desde Mercado Pago (aprobado, rechazado o abandonado) */
    public function callback(Request $request, Order $order): RedirectResponse
    {
        $paymentId = $request->query('payment_id') ?? $request->query('collection_id');

        if ($paymentId && $paymentId !== 'null' && ! $order->isPaid()) {
            try {
                $payment = $this->mp->getPayment($order->organizer, (string) $paymentId);
                $this->checkout->applyPayment($order, $payment);
            } catch (Throwable $e) {
                Log::warning('Retorno MP: ' . $e->getMessage(), ['order' => $order->uuid, 'payment' => $paymentId]);
            }
        }

        $order->refresh();
        $target = redirect()->route('checkout.show', $order);

        if ($order->isPaid()) {
            return $target->with('success', '¡Pago aprobado! Tu compra está confirmada.');
        }

        $status = $request->query('status') ?? $request->query('collection_status');

        return match ($status) {
            'rejected' => $target->with('error', 'El pago fue rechazado. Podés intentar con otro medio de pago mientras dure la reserva.'),
            'null', null => $target->with('info', 'No se completó el pago. Todavía podés pagar mientras dure la reserva.'),
            default    => $target->with('info', 'Estamos esperando la confirmación del pago.'),
        };
    }

    /** El comprador abandona la compra y libera las entradas */
    public function cancel(Order $order): RedirectResponse
    {
        $this->checkout->releaseOrder($order, Order::STATUS_CANCELLED);

        return redirect()->route('events.show', $order->event)->with('info', 'Cancelaste la compra. Las entradas quedaron liberadas.');
    }

    /* ---------------------------------------------------------------- */

    private function redirectToPayment(Order $order): RedirectResponse
    {
        try {
            $pref = $this->mp->createPreference($order);
            $order->update(['mp_preference_id' => $pref['id']]);
        } catch (Throwable $e) {
            Log::error('Preferencia MP: ' . $e->getMessage(), ['order' => $order->uuid]);

            $this->checkout->releaseOrder($order, Order::STATUS_CANCELLED);

            return redirect()->route('events.show', $order->event)
                ->with('error', 'No pudimos iniciar el pago con Mercado Pago. Intentá de nuevo en unos minutos.');
        }

        return redirect()->away($pref['init_point']);
    }
}
