<?php

namespace App\Data;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class CommandResultData extends Data
{
    public function __construct(
        public readonly int $schema_version,
        public readonly string $device_id,
        public readonly string $command_id,
        public readonly string $status,
        #[WithCast(DateTimeInterfaceCast::class, format: ['Y-m-d\TH:i:s.v\Z', 'Y-m-d\TH:i:s\Z'])]
        public readonly ?CarbonImmutable $executed_at,
        public readonly ?bool $ventilation,
        public readonly ?string $reason,
        #[WithCast(DateTimeInterfaceCast::class, format: ['Y-m-d\TH:i:s.v\Z', 'Y-m-d\TH:i:s\Z'])]
        public readonly ?CarbonImmutable $reported_at,
    ) {}
}
