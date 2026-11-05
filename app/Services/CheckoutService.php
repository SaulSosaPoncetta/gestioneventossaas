<?php

namespace App\Services;

use App\Mail\OrderRefundedMail;
use App\Mail\TicketsMail;
use App\Models\Coupon;
use App\Models\Event;
use App\Models\EventSeat;
use App\Models\Order;
use App\Models\Payment;
use App\Models\TicketLot;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Compra de entradas: reserva de cupo y butacas, cálculo de importes,
 * confirmación del pago, emisión de entradas y devoluciones.
 *
 * Todo el stock se controla con bloqueo de filas (SELECT ... FOR UPDATE),
 * así dos personas no pueden llevarse la misma butaca ni sobrevender un lote.
 */
class CheckoutService
{
    public function __construct(
        private TicketService $tickets,
        private MercadoPagoService $mp,
    ) {
    }

    /**
     * Crea una orden pendiente y reserva el stock por config('geventos.reserva_minutos').
     *
     * @param array $buyer      buyer_name, buyer_email, buyer_dni, buyer_phone
     * @param array $quantities [ticket_type_id => cantidad] de tipos generales
     * @param array $seatIds    ids de butacas (seats.id) de tipos numerados
     */
    public function reserve(
        Event $event,
        array $buyer,
        array $quantities,
        array $seatIds,
        ?string $couponCode = null,
        ?User $user = null,
        ?string $ip = null,
    ): Order {
        $event->loadMissing('organizer');

        if (! $event->isOnSale()) {
            $this->fail('El evento no está a la venta en este momento.');
        }
        if (! $event->organizer->canSell()) {
            $this->fail('La venta online de este evento todavía no está habilitada.');
        }

        $this->releaseExpired($event->id);

        $quantities = collect($quantities)->map(fn ($q) => (int) $q)->filter(fn ($q) => $q > 0);
        $seatIds = collect($seatIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $totalQty = $quantities->sum() + $seatIds->count();

        if ($totalQty === 0) {
            $this->fail('Elegí al menos una entrada.');
        }
        if ($totalQty > $event->maxPerOrder()) {
            $this->fail("Podés comprar hasta {$event->maxPerOrder()} entradas por compra.");
        }

        return DB::transaction(function () use ($event, $buyer, $quantities, $seatIds, $couponCode, $user, $ip, $totalQty) {
            $types = TicketType::with('section')->where('event_id', $event->id)->get()->keyBy('id');

            $lots = TicketLot::whereIn('ticket_type_id', $types->keys())
                ->orderBy('sort')->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($types as $type) {
                $type->setRelation('lots', $lots->where('ticket_type_id', $type->id)->values());
            }

            if ($event->capacity) {
                $used = $lots->sum(fn (TicketLot $l) => $l->sold + $l->reserved);
                if ($used + $totalQty > $event->capacity) {
                    $left = max(0, $event->capacity - $used);
                    $this->fail($left > 0 ? "Quedan solo {$left} entradas para este evento." : 'Las entradas para este evento están agotadas.');
                }
            }

            $lines = [];
            $perType = [];

            // Tipos generales (por cupo)
            foreach ($quantities as $typeId => $qty) {
                /** @var TicketType|null $type */
                $type = $types->get($typeId);

                if (! $type || ! $type->is_visible || $type->isSeated()) {
                    $this->fail('Uno de los tipos de entrada elegidos no está disponible.');
                }

                $lot = $type->currentLot();
                if (! $lot) {
                    $this->fail("Las entradas «{$type->name}» están agotadas.");
                }
                if (! $lot->hasStock($qty)) {
                    $this->fail("Quedan {$lot->available()} entradas «{$type->name}» en el lote actual.");
                }

                $lines[] = ['type' => $type, 'lot' => $lot, 'qty' => $qty, 'seat' => null];
                $perType[$type->id] = ($perType[$type->id] ?? 0) + $qty;
            }

            // Butacas numeradas
            if ($seatIds->isNotEmpty()) {
                $eventSeats = EventSeat::where('event_id', $event->id)
                    ->whereIn('seat_id', $seatIds)
                    ->lockForUpdate()
                    ->get();

                $taken = $eventSeats->count() !== $seatIds->count()
                    || $eventSeats->contains(fn (EventSeat $es) => $es->status !== EventSeat::STATUS_AVAILABLE);

                if ($taken) {
                    $this->fail('Alguna de las butacas elegidas acaba de ser tomada por otra persona. Elegí otras.');
                }

                foreach ($eventSeats as $es) {
                    $type = $types->get($es->ticket_type_id);

                    if (! $type || ! $type->is_visible) {
                        $this->fail('Alguna de las butacas elegidas no está a la venta.');
                    }

                    $lot = $type->currentLot();
                    if (! $lot) {
                        $this->fail("Las entradas «{$type->name}» no están a la venta en este momento.");
                    }

                    $lines[] = ['type' => $type, 'lot' => $lot, 'qty' => 1, 'seat' => $es];
                    $perType[$type->id] = ($perType[$type->id] ?? 0) + 1;
                }
            }

            foreach ($perType as $typeId => $qty) {
                $type = $types[$typeId];
                if ($type->max_per_order && $qty > $type->max_per_order) {
                    $this->fail("Podés comprar hasta {$type->max_per_order} entradas «{$type->name}» por compra.");
                }
            }

            $subtotal = round(array_sum(array_map(fn ($l) => (float) $l['lot']->price * $l['qty'], $lines)), 2);

            // Cupón
            $coupon = null;
            $discount = 0.0;

            if ($couponCode !== null && trim($couponCode) !== '') {
                $coupon = Coupon::where('organizer_id', $event->organizer_id)
                    ->where('code', strtoupper(trim($couponCode)))
                    ->lockForUpdate()
                    ->first();

                if (! $coupon) {
                    $this->fail('El cupón ingresado no existe.');
                }
                if ($reason = $coupon->rejectionReason($event, $totalQty)) {
                    $this->fail($reason);
                }

                $discount = $coupon->discountFor($subtotal);
            }

            $amounts = $this->amounts($event, $subtotal, $discount);

            $order = Order::create([
                'event_id'     => $event->id,
                'organizer_id' => $event->organizer_id,
                'user_id'      => $user?->id,
                'coupon_id'    => $coupon?->id,
                'buyer_name'   => $buyer['buyer_name'],
                'buyer_email'  => strtolower($buyer['buyer_email']),
                'buyer_dni'    => $buyer['buyer_dni'] ?? null,
                'buyer_phone'  => $buyer['buyer_phone'] ?? null,
                'status'       => Order::STATUS_PENDING,
                'quantity'     => $totalQty,
                'subtotal'     => $amounts['subtotal'],
                'discount'     => $amounts['discount'],
                'service_fee'  => $amounts['service_fee'],
                'total'        => $amounts['total'],
                'platform_fee' => $amounts['platform_fee'],
                'currency'     => 'ARS',
                'expires_at'   => now()->addMinutes((int) config('geventos.reserva_minutos')),
                'ip'           => $ip,
            ]);

            foreach ($lines as $line) {
                $order->items()->create([
                    'ticket_type_id' => $line['type']->id,
                    'ticket_lot_id'  => $line['lot']->id,
                    'event_seat_id'  => $line['seat']?->id,
                    'quantity'       => $line['qty'],
                    'unit_price'     => $line['lot']->price,
                    'total'          => round((float) $line['lot']->price * $line['qty'], 2),
                ]);

                TicketLot::whereKey($line['lot']->id)->increment('reserved', $line['qty']);

                $line['seat']?->update([
                    'status'     => EventSeat::STATUS_HELD,
                    'order_id'   => $order->id,
                    'held_until' => $order->expires_at,
                ]);
            }

            $coupon?->increment('reserved_count');

            return $order;
        });
    }

    /**
     * Importes de una compra:
     * - El cargo por servicio lo paga el comprador (sobre el valor con descuento).
     * - La plataforma retiene el cargo por servicio + su comisión sobre la productora.
     */
    public function amounts(Event $event, float $subtotal, float $discount = 0): array
    {
        $event->loadMissing('organizer');

        $base = round(max(0, $subtotal - $discount), 2);
        $serviceFee = round($base * $event->effectiveServiceFee() / 100, 2);
        $total = round($base + $serviceFee, 2);
        $commission = round($base * $event->organizer->effectiveCommission() / 100, 2);

        return [
            'subtotal'     => round($subtotal, 2),
            'discount'     => round($discount, 2),
            'service_fee'  => $serviceFee,
            'total'        => $total,
            'platform_fee' => min($total, round($serviceFee + $commission, 2)),
        ];
    }

    /**
     * Pasa la reserva a venta firme, emite las entradas y las envía por mail.
     * Devuelve true si la orden quedó pagada (ahora o antes).
     */
    public function confirmPayment(Order $order): bool
    {
        $result = DB::transaction(function () use ($order) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();

            if (! $locked) {
                return 'missing';
            }
            if ($locked->isPaid()) {
                return 'already';
            }
            if (! $locked->isPending()) {
                return 'not_pending';
            }

            $lotIds = [];
            foreach ($locked->items as $item) {
                $q = (int) $item->quantity;
                TicketLot::whereKey($item->ticket_lot_id)->update([
                    'sold'     => DB::raw("sold + {$q}"),
                    'reserved' => DB::raw($this->minus('reserved', $q)),
                ]);
                $lotIds[] = $item->ticket_lot_id;
            }

            // Lotes que llegaron a su cupo: quedan agotados y la venta pasa al siguiente
            TicketLot::whereIn('id', $lotIds)
                ->whereNotNull('quantity')
                ->whereColumn('sold', '>=', 'quantity')
                ->update(['status' => TicketLot::STATUS_SOLD_OUT]);

            EventSeat::where('order_id', $locked->id)
                ->where('status', EventSeat::STATUS_HELD)
                ->update(['status' => EventSeat::STATUS_SOLD, 'held_until' => null]);

            if ($locked->coupon_id) {
                Coupon::whereKey($locked->coupon_id)->update([
                    'used_count'     => DB::raw('used_count + 1'),
                    'reserved_count' => DB::raw($this->minus('reserved_count', 1)),
                ]);
            }

            $locked->update(['status' => Order::STATUS_PAID, 'paid_at' => now()]);

            // Las entradas se emiten en la misma transacción que la venta
            $this->tickets->issue($locked);

            return 'paid';
        });

        $order->refresh();

        if ($result === 'paid') {
            $this->sendTickets($order);
        }

        return in_array($result, ['paid', 'already'], true);
    }

