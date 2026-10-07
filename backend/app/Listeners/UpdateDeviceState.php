<?php

namespace App\Listeners;

use App\Events\DeviceStateChanged;
use App\Events\DeviceStateReceived;
use App\Logging\LogEvent;
use App\Logging\StructuredLog;
use App\Models\Device;

class UpdateDeviceState
{
    public function handle(DeviceStateReceived $event): void
    {
        $s = $event->state;

        $log = StructuredLog::withContext([
            'deviceId' => $s->device_id,
            'topic' => sprintf('campus/v1/devices/%s/state', $s->device_id),
            'ventilation' => $s->ventilation,
            'bootId' => $s->boot_id,
            'reportedAt' => $s->reported_at->toIso8601String(),
        ]);

        Device::firstOrCreate(['device_id' => $s->device_id]);

        $updated = Device::where('device_id', $s->device_id)
            ->where(fn ($q) => $q
                ->whereNull('state_reported_at')
                ->orWhere('state_reported_at', '<', $s->reported_at)
            )
            ->update([
                'ventilation' => $s->ventilation,
                'boot_id' => $s->boot_id,
                'state_reported_at' => $s->reported_at,
            ]);

        if ($updated) {
            $log->info(LogEvent::StateReceived, 'Device state received', ['status' => 'accepted']);
            DeviceStateChanged::dispatch($s);
        } else {
            $log->warning(LogEvent::StateRejected, 'Stale device state ignored', [
                'status' => 'rejected',
                'reason' => 'stale_timestamp',
            ]);
        }
    }
}
