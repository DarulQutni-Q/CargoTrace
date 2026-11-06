<?php

use App\Http\Controllers\Web\CargoTraceWebController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CargoTrace Web Routes (Laravel 11 + Vite UI Shell)
|--------------------------------------------------------------------------
|
| Renders the exact CargoTrace frontend UI (White Mode default, #080B0C /
| #FFFFFF / #C6F432 palette, 2D Map + 3D Spherical Globe, Multi-Leg
| Checkpoints, 38 Indonesian Provinces, and Printable e-B/L) via
| resources/views/app.blade.php so the Laravel view is 100% identical.
|
*/

// Server-rendered Blade inspection routes (optional direct Blade views)
Route::prefix('blade')->group(function () {
    Route::get('/', [CargoTraceWebController::class, 'dashboard'])->name('dashboard');
    Route::get('/shipments', [CargoTraceWebController::class, 'shipmentsIndex'])->name('shipments.index');
    Route::get('/shipments/{shipment}', [CargoTraceWebController::class, 'shipmentShow'])
        ->whereNumber('shipment')
        ->name('shipments.show');
    Route::get('/shipments/{shipment}/bill-of-lading', [CargoTraceWebController::class, 'billOfLading'])
        ->whereNumber('shipment')
        ->name('shipments.bol');
    Route::get('/locations', [CargoTraceWebController::class, 'locationsIndex'])->name('locations.index');
    Route::get('/carriers', [CargoTraceWebController::class, 'carriersIndex'])->name('carriers.index');
    Route::get('/audit', [CargoTraceWebController::class, 'auditIndex'])->name('audit.index');
});

// Primary Laravel Web Entry Point — renders the exact CargoTrace UI via Vite
Route::view('/{any?}', 'app')->where('any', '^(?!api|blade).*$')->name('cargotrace.app');
