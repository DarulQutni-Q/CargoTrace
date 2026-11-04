<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CarrierController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ShipmentController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisterController::class, 'create'])->name('register');
    Route::post('register', [RegisterController::class, 'store'])->name('register.store');

    Route::get('login', [AuthController::class, 'create'])->name('login');
    Route::post('login', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'destroy'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');

    Route::resource('shipments', ShipmentController::class);
    Route::post('shipments/{shipment}/tracking', [ShipmentController::class, 'storeTracking'])
        ->name('shipments.tracking.store');
    Route::post('shipments/{shipment}/exceptions', [ShipmentController::class, 'storeException'])
        ->name('shipments.exceptions.store');
    Route::patch('exceptions/{exception}', [ShipmentController::class, 'updateException'])
        ->name('exceptions.update');

    Route::middleware('role:ADMIN')->group(function () {
        Route::resource('carriers', CarrierController::class)->except(['show']);
        Route::resource('locations', LocationController::class)->except(['show']);
        Route::get('geocode', [LocationController::class, 'geocode'])->name('locations.geocode');
    });
});
