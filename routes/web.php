<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\CargoTraceWebController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CargoTrace Web Routes (Laravel 12)
|--------------------------------------------------------------------------
|
| Server-rendered Blade views, 3-color palette (#080B0C, #FFFFFF, #C6F432),
| White Mode default, and mobile slide-over drawer. Every application
| route requires an authenticated session; authorization is enforced
| server-side (role middleware + policies), never only in the UI.
|
*/

// Guest: session authentication
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login.attempt');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', [CargoTraceWebController::class, 'dashboard'])->name('dashboard');
    Route::get('/shipments', [CargoTraceWebController::class, 'shipmentsIndex'])->name('shipments.index');
    Route::get('/shipments/{shipment}/focus', [CargoTraceWebController::class, 'shipmentFocus'])->name('shipments.focus');
    Route::get('/shipments/create', [CargoTraceWebController::class, 'createShipment'])->name('shipments.create');
    Route::post('/shipments', [CargoTraceWebController::class, 'storeShipment'])->name('shipments.store');
    Route::post('/shipments/claim', [CargoTraceWebController::class, 'claimShipment'])->name('shipments.claim');
    Route::get('/shipments/{shipment}/edit', [CargoTraceWebController::class, 'editShipment'])
        ->whereNumber('shipment')
        ->name('shipments.edit');
    Route::put('/shipments/{shipment}', [CargoTraceWebController::class, 'updateShipment'])
        ->whereNumber('shipment')
        ->name('shipments.update');
    Route::post('/shipments/{shipment}/archive', [CargoTraceWebController::class, 'archiveShipment'])
        ->whereNumber('shipment')
        ->name('shipments.archive');
    Route::get('/shipments/{shipment}', [CargoTraceWebController::class, 'shipmentShow'])
        ->whereNumber('shipment')
        ->name('shipments.show');
    Route::get('/shipments/{shipment}/bill-of-lading', [CargoTraceWebController::class, 'billOfLading'])
        ->whereNumber('shipment')
        ->name('shipments.bol');

    // Operator actions on a shipment (forms on the dossier page)
    Route::post('/shipments/{shipment}/advance', [CargoTraceWebController::class, 'advanceShipment'])
        ->whereNumber('shipment')
        ->name('shipments.advance');
    Route::post('/shipments/{shipment}/tracking-events', [CargoTraceWebController::class, 'storeTrackingEvent'])
        ->whereNumber('shipment')
        ->name('shipments.tracking-events.store');
    Route::post('/shipments/{shipment}/exceptions', [CargoTraceWebController::class, 'storeException'])
        ->whereNumber('shipment')
        ->name('shipments.exceptions.store');
    Route::patch('/exceptions/{exception}', [CargoTraceWebController::class, 'updateException'])
        ->whereNumber('exception')
        ->name('exceptions.update');

    Route::get('/locations', [CargoTraceWebController::class, 'locationsIndex'])->name('locations.index');
    Route::get('/locations/create', [CargoTraceWebController::class, 'createLocation'])
        ->middleware('role:ADMIN,LOGISTICS_OPERATOR')
        ->name('locations.create');
    Route::post('/locations', [CargoTraceWebController::class, 'storeLocation'])
        ->middleware('role:ADMIN,LOGISTICS_OPERATOR')
        ->name('locations.store');
    Route::post('/locations/quick-add', [CargoTraceWebController::class, 'quickAddLocation'])
        ->middleware('role:ADMIN,LOGISTICS_OPERATOR')
        ->name('locations.quick-add');
    Route::get('/locations/{location}/edit', [CargoTraceWebController::class, 'editLocation'])
        ->whereNumber('location')
        ->middleware('role:ADMIN,LOGISTICS_OPERATOR')
        ->name('locations.edit');
    Route::put('/locations/{location}', [CargoTraceWebController::class, 'updateLocation'])
        ->whereNumber('location')
        ->middleware('role:ADMIN,LOGISTICS_OPERATOR')
        ->name('locations.update');
    Route::get('/carriers', [CargoTraceWebController::class, 'carriersIndex'])->name('carriers.index');
    Route::get('/carriers/create', [CargoTraceWebController::class, 'createCarrier'])
        ->middleware('role:ADMIN,LOGISTICS_OPERATOR')
        ->name('carriers.create');
    Route::post('/carriers', [CargoTraceWebController::class, 'storeCarrier'])
        ->middleware('role:ADMIN,LOGISTICS_OPERATOR')
        ->name('carriers.store');
    Route::get('/carriers/{carrier}/edit', [CargoTraceWebController::class, 'editCarrier'])
        ->whereNumber('carrier')
        ->middleware('role:ADMIN,LOGISTICS_OPERATOR')
        ->name('carriers.edit');
    Route::put('/carriers/{carrier}', [CargoTraceWebController::class, 'updateCarrier'])
        ->whereNumber('carrier')
        ->middleware('role:ADMIN,LOGISTICS_OPERATOR')
        ->name('carriers.update');

    // In-app notifications (visible to the owner only)
    Route::get('/notifications', [CargoTraceWebController::class, 'notificationsIndex'])->name('notifications.index');
    Route::post('/notifications/read-all', [CargoTraceWebController::class, 'markAllNotificationsRead'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [CargoTraceWebController::class, 'markNotificationRead'])
        ->whereNumber('notification')
        ->name('notifications.read');

    // Admin only: audit trail, users & delay rules
    Route::middleware('role:ADMIN')->group(function () {
        Route::get('/audit', [CargoTraceWebController::class, 'auditIndex'])->name('audit.index');
        Route::get('/scenario', [CargoTraceWebController::class, 'scenarioIndex'])->name('scenario.index');
        Route::post('/scenario', [CargoTraceWebController::class, 'updateScenario'])->name('scenario.update');
        Route::post('/scenario/reset', [CargoTraceWebController::class, 'resetDemoData'])->name('scenario.reset');
        Route::post('/users', [CargoTraceWebController::class, 'storeUser'])->name('users.store');
        Route::patch('/users/{user}', [CargoTraceWebController::class, 'updateUser'])
            ->whereNumber('user')
            ->name('users.update');
    });
});
