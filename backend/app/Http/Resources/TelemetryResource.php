<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TelemetryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'observed_at' => $this->observed_at->toIso8601String(),
            'temperature' => $this->temperature,
            'co2' => $this->co2,
        ];
    }
}
