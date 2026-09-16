<?php

namespace App\Listeners;

use App\Events\TelemetryReceived;
use App\Models\Device;
use App\Services\ClickHouseService;
use Illuminate\Support\Facades\Log;

class StoreTelemetry
{
    public function __construct(private readonly ClickHouseService $clickhouse) {}

    public function handle(TelemetryReceived $event): void
    {
        $t = $event->telemetry;

        Log::info('mqtt.telemetry', [
            'device_id'   => $t->device_id,
            'observed_at' => $t->observed_at->toIso8601String(),
            'temperature' => $t->temperature->value,
            'co2'         => $t->co2->value,
        ]);

        $this->clickhouse->insert([
            'message_id'  => $t->message_id,
            'device_id'   => $t->device_id,
            'room_id'     => $t->room_id,
            'observed_at' => $t->observed_at->toIso8601String(),
            'temperature' => $t->temperature->value,
            'co2'         => (float) $t->co2->value,
        ]);

        Device::upsert(
            [['device_id' => $t->device_id, 'room_id' => $t->room_id, 'last_seen_at' => $t->observed_at]],
            ['device_id'],
            ['room_id', 'last_seen_at'],
        );
    }
}
