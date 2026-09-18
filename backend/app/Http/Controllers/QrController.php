<?php

namespace App\Http\Controllers;

use App\Http\Requests\VerifyQrRequest;
use App\Http\Resources\DeviceResource;
use App\Models\Device;
use Illuminate\Http\JsonResponse;

class QrController extends Controller
{
    private const MAX_AGE = 300; // secondes

    public function verify(VerifyQrRequest $request): DeviceResource|JsonResponse
    {
        $data = $request->validated();

        $secret = config('services.qr.secret');
        if (! $secret) {
            return response()->json(['message' => 'QR_SECRET non configuré'], 500);
        }

        if (time() - $data['issued_at'] > self::MAX_AGE) {
            return response()->json(['message' => 'Token QR expiré'], 422);
        }

        $message = "{$data['device_id']}|{$data['issued_at']}";
        $expected = hash_hmac('sha256', $message, $secret);

        if (! hash_equals($expected, $data['sig'])) {
            return response()->json(['message' => 'Signature QR invalide'], 422);
        }

        $device = Device::with('latestTelemetry')->find($data['device_id']);
        if (! $device) {
            return response()->json(['message' => 'Device introuvable'], 404);
        }

        return DeviceResource::make($device);
    }
}
