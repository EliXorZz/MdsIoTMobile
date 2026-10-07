<?php

namespace App\Listeners;

use App\Enums\AvailabilityStatus;
use App\Events\DeviceAvailabilityChanged;
use App\Models\Device;

class UpdateDeviceAvailability
{
    public function handle(DeviceAvailabilityChanged $event): void
    {
        $a = $event->availability;

        $values = [
            'device_id' => $a->device_id,
            'online' => $a->status === AvailabilityStatus::Online,
        ];

        if ($a->reported_at) {
            $values['last_seen_at'] = $a->reported_at;
        }

        Device::upsert($values, ['device_id'], array_keys(array_diff_key($values, ['device_id' => null])));
    }
}
