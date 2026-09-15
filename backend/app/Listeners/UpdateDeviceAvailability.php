<?php

namespace App\Listeners;

use App\Events\DeviceAvailabilityChanged;
use App\Models\Device;
use Illuminate\Support\Facades\Log;

class UpdateDeviceAvailability
{
    public function handle(DeviceAvailabilityChanged $event): void
    {
        $a = $event->availability;

        Log::info('mqtt.availability', [
            'device_id'   => $a->device_id,
            'status'      => $a->status,
            'reason'      => $a->reason,
            'reported_at' => $a->reported_at?->toIso8601String(),
        ]);

        $values = [
            'device_id' => $a->device_id,
            'online'    => $a->status === 'online',
        ];

        if ($a->reported_at) {
            $values['last_seen_at'] = $a->reported_at;
        }

        Device::upsert($values, ['device_id'], array_keys(array_diff_key($values, ['device_id' => null])));
    }
}
