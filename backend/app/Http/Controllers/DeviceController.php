<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListCommandResultsRequest;
use App\Http\Requests\ListDevicesRequest;
use App\Http\Requests\ListTelemetryRequest;
use App\Http\Resources\CommandResultResource;
use App\Http\Resources\DeviceResource;
use App\Http\Resources\TelemetryResource;
use App\Models\Device;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeviceController extends Controller
{
    public function index(ListDevicesRequest $request): AnonymousResourceCollection
    {
        $query = Device::with('latestTelemetry');

        if ($request->has('online')) {
            $query->where('online', $request->boolean('online'));
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

    public function telemetry(ListTelemetryRequest $request, Device $device): AnonymousResourceCollection
    {
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

    public function commands(ListCommandResultsRequest $request, Device $device): AnonymousResourceCollection
    {
        $query = $device->commandResults()->orderByDesc('created_at');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return CommandResultResource::collection(
            $query->paginate($request->integer('per_page', 20))
        );
    }
}
