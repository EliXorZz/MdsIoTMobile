<?php

namespace App\Data;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class DeviceStateData extends Data
{
    public function __construct(
        public readonly int $schema_version,
        public readonly string $device_id,
        #[WithCast(DateTimeInterfaceCast::class, format: ['Y-m-d\TH:i:s.v\Z', 'Y-m-d\TH:i:s\Z'])]
        public readonly CarbonImmutable $reported_at,
        public readonly string $boot_id,
        public readonly bool $ventilation,
    ) {}
}
