<?php

namespace App\Events;

use App\Data\TelemetryData;
use Illuminate\Foundation\Events\Dispatchable;

class TelemetryReceived
{
    use Dispatchable;

    public function __construct(public readonly TelemetryData $telemetry) {}
}
