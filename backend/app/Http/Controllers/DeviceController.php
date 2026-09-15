<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListCommandResultsRequest;
use App\Http\Requests\ListDevicesRequest;
use App\Http\Requests\ListTelemetryRequest;
use App\Http\Resources\CommandResultResource;
use App\Http\Resources\DeviceResource;
use App\Http\Resources\TelemetryResource;
use App\Models\Device;
use App\Models\Telemetry;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeviceController extends Controller
{
    public function index(ListDevicesRequest $request): AnonymousResourceCollection
    {
        $data = $request->validated();

        $query = Device::with('latestTelemetry');

        if (($data['online'] ?? null) !== null) {
            $query->where('online', $data['online']);
        }

        if (($data['room_id'] ?? null) !== null) {
            $query->where('room_id', $data['room_id']);
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
        $data = $request->validated();

        $bucket = $data['bucket'] ?? null;

        $query = $bucket
            ? $device->telemetry()->bucketed($bucket)->orderByRaw('1 DESC')
            : $device->telemetry()->orderByDesc('observed_at');

        if (($data['from'] ?? null) !== null) {
            $query->where('observed_at', '>=', $data['from']);
        }

        if (($data['to'] ?? null) !== null) {
            $query->where('observed_at', '<=', $data['to']);
        }

        return TelemetryResource::collection(
            $query->paginate($data['per_page'] ?? 100)
        );
    }

    public function commands(ListCommandResultsRequest $request, Device $device): AnonymousResourceCollection
    {
        $data = $request->validated();

        $query = $device->commandResults()->orderByDesc('created_at');

        if (($data['status'] ?? null) !== null) {
            $query->where('status', $data['status']);
        }

        return CommandResultResource::collection(
            $query->paginate($data['per_page'] ?? 20)
        );
    }
}
