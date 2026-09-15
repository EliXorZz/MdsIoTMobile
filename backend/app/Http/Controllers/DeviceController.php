<?php

namespace App\Http\Controllers;

use App\Http\Resources\CommandResultResource;
use App\Http\Resources\DeviceResource;
use App\Http\Resources\TelemetryResource;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeviceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Device::with('latestTelemetry');

        if ($request->has('online')) {
            $query->where('online', filter_var($request->online, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('room_id')) {
            $query->where('room_id', $request->room_id);
        }

        return DeviceResource::collection(
            $query->orderBy('device_id')->paginate(50)
        );
    }

    public function show(Device $device): DeviceResource
    {
        return DeviceResource::make(
            $device->load('latestTelemetry')
        );
    }

    public function telemetry(Request $request, Device $device): AnonymousResourceCollection
    {
        $request->validate([
            'from'     => ['nullable', 'date'],
            'to'       => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $query = $device->telemetry()->orderByDesc('observed_at');

        if ($from = $request->input('from')) {
            $query->where('observed_at', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->where('observed_at', '<=', $to);
        }

        return TelemetryResource::collection(
            $query->paginate($request->integer('per_page', 100))
        );
    }

    public function commands(Request $request, Device $device): AnonymousResourceCollection
    {
        $request->validate([
            'status'   => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = $device->commandResults()->orderByDesc('created_at');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return CommandResultResource::collection(
            $query->paginate($request->integer('per_page', 20))
        );
    }
}
