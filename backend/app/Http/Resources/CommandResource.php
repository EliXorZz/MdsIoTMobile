<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'command_id' => $this->command_id,
            'device_id' => $this->device_id,
            'action' => $this->action,
            'params' => $this->params,
            'status' => $this->effective_status->value,
            'issued_at' => $this->issued_at->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'acked_at' => $this->acked_at?->toIso8601String(),
            'timeout_at' => $this->timeout_at->toIso8601String(),
        ];
    }
}
