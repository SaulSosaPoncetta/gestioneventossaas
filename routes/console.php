<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Tareas programadas
|--------------------------------------------------------------------------
| En el servidor: cron "* * * * * cd /var/www/geventos && php artisan schedule:run"
| En local:       php artisan schedule:work
*/

// Libera cupo y butacas de compras que no se pagaron dentro del tiempo de reserva
Schedule::command('geventos:liberar-reservas')->everyMinute()->withoutOverlapping();

// Busca en Mercado Pago pagos de órdenes recientes que no se confirmaron (por si falló un webhook)
Schedule::command('geventos:conciliar-pagos')->everyFiveMinutes()->withoutOverlapping();
