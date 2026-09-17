<?php

namespace App\Listeners;

use App\Data\TelemetryData;
use App\Events\TelemetryBatchReceived;
use App\Models\Device;
use App\Models\Telemetry;
use App\Pipes\Telemetry\CheckSensorFault;
use Illuminate\Pipeline\Pipeline;

class StoreTelemetryBatch
{
    private const ANALYSIS_PIPES = [
        CheckSensorFault::class,
    ];

    public function __construct(
        private readonly Pipeline $pipeline
    ) {}

    public function handle(TelemetryBatchReceived $event): void
    {
        $batch = $event->batch;

        if (empty($batch)) {
            return;
        }

        $rows = array_map(fn (TelemetryData $t) => $this->toRow($t), $batch);
        Telemetry::insertOrIgnore($rows);

        foreach ($batch as $telemetry) {
            $this->pipeline->send($telemetry)->through(self::ANALYSIS_PIPES)->thenReturn();
        }

        $this->upsertDeviceLastSeen($batch);
    }

    /** @param TelemetryData[] $batch */
    private function upsertDeviceLastSeen(array $batch): void
    {
        $latest = [];
        foreach ($batch as $t) {
            if (! isset($latest[$t->device_id]) || $t->observed_at > $latest[$t->device_id]['last_seen_at']) {
                $latest[$t->device_id] = [
                    'device_id' => $t->device_id,
                    'room_id' => $t->room_id,
                    'last_seen_at' => $t->observed_at,
                ];
            }
        }

        Device::upsert(array_values($latest), ['device_id'], ['room_id', 'last_seen_at']);
    }

    private function toRow(TelemetryData $telemetry): array
    {
        return [
            'message_id' => $telemetry->message_id,
            'device_id' => $telemetry->device_id,
            'room_id' => $telemetry->room_id,
            'observed_at' => $telemetry->observed_at,
            'temperature' => $telemetry->temperature->value,
            'co2' => $telemetry->co2->value,
        ];
    }
}
