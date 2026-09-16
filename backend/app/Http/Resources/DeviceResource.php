<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class DeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->device_id,
            'room_id' => $this->room_id,
            'online' => $this->online,
            'is_stale' => $this->last_seen_at === null || $this->last_seen_at->isBefore(Carbon::now()->subMinutes(2)),
            'ventilation' => $this->ventilation,
            'boot_id' => $this->boot_id,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'latest_telemetry' => TelemetryAggregateResource::make(
                $this->whenLoaded('latestTelemetry')
            ),
        ];
    }
}
