<?php

namespace App\Listeners;

use App\Events\RawTelemetryReceived;
use App\Jobs\ProcessTelemetry;
use App\Jobs\StoreDatalakeTelemetry;
use Carbon\Carbon;

class StoreRawTelemetry
{
    public function handle(RawTelemetryReceived $event): void
    {
        $now = Carbon::now();
        ProcessTelemetry::dispatch($event->topic, $event->rawMessage, $now);
        StoreDatalakeTelemetry::dispatch($event->topic, $event->rawMessage, $now);
    }
}
