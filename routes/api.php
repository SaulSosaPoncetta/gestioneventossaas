<?php

use App\Http\Controllers\Api\ScannerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API (prefijo /api)
|--------------------------------------------------------------------------
| Escáner de puerta: PWA (/escaner) y app Android.
*/

Route::prefix('scanner')->group(function () {
    Route::post('/login', [ScannerController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'throttle:600,1'])->group(function () {
        Route::post('/logout', [ScannerController::class, 'logout']);
        Route::get('/events', [ScannerController::class, 'events']);
        Route::get('/events/{event}/package', [ScannerController::class, 'package']);
        Route::post('/events/{event}/scan', [ScannerController::class, 'scan']);
        Route::post('/events/{event}/sync', [ScannerController::class, 'sync']);
    });
});
