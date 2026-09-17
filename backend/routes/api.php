<?php

use App\Http\Controllers\DeviceController;
use App\Http\Controllers\QrController;
use App\Http\Controllers\SSEController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::post('/qr/verify', [QrController::class, 'verify']);

Route::get('/devices/events', [SSEController::class, 'stream']);

Route::prefix('devices')->group(function () {
    Route::get('/', [DeviceController::class, 'index']);
    Route::get('/{device}', [DeviceController::class, 'show']);
    Route::get('/{device}/telemetry', [DeviceController::class, 'telemetry']);
    Route::get('/{device}/commands', [DeviceController::class, 'commands']);
});
