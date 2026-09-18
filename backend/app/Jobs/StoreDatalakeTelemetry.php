<?php

namespace App\Jobs;

use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class StoreDatalakeTelemetry implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $topic,
        public readonly string $payload,
        public readonly Carbon $enqueuedAt,
    ) {
        $this->onQueue('datalake');
    }

    public function handle(): void {}
}
