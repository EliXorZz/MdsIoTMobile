<?php

use App\Http\Controllers\DeviceController;
use Illuminate\Support\Facades\Route;

Route::prefix('devices')->group(function () {
    Route::get('/', [DeviceController::class, 'index']);
    Route::get('/{device}', [DeviceController::class, 'show']);
    Route::get('/{device}/telemetry', [DeviceController::class, 'telemetry']);
    Route::get('/{device}/commands', [DeviceController::class, 'commands']);
});
