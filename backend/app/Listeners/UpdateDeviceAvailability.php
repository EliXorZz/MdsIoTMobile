<?php

namespace App\Listeners;

use App\Enums\AvailabilityStatus;
use App\Events\DeviceAvailabilityChanged;
use App\Logging\LogEvent;
use App\Logging\StructuredLog;
use App\Models\Device;

class UpdateDeviceAvailability
{
    public function handle(DeviceAvailabilityChanged $event): void
    {
        $a = $event->availability;

        $log = StructuredLog::withContext([
            'deviceId'   => $a->device_id,
            'topic'      => sprintf('campus/v1/devices/%s/availability', $a->device_id),
            'onlineStatus' => $a->status->value,
            'reportedAt' => $a->reported_at?->toIso8601String(),
        ]);

        Device::firstOrCreate(['device_id' => $a->device_id]);

        // Will messages carry no timestamp (broker-generated) — always apply.
        if ($a->reported_at === null) {
            Device::where('device_id', $a->device_id)
                ->update(['online' => $a->status === AvailabilityStatus::Online]);

            $log->info(LogEvent::AvailabilityReceived, 'Device availability received (will)', ['status' => 'accepted']);

            return;
        }

        $updated = Device::where('device_id', $a->device_id)
            ->where(fn ($q) => $q
                ->whereNull('last_seen_at')
                ->orWhere('last_seen_at', '<', $a->reported_at)
            )
            ->update([
                'online'       => $a->status === AvailabilityStatus::Online,
                'last_seen_at' => $a->reported_at,
            ]);

        if ($updated) {
            $log->info(LogEvent::AvailabilityReceived, 'Device availability received', ['status' => 'accepted']);
        } else {
            $log->warning(LogEvent::AvailabilityRejected, 'Stale availability ignored', [
                'status' => 'rejected',
                'reason' => 'stale_timestamp',
            ]);
        }
    }
}
