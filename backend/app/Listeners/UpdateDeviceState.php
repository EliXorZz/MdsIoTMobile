<?php

namespace App\Listeners;

use App\Events\DeviceStateReceived;
use App\Models\Device;
use Illuminate\Support\Facades\Log;

class UpdateDeviceState
{
    public function handle(DeviceStateReceived $event): void
    {
        $s = $event->state;

        Log::info('mqtt.state', [
            'device_id'   => $s->device_id,
            'ventilation' => $s->ventilation,
            'boot_id'     => $s->boot_id,
            'reported_at' => $s->reported_at->toIso8601String(),
        ]);

        Device::upsert(
            [['device_id' => $s->device_id, 'ventilation' => $s->ventilation, 'boot_id' => $s->boot_id]],
            ['device_id'],
            ['ventilation', 'boot_id'],
        );
    }
}
