<?php

use App\Http\Controllers\CommandController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\SSEController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::get('/devices/events', [SSEController::class, 'stream']);

Route::prefix('devices')->group(function () {
    Route::get('/', [DeviceController::class, 'index']);
    Route::get('/{device}', [DeviceController::class, 'show']);
    Route::get('/{device}/telemetry', [DeviceController::class, 'telemetry']);
    Route::get('/{device}/commands', [CommandController::class, 'index']);
    Route::post('/{device}/commands', [CommandController::class, 'store']);
});
