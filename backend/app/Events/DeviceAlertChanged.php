<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class DeviceAlertChanged implements SSEEvent
{
    use Dispatchable;

    public function __construct(
        public readonly string $deviceId,
        public readonly string $roomId,
        public readonly string $metric,
        public readonly float|int $value,
        public readonly string $unit,
        public readonly float $threshold,
        public readonly string $status,
    ) {}

    public function sseType(): string
    {
        return 'alert';
    }

    public function ssePayload(): array
    {
        return [
            'device_id' => $this->deviceId,
            'room_id' => $this->roomId,
            'metric' => $this->metric,
            'value' => $this->value,
            'unit' => $this->unit,
            'threshold' => $this->threshold,
            'status' => $this->status,
        ];
    }
}
