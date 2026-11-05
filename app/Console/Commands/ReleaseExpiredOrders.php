<?php

namespace App\Console\Commands;

use App\Services\CheckoutService;
use Illuminate\Console\Command;

class ReleaseExpiredOrders extends Command
{
    protected $signature = 'geventos:liberar-reservas';

    protected $description = 'Libera el cupo, las butacas y los cupones de las compras que no se pagaron a tiempo';

    public function handle(CheckoutService $checkout): int
    {
        $count = $checkout->releaseExpired();

        if ($count > 0) {
            $this->info("Reservas liberadas: {$count}");
        }

        return self::SUCCESS;
    }
}
