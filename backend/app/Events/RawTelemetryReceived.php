<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class RawTelemetryReceived
{
    use Dispatchable;

    public function __construct(
        public readonly string $topic,
        public readonly string $rawMessage,
    ) {}
}
