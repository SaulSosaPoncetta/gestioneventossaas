<?php

use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Controllers\Admin\OrganizerController as AdminOrganizerController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OrganizerRegisterController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Organizer\CheckinController;
use App\Http\Controllers\Organizer\CouponController;
use App\Http\Controllers\Organizer\DashboardController;
use App\Http\Controllers\Organizer\EventController;
use App\Http\Controllers\Organizer\EventSeatController;
use App\Http\Controllers\Organizer\MercadoPagoController;
use App\Http\Controllers\Organizer\OrderController;
use App\Http\Controllers\Organizer\ReportController;
use App\Http\Controllers\Organizer\TeamController;
use App\Http\Controllers\Organizer\SeatMapController;
use App\Http\Controllers\Organizer\TicketLotController;
use App\Http\Controllers\Organizer\TicketTypeController;
use App\Http\Controllers\Organizer\VenueController;
use App\Http\Controllers\OrderManageController;
use App\Http\Controllers\TicketPublicController;
use App\Http\Controllers\PublicEventController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sitio público
|--------------------------------------------------------------------------
*/

Route::get('/', [PublicEventController::class, 'home'])->name('home');
Route::get('/evento/{event:slug}', [PublicEventController::class, 'show'])->name('events.show');

/*
|--------------------------------------------------------------------------
| Compra de entradas
|--------------------------------------------------------------------------
*/

