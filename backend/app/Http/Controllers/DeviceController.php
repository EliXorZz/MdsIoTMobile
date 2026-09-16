<?php

namespace App\Http\Controllers;

use App\Data\TelemetryPointData;
use App\Http\Requests\ListCommandResultsRequest;
use App\Http\Requests\ListDevicesRequest;
use App\Http\Requests\ListTelemetryRequest;
use App\Http\Resources\CommandResultResource;
use App\Http\Resources\DeviceResource;
use App\Models\Device;
use App\Services\ClickHouseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class DeviceController extends Controller
{
    public function __construct(private readonly ClickHouseService $clickhouse) {}

    public function index(ListDevicesRequest $request): AnonymousResourceCollection
    {
        $data = $request->validated();

        $query = Device::query();

        if (($data['online'] ?? null) !== null) {
            $query->where('online', $data['online']);
        }

        if (($data['room_id'] ?? null) !== null) {
            $query->where('room_id', $data['room_id']);
        }

        $devices = $query->orderBy('device_id')->paginate(50);

        $this->attachLatestTelemetry($devices->items());

        return DeviceResource::collection($devices);
    }

    public function show(Device $device): DeviceResource
    {
        $this->attachLatestTelemetry([$device]);

        return DeviceResource::make($device);
    }

    /**
     * @param Device[] $devices
     */
    private function attachLatestTelemetry(array $devices): void
    {
        $ids = array_map(fn(Device $d) => $d->device_id, $devices);
        $latestMap = $this->clickhouse->latestTelemetryForDevices($ids);

        foreach ($devices as $device) {
            $row = $latestMap[$device->device_id] ?? null;
            $device->setRelation(
                'latestTelemetry',
                $row !== null ? TelemetryPointData::from($row) : null
            );
        }
    }

    public function telemetry(ListTelemetryRequest $request, Device $device): JsonResponse
    {
        $data = $request->validated();

        $bucket  = $data['bucket'] ?? null;
        $perPage = $data['per_page'] ?? 100;
        $page    = $data['page'] ?? 1;
        $offset  = ($page - 1) * $perPage;

        $timeField = $bucket ? 'bucket' : 'observed_at';
        $table     = $bucket ? "telemetry_{$bucket}" : 'telemetry_raw FINAL';

        $params = [
            'device_id' => $device->device_id,
            'per_page'  => $perPage,
            'offset'    => $offset,
        ];

        $where = 'device_id = {device_id:String}';

        if (($data['from'] ?? null) !== null) {
            $where .= " AND {$timeField} >= '" . Carbon::parse($data['from'])->format('Y-m-d H:i:s') . "'";
        }
        if (($data['to'] ?? null) !== null) {
            $where .= " AND {$timeField} <= '" . Carbon::parse($data['to'])->format('Y-m-d H:i:s') . "'";
        }

        if ($bucket) {
            $selectSql = "
                SELECT
                    formatDateTime(bucket, '%Y-%m-%dT%H:%i:%SZ')          AS observed_at,
                    ROUND(quantileMerge(0.5)(temperature) * 2) / 2.0      AS temperature,
                    ROUND(quantileMerge(0.5)(co2))::Int32                  AS co2
                FROM {$table}
                WHERE {$where}
                GROUP BY bucket
                ORDER BY bucket DESC
                LIMIT {per_page:UInt32}
                OFFSET {offset:UInt64}
            ";
            $countSql = "SELECT uniqExact(bucket) AS total FROM {$table} WHERE {$where}";
        } else {
            $selectSql = "
                SELECT
                    formatDateTime(observed_at, '%Y-%m-%dT%H:%i:%SZ')    AS observed_at,
                    ROUND(temperature * 2) / 2.0                          AS temperature,
                    ROUND(co2)::Int32                                      AS co2
                FROM {$table}
                WHERE {$where}
                ORDER BY observed_at DESC
                LIMIT {per_page:UInt32}
                OFFSET {offset:UInt64}
            ";
            $countSql = "SELECT COUNT(*) AS total FROM {$table} WHERE {$where}";
        }

        $rows  = $this->clickhouse->select($selectSql, $params);
        $total = (int) ($this->clickhouse->select($countSql, $params)[0]['total'] ?? 0);

        /** @var TelemetryPointData[] $points */
        $points = array_map(fn(array $row) => TelemetryPointData::from($row), $rows);

        $lastPage = (int) max(1, ceil($total / $perPage));

        return response()->json([
            'data' => array_map(fn(TelemetryPointData $p) => [
                'observed_at' => $p->observed_at->toIso8601String(),
                'temperature' => $p->temperature,
                'co2'         => $p->co2,
            ], $points),
            'pagination' => [
                'current_page' => $page,
                'last_page'    => $lastPage,
                'per_page'     => $perPage,
                'total'        => $total,
                'from'         => $total > 0 ? $offset + 1 : null,
                'to'           => $total > 0 ? min($offset + $perPage, $total) : null,
            ],
        ]);
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
