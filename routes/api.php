<?php

use App\Http\Controllers\Api\V1\ShipmentApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CargoTrace REST API v1 Routes (/api/v1/*)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => response()->json([
        'status' => 'ok',
        'service' => 'CargoTrace Laravel 11 API',
        'version' => '1.0.0',
    ]));

    Route::get('/shipments', [ShipmentApiController::class, 'index'])->name('api.v1.shipments.index');
    Route::get('/shipments/map', [ShipmentApiController::class, 'map'])->name('api.v1.shipments.map');
    Route::get('/shipments/{shipment}', [ShipmentApiController::class, 'show'])
        ->whereNumber('shipment')
        ->name('api.v1.shipments.show');

    Route::post('/shipments', [ShipmentApiController::class, 'store'])->name('api.v1.shipments.store');
    Route::post('/shipments/{shipment}/tracking-events', [ShipmentApiController::class, 'storeTrackingEvent'])
        ->whereNumber('shipment')
        ->name('api.v1.shipments.tracking-events.store');
    Route::post('/shipments/{shipment}/exceptions', [ShipmentApiController::class, 'storeException'])
        ->whereNumber('shipment')
        ->name('api.v1.shipments.exceptions.store');

    Route::get('/locations/catalog', [ShipmentApiController::class, 'locationsCatalog'])->name('api.v1.locations.catalog');
});
