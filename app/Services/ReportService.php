<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Order;
use App\Models\Organizer;
use App\Models\Payment;
use App\Models\Ticket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reportes de ventas y liquidación.
 *
 * - Bruto: valor de las entradas (subtotal) · Descuentos: cupones
 * - Cobrado: lo que pagó el comprador (incluye cargo por servicio)
 * - Retención plataforma: cargo por servicio + comisión (marketplace_fee)
 * - Neto productora: cobrado - retención (antes de los costos propios de Mercado Pago)
 */
class ReportService
{
    /** Resumen por evento de una productora */
    public function eventsSummary(Organizer $organizer, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $rows = Order::query()
            ->where('organizer_id', $organizer->id)
            ->whereIn('status', [Order::STATUS_PAID, Order::STATUS_REFUNDED])
            ->when($from, fn ($q) => $q->where('paid_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where('paid_at', '<=', $to->copy()->endOfDay()))
            ->groupBy('event_id')
            ->selectRaw("
                event_id,
                SUM(status = 'paid') as orders,
                SUM(CASE WHEN status = 'paid' THEN quantity ELSE 0 END) as tickets,
                SUM(CASE WHEN status = 'paid' THEN subtotal ELSE 0 END) as gross,
                SUM(CASE WHEN status = 'paid' THEN discount ELSE 0 END) as discount,
                SUM(CASE WHEN status = 'paid' THEN service_fee ELSE 0 END) as service_fee,
                SUM(CASE WHEN status = 'paid' THEN total ELSE 0 END) as charged,
                SUM(CASE WHEN status = 'paid' THEN platform_fee ELSE 0 END) as platform_fee,
                SUM(status = 'refunded') as refunded_orders,
                SUM(CASE WHEN status = 'refunded' THEN total ELSE 0 END) as refunded_amount
            ")
            ->get()
            ->keyBy('event_id');

        $events = Event::with('venue:id,name')
            ->whereIn('id', $rows->keys())
            ->orderByDesc('starts_at')
            ->get();

        $checkedIn = Ticket::whereIn('event_id', $rows->keys())
            ->where('status', Ticket::STATUS_USED)
            ->selectRaw('event_id, COUNT(*) as total')
            ->groupBy('event_id')
            ->pluck('total', 'event_id');

        return $events->map(function (Event $event) use ($rows, $checkedIn) {
            $r = $rows[$event->id];

            return (object) [
                'event'           => $event,
                'orders'          => (int) $r->orders,
                'tickets'         => (int) $r->tickets,
                'checked_in'      => (int) ($checkedIn[$event->id] ?? 0),
                'gross'           => (float) $r->gross,
                'discount'        => (float) $r->discount,
                'service_fee'     => (float) $r->service_fee,
                'charged'         => (float) $r->charged,
                'platform_fee'    => (float) $r->platform_fee,
                'net'             => round((float) $r->charged - (float) $r->platform_fee, 2),
                'refunded_orders' => (int) $r->refunded_orders,
                'refunded_amount' => (float) $r->refunded_amount,
            ];
        });
    }

    /** Totales de una colección de filas de eventsSummary() */
    public function totals(Collection $rows): object
    {
        $sum = fn (string $field) => round($rows->sum($field), 2);

        return (object) [
            'orders'          => $rows->sum('orders'),
            'tickets'         => $rows->sum('tickets'),
            'checked_in'      => $rows->sum('checked_in'),
            'gross'           => $sum('gross'),
            'discount'        => $sum('discount'),
            'service_fee'     => $sum('service_fee'),
            'charged'         => $sum('charged'),
            'platform_fee'    => $sum('platform_fee'),
            'net'             => $sum('net'),
            'refunded_orders' => $rows->sum('refunded_orders'),
            'refunded_amount' => $sum('refunded_amount'),
        ];
    }

    /** Detalle de un evento: por tipo de entrada, por día, por medio de pago y por cupón */
    public function eventDetail(Event $event): array
    {
        $paid = fn ($q) => $q->where('orders.event_id', $event->id)->where('orders.status', Order::STATUS_PAID);

        $byType = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('ticket_types', 'ticket_types.id', '=', 'order_items.ticket_type_id')
            ->join('ticket_lots', 'ticket_lots.id', '=', 'order_items.ticket_lot_id')
            ->where($paid)
            ->groupBy('ticket_types.id', 'ticket_types.name', 'ticket_lots.id', 'ticket_lots.name', 'ticket_lots.price')
            ->orderBy('ticket_types.name')->orderBy('ticket_lots.id')
            ->get([
                'ticket_types.name as type',
                'ticket_lots.name as lot',
                'ticket_lots.price',
                DB::raw('SUM(order_items.quantity) as qty'),
                DB::raw('SUM(order_items.total) as amount'),
            ]);

        $byDay = DB::table('orders')
            ->where($paid)
            ->groupBy(DB::raw('DATE(paid_at)'))
            ->orderBy(DB::raw('DATE(paid_at)'))
            ->get([
                DB::raw('DATE(paid_at) as day'),
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(quantity) as tickets'),
                DB::raw('SUM(total) as charged'),
            ]);

        $byMethod = Payment::query()
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->where($paid)
            ->where('payments.status', 'approved')
            ->groupBy('payments.payment_method')
            ->get([
                DB::raw("COALESCE(payments.payment_method, 'otro') as method"),
                DB::raw('COUNT(*) as payments'),
                DB::raw('SUM(payments.amount) as amount'),
                DB::raw('SUM(payments.net_amount) as net'),
            ]);

        $byCoupon = DB::table('orders')
            ->join('coupons', 'coupons.id', '=', 'orders.coupon_id')
            ->where($paid)
            ->groupBy('coupons.id', 'coupons.code')
            ->get([
                'coupons.code',
                DB::raw('COUNT(*) as uses'),
                DB::raw('SUM(orders.discount) as discount'),
            ]);

        // Lo que Mercado Pago informa como acreditado (descontados todos sus costos)
        $mpNet = (float) Payment::query()
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->where($paid)
            ->where('payments.status', 'approved')
            ->sum('payments.net_amount');

        return compact('byType', 'byDay', 'byMethod', 'byCoupon', 'mpNet');
    }

    /** Lo que retuvo la plataforma, por productora (para el administrador) */
    public function platformSummary(?Carbon $from = null, ?Carbon $to = null): Collection
    {
        return Order::query()
            ->join('organizers', 'organizers.id', '=', 'orders.organizer_id')
            ->where('orders.status', Order::STATUS_PAID)
            ->when($from, fn ($q) => $q->where('orders.paid_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where('orders.paid_at', '<=', $to->copy()->endOfDay()))
            ->groupBy('organizers.id', 'organizers.name')
            ->orderByDesc(DB::raw('SUM(orders.platform_fee)'))
            ->get([
                'organizers.id',
                'organizers.name',
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(orders.quantity) as tickets'),
                DB::raw('SUM(orders.total) as charged'),
                DB::raw('SUM(orders.service_fee) as service_fee'),
                DB::raw('SUM(orders.platform_fee) as platform_fee'),
            ]);
    }
}
