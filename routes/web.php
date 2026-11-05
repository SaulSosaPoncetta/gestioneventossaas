<?php

use App\Http\Controllers\Admin\OrganizerController as AdminOrganizerController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OrganizerRegisterController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Organizer\CouponController;
use App\Http\Controllers\Organizer\DashboardController;
use App\Http\Controllers\Organizer\EventController;
use App\Http\Controllers\Organizer\EventSeatController;
use App\Http\Controllers\Organizer\MercadoPagoController;
use App\Http\Controllers\Organizer\SeatMapController;
use App\Http\Controllers\Organizer\TicketLotController;
use App\Http\Controllers\Organizer\TicketTypeController;
use App\Http\Controllers\Organizer\VenueController;
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
});
