<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommandResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'command_id'  => $this->command_id,
            'device_id'   => $this->device_id,
            'status'      => $this->status,
            'ventilation' => $this->ventilation,
            'reason'      => $this->reason,
            'executed_at' => $this->executed_at?->toIso8601String(),
            'reported_at' => $this->reported_at?->toIso8601String(),
            'created_at'  => $this->created_at->toIso8601String(),
            'updated_at'  => $this->updated_at->toIso8601String(),
        ];
    }
}