    /** Envía (o reenvía) las entradas por mail, en cola */
    public function sendTickets(Order $order): void
    {
        try {
            Mail::to($order->buyer_email, $order->buyer_name)->queue(new TicketsMail($order));
        } catch (Throwable $e) {
            Log::error('Mail de entradas: ' . $e->getMessage(), ['order' => $order->uuid]);
        }
    }

    /** Libera el stock de una orden pendiente (vencida o cancelada por el comprador) */
    public function releaseOrder(Order $order, string $status = Order::STATUS_EXPIRED): bool
    {
        return DB::transaction(function () use ($order, $status) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();

            if (! $locked || ! $locked->isPending()) {
                return false;
            }

            foreach ($locked->items as $item) {
                TicketLot::whereKey($item->ticket_lot_id)->update([
                    'reserved' => DB::raw($this->minus('reserved', (int) $item->quantity)),
                ]);
            }

            EventSeat::where('order_id', $locked->id)
                ->where('status', EventSeat::STATUS_HELD)
                ->update([
                    'status'     => EventSeat::STATUS_AVAILABLE,
                    'order_id'   => null,
                    'held_until' => null,
                ]);

            if ($locked->coupon_id) {
                Coupon::whereKey($locked->coupon_id)->update([
                    'reserved_count' => DB::raw($this->minus('reserved_count', 1)),
                ]);
            }

            $locked->update(['status' => $status]);
            $order->setRawAttributes($locked->getAttributes(), true);

            return true;
        });
    }

    /** Libera todas las reservas vencidas (opcionalmente de un solo evento) */
    public function releaseExpired(?int $eventId = null): int
    {
        $count = 0;

        Order::expiredPending()
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
            ->orderBy('id')
            ->get()
            ->each(function (Order $order) use (&$count) {
                if ($this->releaseOrder($order, Order::STATUS_EXPIRED)) {
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Registra un pago informado por Mercado Pago (webhook, retorno o conciliación)
     * y actúa según su estado. Es idempotente: el mismo pago puede llegar varias veces.
     */
    public function applyPayment(Order $order, array $p): Payment
    {
        if (($p['external_reference'] ?? null) !== $order->uuid) {
            throw new RuntimeException('El pago no corresponde a esta orden.');
        }

        $fees = collect($p['fee_details'] ?? []);

        $payment = Payment::updateOrCreate(
            ['mp_payment_id' => (string) $p['id']],
            [
                'order_id'        => $order->id,
                'status'          => $p['status'] ?? 'unknown',
                'status_detail'   => $p['status_detail'] ?? null,
                'amount'          => $p['transaction_amount'] ?? 0,
                'net_amount'      => $p['transaction_details']['net_received_amount'] ?? null,
                'fee_amount'      => $fees->sum('amount') ?: null,
                'marketplace_fee' => $p['marketplace_fee'] ?? ($fees->where('type', 'application_fee')->sum('amount') ?: null),
                'payment_method'  => $p['payment_method_id'] ?? null,
                'payer_email'     => $p['payer']['email'] ?? null,
                'raw'             => $p,
                'approved_at'     => ! empty($p['date_approved']) ? Carbon::parse($p['date_approved']) : null,
            ]
        );

        $order->refresh();
        $amountOk = abs((float) $payment->amount - (float) $order->total) < 0.01;

        if ($payment->isApproved() && $amountOk) {
            if ($order->isPending()) {
                $order->update(['mp_payment_id' => $payment->mp_payment_id]);
                $this->confirmPayment($order);
            } elseif (in_array($order->status, [Order::STATUS_EXPIRED, Order::STATUS_CANCELLED], true)) {
                $this->handleLatePayment($order, $payment);
            }
        } elseif (in_array($payment->status, ['refunded', 'charged_back'], true)
            && $order->isPaid()
            && $order->mp_payment_id === $payment->mp_payment_id) {
            // Devolución hecha desde Mercado Pago o contracargo: se anulan las entradas
            $this->refundOrder($order, $payment->status === 'charged_back'
                ? 'El pago tuvo un contracargo y las entradas fueron anuladas.'
                : 'El pago fue devuelto y las entradas fueron anuladas.');
        }

        return $payment;
    }

    /**
     * Pago aprobado que llegó con la reserva ya vencida o cancelada.
     * Si el mismo stock sigue libre se vuelve a tomar y se confirma la compra;
     * si no, se devuelve el dinero automáticamente.
     */
    public function handleLatePayment(Order $order, Payment $payment): void
    {
        $revived = DB::transaction(function () use ($order) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();

            if (! $locked || ! in_array($locked->status, [Order::STATUS_EXPIRED, Order::STATUS_CANCELLED], true)) {
                return false;
            }

            $items = $locked->items;

            // 1) ¿Sigue habiendo stock?
            foreach ($items as $item) {
                if ($item->event_seat_id) {
                    $seat = EventSeat::whereKey($item->event_seat_id)->lockForUpdate()->first();
                    if (! $seat || $seat->status !== EventSeat::STATUS_AVAILABLE) {
                        return false;
                    }
                } else {
                    $lot = TicketLot::whereKey($item->ticket_lot_id)->lockForUpdate()->first();
                    if (! $lot || ($lot->quantity !== null && $lot->quantity - $lot->sold - $lot->reserved < $item->quantity)) {
                        return false;
                    }
                }
            }

            // 2) Se vuelve a reservar todo y la orden pasa a pendiente para confirmarse
            foreach ($items as $item) {
                TicketLot::whereKey($item->ticket_lot_id)->increment('reserved', $item->quantity);

                if ($item->event_seat_id) {
                    EventSeat::whereKey($item->event_seat_id)->update([
                        'status'     => EventSeat::STATUS_HELD,
                        'order_id'   => $locked->id,
                        'held_until' => now()->addMinutes(5),
                    ]);
                }
            }

            if ($locked->coupon_id) {
                Coupon::whereKey($locked->coupon_id)->increment('reserved_count');
            }

            $locked->update(['status' => Order::STATUS_PENDING, 'expires_at' => now()->addMinutes(5)]);

            return true;
        });

        if ($revived) {
            $order->refresh()->update(['mp_payment_id' => $payment->mp_payment_id]);
            $this->confirmPayment($order);

            return;
        }

        // Sin stock: devolución automática
        try {
            $this->mp->refund($order->organizer, $payment->mp_payment_id);
            $payment->update(['status' => 'refunded']);
            $order->update(['status' => Order::STATUS_REFUNDED, 'mp_payment_id' => $payment->mp_payment_id]);

            Mail::to($order->buyer_email, $order->buyer_name)->queue(new OrderRefundedMail(
                $order,
                'Tu pago se acreditó después de que venció la reserva y las entradas ya no estaban disponibles. Te devolvimos el dinero.'
            ));
        } catch (Throwable $e) {
            // Queda registrado para resolverlo a mano desde el panel
            Log::critical('Pago tardío sin stock y sin devolución automática: ' . $e->getMessage(), [
                'order'   => $order->uuid,
                'payment' => $payment->mp_payment_id,
            ]);
        }
    }

    /**
     * Anula una compra pagada: cancela las entradas y devuelve el stock a la venta.
     * (El reintegro del dinero se hace en Mercado Pago o lo informa Mercado Pago.)
     */
    public function refundOrder(Order $order, ?string $reason = null, bool $notify = true): bool
    {
        $done = DB::transaction(function () use ($order) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();

            if (! $locked || ! $locked->isPaid()) {
                return false;
            }

            $lotIds = [];
            foreach ($locked->items as $item) {
                TicketLot::whereKey($item->ticket_lot_id)->update([
                    'sold' => DB::raw($this->minus('sold', (int) $item->quantity)),
                ]);
                $lotIds[] = $item->ticket_lot_id;
            }

            // Lotes agotados que vuelven a tener cupo
            TicketLot::whereIn('id', $lotIds)
                ->where('status', TicketLot::STATUS_SOLD_OUT)
                ->where(fn ($q) => $q->whereNull('quantity')->orWhereColumn('sold', '<', 'quantity'))
                ->update(['status' => TicketLot::STATUS_ACTIVE]);

            EventSeat::where('order_id', $locked->id)->update([
                'status'     => EventSeat::STATUS_AVAILABLE,
                'order_id'   => null,
                'held_until' => null,
            ]);

            if ($locked->coupon_id) {
                Coupon::whereKey($locked->coupon_id)->update([
                    'used_count' => DB::raw($this->minus('used_count', 1)),
                ]);
            }

            $this->tickets->cancelForOrder($locked);
            $locked->update(['status' => Order::STATUS_REFUNDED]);

            return true;
        });

        $order->refresh();

        if ($done && $notify) {
            try {
                Mail::to($order->buyer_email, $order->buyer_name)->queue(new OrderRefundedMail($order, $reason));
            } catch (Throwable $e) {
                Log::error('Mail de devolución: ' . $e->getMessage(), ['order' => $order->uuid]);
            }
        }

        return $done;
    }

    /* ---------------------------------------------------------------- */

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['checkout' => $message]);
    }

    /** Resta sin bajar de cero (las columnas son UNSIGNED) */
    private function minus(string $column, int $n): string
    {
        return "IF({$column} >= {$n}, {$column} - {$n}, 0)";
    }
}
