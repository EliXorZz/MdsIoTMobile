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
            'temperature' => $this->median_temperature,
            'min_temperature' => $this->min_temperature,
            'max_temperature' => $this->max_temperature,
            'co2' => $this->median_co2,
            'min_co2' => $this->min_co2,
            'max_co2' => $this->max_co2,
            'samples' => $this->samples,
        ];
    }
}
