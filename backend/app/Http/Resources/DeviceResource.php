<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->device_id,
            'room_id'      => $this->room_id,
            'online'       => $this->online,
            'ventilation'  => $this->ventilation,
            'boot_id'      => $this->boot_id,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'created_at'   => $this->created_at->toIso8601String(),
            'updated_at'   => $this->updated_at->toIso8601String(),
            'latest_telemetry' => TelemetryResource::make($this->whenLoaded('latestTelemetry')),
        ];
    }
}
