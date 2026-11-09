<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\Order;
use App\Services\CheckoutService;
use App\Services\MercadoPagoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Devuelve todas las compras pagas de un evento cancelado (en segundo plano).
 * El progreso queda en caché para mostrarlo en el panel.
 */
class RefundEventOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 3600;

    public function __construct(public Event $event)
    {
    }

    public static function progressKey(int $eventId): string
    {
        return 'refund-event:' . $eventId;
    }

    public function handle(CheckoutService $checkout, MercadoPagoService $mp): void
    {
        $key = self::progressKey($this->event->id);
        $orders = Order::with('organizer')
            ->where('event_id', $this->event->id)
            ->where('status', Order::STATUS_PAID)
            ->orderBy('id')
            ->get();

        $done = 0;
        $failed = 0;
        Cache::put($key, ['total' => $orders->count(), 'done' => 0, 'failed' => 0, 'running' => true], now()->addDay());

        foreach ($orders as $order) {
            try {
                if ((float) $order->total > 0 && $order->mp_payment_id) {
                    $mp->refund($order->organizer, $order->mp_payment_id);
                }
                $checkout->refundOrder($order, 'El evento fue cancelado. Te devolvimos el total de tu compra.');
                $done++;
            } catch (Throwable $e) {
                $failed++;
                Log::error('Devolución por evento cancelado: ' . $e->getMessage(), ['order' => $order->uuid]);
            }

            Cache::put($key, ['total' => $orders->count(), 'done' => $done, 'failed' => $failed, 'running' => true], now()->addDay());
        }

        Cache::put($key, ['total' => $orders->count(), 'done' => $done, 'failed' => $failed, 'running' => false], now()->addDays(7));
    }
}
