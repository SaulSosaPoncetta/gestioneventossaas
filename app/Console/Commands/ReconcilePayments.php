<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\CheckoutService;
use App\Services\MercadoPagoService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Red de seguridad por si un webhook no llega: busca en Mercado Pago los pagos
 * de las órdenes recientes que siguen sin confirmar.
 */
class ReconcilePayments extends Command
{
    protected $signature = 'geventos:conciliar-pagos {--horas=3 : Antigüedad máxima de las órdenes a revisar}';

    protected $description = 'Consulta en Mercado Pago los pagos de órdenes recientes no confirmadas';

    public function handle(MercadoPagoService $mp, CheckoutService $checkout): int
    {
        $orders = Order::with('organizer')
            ->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_EXPIRED, Order::STATUS_CANCELLED])
            ->whereNotNull('mp_preference_id')
            ->where('created_at', '>=', now()->subHours((int) $this->option('horas')))
            ->orderBy('id')
            ->get();

        $confirmed = 0;

        foreach ($orders as $order) {
            try {
                foreach ($mp->searchPayments($order->organizer, $order->uuid) as $payment) {
                    if (in_array($payment['status'] ?? null, ['approved', 'refunded', 'charged_back'], true)) {
                        $checkout->applyPayment($order, $payment);
                    }
                }

                if ($order->refresh()->isPaid()) {
                    $confirmed++;
                }
            } catch (Throwable $e) {
                $this->warn("Orden {$order->uuid}: {$e->getMessage()}");
            }
        }

        if ($confirmed > 0) {
            $this->info("Órdenes confirmadas por conciliación: {$confirmed}");
        }

        return self::SUCCESS;
    }
}