Route::post('/evento/{event:slug}/cupon', [CheckoutController::class, 'coupon'])->middleware('throttle:20,1')->name('checkout.coupon');
Route::post('/evento/{event:slug}/comprar', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');

Route::prefix('compra/{order}')->name('checkout.')->controller(CheckoutController::class)->group(function () {
    Route::get('/', 'show')->name('show');
    Route::post('/pagar', 'pay')->middleware('throttle:10,1')->name('pay');
    Route::get('/retorno', 'callback')->name('return');
    Route::post('/cancelar', 'cancel')->name('cancel');
    Route::get('/entradas.pdf', 'pdf')->name('pdf');
    Route::post('/reenviar', 'resend')->middleware('throttle:3,10')->name('resend');
});

// Asistentes y transferencias de una compra
Route::prefix('compra/{order}')->name('checkout.')->controller(OrderManageController::class)->group(function () {
    Route::get('/asistentes', 'edit')->name('attendees');
    Route::post('/acceso', 'requestAccess')->middleware('throttle:3,10')->name('access.request');
    Route::get('/acceso', 'access')->middleware('signed')->name('access');
    Route::patch('/entradas/{ticket:code}', 'assign')->middleware('throttle:30,1')->name('tickets.assign');
    Route::post('/entradas/{ticket:code}/transferir', 'transfer')->middleware('throttle:10,1')->name('tickets.transfer');
});

// Entrada individual para quien la recibió por transferencia (enlace firmado)
Route::get('/entrada/{ticket:code}', [TicketPublicController::class, 'show'])->middleware('signed')->name('tickets.show');
Route::get('/entrada/{ticket:code}/pdf', [TicketPublicController::class, 'pdf'])->middleware('signed')->name('tickets.pdf');

Route::get('/mis-compras', [OrderManageController::class, 'mine'])->middleware('auth')->name('orders.mine');

// Escáner de puerta (PWA). Se autentica con token contra /api/scanner
Route::view('/escaner', 'escaner')->name('scanner');

// Notificaciones de Mercado Pago (sin CSRF: ver bootstrap/app.php)
Route::post('/webhooks/mercadopago', WebhookController::class)->middleware('throttle:120,1')->name('webhooks.mercadopago');

/*
|--------------------------------------------------------------------------
| Autenticación
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/ingresar', [LoginController::class, 'create'])->name('login');
    Route::post('/ingresar', [LoginController::class, 'store'])->middleware('throttle:10,1')->name('login.store');

    Route::get('/registro', [RegisterController::class, 'create'])->name('register');
    Route::post('/registro', [RegisterController::class, 'store'])->middleware('throttle:10,1')->name('register.store');

    Route::get('/recuperar-clave', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/recuperar-clave', [PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/restablecer-clave/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/restablecer-clave', [PasswordResetController::class, 'update'])->name('password.update');
});

Route::post('/salir', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// Alta de productora (sirve para visitantes y para usuarios ya logueados)
Route::get('/productoras/registro', [OrganizerRegisterController::class, 'create'])->name('organizer.register');
Route::post('/productoras/registro', [OrganizerRegisterController::class, 'store'])->middleware('throttle:10,1')->name('organizer.register.store');

// Vuelta de la autorización OAuth de Mercado Pago (debe coincidir con la Redirect URL de la aplicación en MP)
Route::get('/productora/mercadopago/callback', [MercadoPagoController::class, 'callback'])
    ->middleware('auth')
    ->name('organizer.mercadopago.callback');

/*
|--------------------------------------------------------------------------
| Panel de productora
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'organizer'])->prefix('panel')->name('organizer.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/cambiar-productora', [DashboardController::class, 'switch'])->name('switch');

    // Tablero de ingresos (también para el rol "puerta")
    Route::get('eventos/{event}/ingresos', [CheckinController::class, 'show'])->name('events.checkins');

    // El rol "puerta" solo usa el escáner; el resto del panel es para dueño, admin y staff
    Route::middleware('organizer:owner,admin,staff')->group(function () {
        Route::resource('eventos', EventController::class)
            ->parameters(['eventos' => 'event'])
            ->names('events');
        Route::patch('eventos/{event}/estado', [EventController::class, 'status'])->name('events.status');

        // Tipos de entrada y lotes de cada evento
        Route::post('eventos/{event}/entradas', [TicketTypeController::class, 'store'])->name('events.types.store');
        Route::put('eventos/{event}/entradas/{type}', [TicketTypeController::class, 'update'])->name('events.types.update');
        Route::delete('eventos/{event}/entradas/{type}', [TicketTypeController::class, 'destroy'])->name('events.types.destroy');
        Route::post('eventos/{event}/entradas/{type}/lotes', [TicketLotController::class, 'store'])->name('events.lots.store');
        Route::put('eventos/{event}/lotes/{lot}', [TicketLotController::class, 'update'])->name('events.lots.update');
        Route::delete('eventos/{event}/lotes/{lot}', [TicketLotController::class, 'destroy'])->name('events.lots.destroy');

        // Butacas de un evento numerado (bloquear / liberar)
        Route::get('eventos/{event}/butacas', [EventSeatController::class, 'show'])->name('events.seats');
        Route::patch('eventos/{event}/butacas', [EventSeatController::class, 'update'])->name('events.seats.update');

        // Cupones de descuento
        Route::resource('cupones', CouponController::class)
            ->except('show')
            ->parameters(['cupones' => 'coupon'])
            ->names('coupons');

        Route::resource('lugares', VenueController::class)
            ->except('show')
            ->parameters(['lugares' => 'venue'])
            ->names('venues');

        // Mapa de butacas de cada lugar
        Route::prefix('lugares/{venue}/mapa')->name('venues.map.')->controller(SeatMapController::class)->group(function () {
            Route::get('/', 'show')->name('show');
            Route::post('/sectores', 'storeSection')->name('sections.store');
            Route::put('/sectores/{section}', 'updateSection')->name('sections.update');
            Route::delete('/sectores/{section}', 'destroySection')->name('sections.destroy');
            Route::post('/sectores/{section}/butacas', 'generateSeats')->name('sections.generate');
            Route::patch('/butacas', 'updateSeats')->name('seats.update');
        });

        // Órdenes
        Route::get('ordenes', [OrderController::class, 'index'])->name('orders.index');
        Route::get('ordenes/exportar', [OrderController::class, 'export'])->name('orders.export');
        Route::get('ordenes/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('ordenes/{order}/reenviar', [OrderController::class, 'resend'])->middleware('throttle:10,1')->name('orders.resend');

        // Reportes y liquidación
        Route::get('reportes', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reportes/{event}', [ReportController::class, 'show'])->name('reports.show');
        Route::get('reportes/{event}/asistentes', [ReportController::class, 'attendees'])->name('reports.attendees');
    });

    // Devoluciones y equipo (solo dueño y administradores)
    Route::middleware('organizer:owner,admin')->group(function () {
        Route::post('ordenes/{order}/devolver', [OrderController::class, 'refund'])->name('orders.refund');
        Route::post('eventos/{event}/devolver-todo', [OrderController::class, 'refundEvent'])->name('events.refund-all');

        Route::get('equipo', [TeamController::class, 'index'])->name('team.index');
        Route::post('equipo', [TeamController::class, 'store'])->name('team.store');
        Route::patch('equipo/{user}', [TeamController::class, 'update'])->name('team.update');
        Route::delete('equipo/{user}', [TeamController::class, 'destroy'])->name('team.destroy');
    });

    // Vinculación de Mercado Pago (solo dueño y administradores)
    Route::middleware('organizer:owner,admin')->prefix('mercadopago')->name('mercadopago.')
        ->controller(MercadoPagoController::class)->group(function () {
            Route::get('/', 'show')->name('show');
            Route::post('/conectar', 'connect')->name('connect');
            Route::post('/manual', 'manual')->name('manual');
            Route::delete('/', 'disconnect')->name('disconnect');
        });
});

/*
|--------------------------------------------------------------------------
| Administración de la plataforma
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'platform.admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::redirect('/', '/admin/productoras')->name('home');
    Route::get('/productoras', [AdminOrganizerController::class, 'index'])->name('organizers.index');
    Route::patch('/productoras/{organizer}', [AdminOrganizerController::class, 'update'])->name('organizers.update');
    Route::get('/recaudacion', [AdminReportController::class, 'index'])->name('reports');
});
