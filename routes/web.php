<?php

use App\Http\Controllers\Web\CargoTraceWebController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CargoTrace Web Routes (Laravel 11)
|--------------------------------------------------------------------------
|
| Serves both server-rendered Blade views and the interactive Vite SPA shell
| with identical routes, 3-color palette (#080B0C, #FFFFFF, #C6F432),
| White Mode default, and mobile slide-over drawer.
|
*/

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
