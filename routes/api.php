<?php

use App\Http\Controllers\Api\V1\ShipmentApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CargoTrace REST API Routes (Slide 12 Mandatory Endpoints + /api/v1/*)
|--------------------------------------------------------------------------
*/

$registerApiRoutes = function () {
    Route::get('/health', fn () => response()->json([
        'status' => 'ok',
        'service' => 'CargoTrace Laravel 11 API',
        'database' => config('database.default'),
        'version' => '1.0.0',
    ]));

    // 1. Authentication (Slide 12: POST /api/register, POST /api/login, POST /api/logout, GET /api/user)
    Route::post('/register', [ShipmentApiController::class, 'register']);
    Route::post('/login', [ShipmentApiController::class, 'login']);
    Route::post('/logout', [ShipmentApiController::class, 'logout']);
    Route::get('/user', [ShipmentApiController::class, 'currentUser']);

    // 2. Shipments (Slide 12: GET/POST /api/shipments, GET/PUT/PATCH/DELETE /api/shipments/{id}, GET/POST /api/shipments/{id}/tracking)
    Route::get('/shipments', [ShipmentApiController::class, 'index']);
    Route::post('/shipments', [ShipmentApiController::class, 'store']);
    Route::get('/shipments/map', [ShipmentApiController::class, 'map']);
    Route::get('/shipments/{shipment}', [ShipmentApiController::class, 'show'])->whereNumber('shipment');
    Route::match(['put', 'patch'], '/shipments/{shipment}', [ShipmentApiController::class, 'update'])->whereNumber('shipment');
    Route::delete('/shipments/{shipment}', [ShipmentApiController::class, 'destroy'])->whereNumber('shipment');

    Route::get('/shipments/{shipment}/tracking', [ShipmentApiController::class, 'trackingIndex'])->whereNumber('shipment');
    Route::post('/shipments/{shipment}/tracking', [ShipmentApiController::class, 'storeTrackingEvent'])->whereNumber('shipment');
    Route::post('/shipments/{shipment}/tracking-events', [ShipmentApiController::class, 'storeTrackingEvent'])->whereNumber('shipment');

    // 3. Related Resources (Slide 12: GET/POST /api/carriers, GET/POST /api/locations, GET/POST /api/shipments/{id}/exceptions, PATCH /api/exceptions/{id})
    Route::get('/carriers', [ShipmentApiController::class, 'carriersIndex']);
    Route::post('/carriers', [ShipmentApiController::class, 'carriersStore']);

    Route::get('/locations', [ShipmentApiController::class, 'locationsIndex']);
    Route::post('/locations', [ShipmentApiController::class, 'locationsStore']);
    Route::get('/locations/catalog', [ShipmentApiController::class, 'locationsCatalog']);

    Route::get('/shipments/{shipment}/exceptions', [ShipmentApiController::class, 'exceptionsIndex'])->whereNumber('shipment');
    Route::post('/shipments/{shipment}/exceptions', [ShipmentApiController::class, 'storeException'])->whereNumber('shipment');
    Route::patch('/exceptions/{exception}', [ShipmentApiController::class, 'updateException'])->whereNumber('exception');
};

// Register at both /api/* (Slide 12) and /api/v1/*
$registerApiRoutes();
Route::prefix('v1')->group($registerApiRoutes);
