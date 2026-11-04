<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CarrierController;
use App\Http\Controllers\Api\ExceptionController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\ShipmentController;
use Illuminate\Support\Facades\Route;

// API route names are prefixed with api. so they never shadow the
// web routes of the same resource.

// Public authentication surface [S12].
Route::name('api.')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/user', [AuthController::class, 'user'])->name('user');

        Route::apiResource('shipments', ShipmentController::class);
        Route::get('shipments/{shipment}/tracking', [ShipmentController::class, 'trackingIndex'])->name('shipments.tracking.index');
        Route::post('shipments/{shipment}/tracking', [ShipmentController::class, 'trackingStore'])->name('shipments.tracking.store');
        Route::get('shipments/{shipment}/exceptions', [ShipmentController::class, 'exceptionsIndex'])->name('shipments.exceptions.index');
        Route::post('shipments/{shipment}/exceptions', [ShipmentController::class, 'exceptionsStore'])->name('shipments.exceptions.store');

        Route::patch('exceptions/{exception}', [ExceptionController::class, 'update'])->name('exceptions.update');

        Route::get('carriers', [CarrierController::class, 'index'])->name('carriers.index');
        Route::post('carriers', [CarrierController::class, 'store'])->name('carriers.store');
        Route::get('locations', [LocationController::class, 'index'])->name('locations.index');
        Route::post('locations', [LocationController::class, 'store'])->name('locations.store');
    });
});
