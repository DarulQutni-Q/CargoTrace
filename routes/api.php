<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CarrierController;
use App\Http\Controllers\Api\V1\ExceptionController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\ShipmentApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CargoTrace REST API v1 Routes (/api/v1/*)
|--------------------------------------------------------------------------
|
| JSON envelope: { "status": "ok"|"error", "data": ..., "message": ... }
| 200/201 success · 401 unauthenticated · 403 forbidden · 404 not found ·
| 422 validation. All endpoints except /health, /register and /login
| require a Sanctum Bearer token. Customers only ever see their own
| shipments (policy + query scoping, enforced server-side).
|
*/

Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => response()->json([
        'status' => 'ok',
        'service' => 'CargoTrace Laravel 12 API',
        'version' => '1.0.0',
    ]));

    // Public authentication
    Route::post('/register', [AuthController::class, 'register'])->name('api.v1.register');
    Route::post('/login', [AuthController::class, 'login'])->name('api.v1.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('api.v1.logout');
        Route::get('/user', [AuthController::class, 'user'])->name('api.v1.user');

        // Shipments
        Route::get('/shipments', [ShipmentApiController::class, 'index'])->name('api.v1.shipments.index');
        Route::get('/shipments/map', [ShipmentApiController::class, 'map'])->name('api.v1.shipments.map');
        Route::get('/shipments/{shipment}', [ShipmentApiController::class, 'show'])
            ->whereNumber('shipment')
            ->name('api.v1.shipments.show');
        Route::post('/shipments', [ShipmentApiController::class, 'store'])->name('api.v1.shipments.store');
        Route::match(['put', 'patch'], '/shipments/{shipment}', [ShipmentApiController::class, 'update'])
            ->whereNumber('shipment')
            ->name('api.v1.shipments.update');
        Route::delete('/shipments/{shipment}', [ShipmentApiController::class, 'destroy'])
            ->whereNumber('shipment')
            ->name('api.v1.shipments.destroy');

        // Tracking timeline (append-only history)
        Route::get('/shipments/{shipment}/tracking', [ShipmentApiController::class, 'tracking'])
            ->whereNumber('shipment')
            ->name('api.v1.shipments.tracking.index');
        Route::post('/shipments/{shipment}/tracking-events', [ShipmentApiController::class, 'storeTrackingEvent'])
            ->whereNumber('shipment')
            ->name('api.v1.shipments.tracking-events.store');

        // Exceptions
        Route::get('/shipments/{shipment}/exceptions', [ExceptionController::class, 'index'])
            ->whereNumber('shipment')
            ->name('api.v1.shipments.exceptions.index');
        Route::post('/shipments/{shipment}/exceptions', [ShipmentApiController::class, 'storeException'])
            ->whereNumber('shipment')
            ->name('api.v1.shipments.exceptions.store');
        Route::patch('/exceptions/{exception}', [ExceptionController::class, 'update'])
            ->whereNumber('exception')
            ->name('api.v1.exceptions.update');

        // Carriers
        Route::get('/carriers', [CarrierController::class, 'index'])->name('api.v1.carriers.index');
        Route::get('/carriers/{carrier}', [CarrierController::class, 'show'])
            ->whereNumber('carrier')
            ->name('api.v1.carriers.show');
        Route::post('/carriers', [CarrierController::class, 'store'])
            ->middleware('role:ADMIN,LOGISTICS_OPERATOR')
            ->name('api.v1.carriers.store');

        // Locations
        Route::get('/locations', [LocationController::class, 'index'])->name('api.v1.locations.index');
        Route::get('/locations/{location}', [LocationController::class, 'show'])
            ->whereNumber('location')
            ->name('api.v1.locations.show');
        Route::post('/locations', [LocationController::class, 'store'])
            ->middleware('role:ADMIN,LOGISTICS_OPERATOR')
            ->name('api.v1.locations.store');

        // Location catalog (presets + 38 Indonesian provinces)
        Route::get('/locations/catalog', [ShipmentApiController::class, 'locationsCatalog'])->name('api.v1.locations.catalog');
    });
});
