<?php

namespace App\Events;

use App\Data\DeviceStateData;
use Illuminate\Foundation\Events\Dispatchable;

class DeviceStateChanged implements SSEEvent
{
    use Dispatchable;

    public function __construct(public readonly DeviceStateData $state) {}

    public function sseType(): string
    {
        return 'state';
    }

    public function ssePayload(): array
    {
        return [
            'device_id' => $this->state->device_id,
            'ventilation' => $this->state->ventilation,
            'boot_id' => $this->state->boot_id,
        ];
    }
}
