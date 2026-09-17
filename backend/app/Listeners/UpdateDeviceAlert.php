<?php

namespace App\Listeners;

use App\Events\DeviceAlertChanged;
use App\Models\Device;

class UpdateDeviceAlert
{
    public function handle(DeviceAlertChanged $event): void
    {
        Device::where('device_id', $event->deviceId)
            ->update(['co2_alert' => $event->status === 'raised']);
    }
}
