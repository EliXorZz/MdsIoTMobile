<?php

namespace App\Listeners;

use App\Data\TelemetryData;
use App\Events\TelemetryBatchReceived;
use App\Models\Device;
use App\Models\Telemetry;

class StoreTelemetryBatch
{
    public function handle(TelemetryBatchReceived $event): void
    {
        Telemetry::insertOrIgnore(array_map($this->toRow(...), $event->batch));

        Device::upsert($this->latestPerDevice($event->batch), ['device_id'], ['room_id', 'last_seen_at']);
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

    /** @param TelemetryData[] $batch */
    private function latestPerDevice(array $batch): array
    {
        $devices = [];

        foreach ($batch as $telemetry) {
            $id = $telemetry->device_id;

            if (! isset($devices[$id]) || $telemetry->observed_at > $devices[$id]['last_seen_at']) {
                $devices[$id] = [
                    'device_id' => $telemetry->device_id,
                    'room_id' => $telemetry->room_id,
                    'last_seen_at' => $telemetry->observed_at,
                ];
            }
        }

        return array_values($devices);
    }
}
