<?php

namespace App\Listeners;

use App\Events\DeviceStateReceived;
use App\Logging\StructuredLog;
use App\Models\Device;

class UpdateDeviceState
{
    public function handle(DeviceStateReceived $event): void
    {
        $s = $event->state;

        StructuredLog::withContext([
            'deviceId' => $s->device_id,
            'topic' => sprintf('campus/v1/devices/%s/state', $s->device_id),
            'status' => 'accepted',
            'ventilation' => $s->ventilation,
            'bootId' => $s->boot_id,
            'reportedAt' => $s->reported_at->toIso8601String(),
        ])->info('state.received', 'Device state received');

        Device::upsert(
            [['device_id' => $s->device_id, 'ventilation' => $s->ventilation, 'boot_id' => $s->boot_id]],
            ['device_id'],
            ['ventilation', 'boot_id'],
        );
    }
}
