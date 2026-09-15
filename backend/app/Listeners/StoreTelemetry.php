<?php

namespace App\Listeners;

use App\Events\TelemetryReceived;
use App\Models\Device;
use App\Models\Telemetry;
use Illuminate\Support\Facades\Log;

class StoreTelemetry
{
    public function handle(TelemetryReceived $event): void
    {
        $t = $event->telemetry;

        Log::info('mqtt.telemetry', [
            'device_id'   => $t->device_id,
            'observed_at' => $t->observed_at->toIso8601String(),
            'temperature' => $t->temperature->value,
            'co2'         => $t->co2->value,
        ]);

        Telemetry::insertOrIgnore([
            'observed_at' => $t->observed_at,
            'device_id'   => $t->device_id,
            'room_id'     => $t->room_id,
            'message_id'  => $t->message_id,
            'temperature' => $t->temperature->value,
            'co2'         => $t->co2->value,
        ]);

        Device::upsert(
            [['device_id' => $t->device_id, 'room_id' => $t->room_id, 'last_seen_at' => $t->observed_at]],
            ['device_id'],
            ['room_id', 'last_seen_at'],
        );
    }
}
