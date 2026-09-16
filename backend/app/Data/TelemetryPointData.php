<?php

namespace App\Data;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class TelemetryPointData extends Data
{
    public function __construct(
        #[WithCast(DateTimeInterfaceCast::class, format: ['Y-m-d\TH:i:s\Z', 'Y-m-d\TH:i:s.v\Z', 'Y-m-d H:i:s'])]
        public readonly CarbonImmutable $observed_at,
        public readonly float $temperature,
        public readonly int $co2,
    ) {}
}
