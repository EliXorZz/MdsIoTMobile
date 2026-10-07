<?php

namespace App\Events;

use App\Data\CommandResultData;
use Illuminate\Foundation\Events\Dispatchable;

class CommandResultReceived implements SSEEvent
{
    use Dispatchable;

    public function __construct(public readonly CommandResultData $result) {}

    public function sseType(): string
    {
        return 'state';
    }

    public function ssePayload(): array
    {
        return [
            'device_id' => $this->result->device_id,
            'ventilation' => $this->result->ventilation,
        ];
    }
}
