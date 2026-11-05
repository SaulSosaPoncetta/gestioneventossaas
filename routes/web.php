<?php

use App\Http\Controllers\Admin\OrganizerController as AdminOrganizerController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OrganizerRegisterController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Organizer\DashboardController;
use App\Http\Controllers\Organizer\EventController;
use App\Http\Controllers\Organizer\SeatMapController;
use App\Http\Controllers\Organizer\VenueController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sitio público
|--------------------------------------------------------------------------
*/

Route::view('/', 'home')->name('home');

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
