<?php

namespace App\Http\Controllers;

use App\Http\Resources\CommandResultResource;
use App\Http\Resources\DeviceResource;
use App\Http\Resources\TelemetryResource;
use App\Models\Device;
use App\Models\Telemetry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class DeviceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Device::query();

        if ($request->has('online')) {
            $query->where('online', filter_var($request->online, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('room_id')) {
            $query->where('room_id', $request->room_id);
        }

        $devices = $query->orderBy('device_id')->paginate(50);

        $this->loadLatestTelemetry($devices->items());

        return DeviceResource::collection($devices);
    }

    public function show(Device $device): DeviceResource
    {
        $latest = Telemetry::where('device_id', $device->device_id)
            ->orderByDesc('observed_at')
            ->first();

        $device->setRelation('latestTelemetry', $latest);

        return DeviceResource::make($device);
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

    /** @param Device[] $devices */
    private function loadLatestTelemetry(array $devices): void
    {
        if (empty($devices)) {
            return;
        }

        $ids = array_map(fn ($d) => $d->device_id, $devices);

        $rows = Telemetry::select(DB::raw('DISTINCT ON (device_id) *'))
            ->whereIn('device_id', $ids)
            ->orderBy('device_id')
            ->orderByDesc('observed_at')
            ->get()
            ->keyBy('device_id');

        foreach ($devices as $device) {
            $device->setRelation('latestTelemetry', $rows->get($device->device_id));
        }
    }
}
