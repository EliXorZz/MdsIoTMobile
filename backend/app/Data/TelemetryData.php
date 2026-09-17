<?php

namespace App\Data;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class TelemetryData extends Data
{
    public function __construct(
        public readonly int $schema_version,

        public readonly string $message_id,
        public readonly string $device_id,
        public readonly string $room_id,

        #[WithCast(DateTimeInterfaceCast::class, format: ['Y-m-d\TH:i:s.v\Z', 'Y-m-d\TH:i:s.uP', 'Y-m-d\TH:i:s\Z'])]
        public readonly CarbonImmutable $observed_at,

        public readonly SensorValueData $temperature,
        public readonly SensorValueData $co2,
    ) {}
}
