<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TelemetryAggregateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'bucket' => $this->bucket->toIso8601String(),
            'temperature' => round((float) $this->median_temperature, 1),
            'min_temperature' => round((float) $this->min_temperature, 1),
            'max_temperature' => round((float) $this->max_temperature, 1),
            'co2' => (int) round((float) $this->median_co2),
            'min_co2' => (int) round((float) $this->min_co2),
            'max_co2' => (int) round((float) $this->max_co2),
            'samples' => $this->samples,
        ];
    }
}
