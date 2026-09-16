<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class SensorValueData extends Data
{
    public function __construct(
        public readonly float $value,
        public readonly string $unit,
    ) {}
}
