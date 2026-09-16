<?php

namespace App\Events;

use App\Data\TelemetryData;

class TelemetryBatchReceived
{
    /** @param TelemetryData[] $batch */
    public function __construct(
        public readonly array $batch,
    ) {}
}
