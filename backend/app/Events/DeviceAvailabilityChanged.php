<?php

namespace App\Events;

use App\Data\AvailabilityData;
use App\Enums\AvailabilityStatus;
use Illuminate\Foundation\Events\Dispatchable;

class DeviceAvailabilityChanged implements SSEEvent
{
    use Dispatchable;

    public function __construct(public readonly AvailabilityData $availability) {}

    public function sseType(): string
    {
        return 'availability';
    }

    public function ssePayload(): array
    {
        return [
            'device_id'   => $this->availability->device_id,
            'online'      => $this->availability->status === AvailabilityStatus::Online,
            'last_seen_at' => $this->availability->reported_at?->toIso8601String(),
        ];
    }
}
