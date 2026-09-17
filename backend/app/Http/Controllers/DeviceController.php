<?php

namespace App\Http\Controllers;

use App\Enums\TelemetryResolution;
use App\Http\Requests\ListCommandResultsRequest;
use App\Http\Requests\ListDevicesRequest;
use App\Http\Requests\ListTelemetryRequest;
use App\Http\Resources\CommandResultResource;
use App\Http\Resources\DeviceResource;
use App\Http\Resources\TelemetryAggregateResource;
use App\Models\Device;
use App\Models\TelemetryAggregate;
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
        $resolution = TelemetryResolution::forRange($data['from'] ?? null, $data['to'] ?? null);

        $query = TelemetryAggregate::resolution($resolution)
            ->where('device_id', $device->device_id)
            ->where('bucket', '<', $resolution->currentBucketStart())
            ->orderBy('bucket');

        if ($data['from'] ?? null) {
            $query->where('bucket', '>=', $data['from']);
        }

        if ($data['to'] ?? null) {
            $query->where('bucket', '<=', $data['to']);
        }

        return TelemetryAggregateResource::collection($query->get());
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
