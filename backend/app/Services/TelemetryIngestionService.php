<?php

namespace App\Services;

use App\Data\TelemetryData;
use App\Events\TelemetryBatchReceived;

class TelemetryIngestionService
{
    private const BATCH_SIZE = 500;

    private const FLUSH_EVERY_S = 0.2;

    /** @var TelemetryData[] */
    private array $buffer = [];

    private float $lastFlush = 0;

    private int $received = 0;

    private int $inserted = 0;

    private int $rejected = 0;

    public function __construct()
    {
        $this->lastFlush = microtime(true);
    }

    public function push(TelemetryData $telemetry): void
    {
        $this->received++;
        $this->buffer[] = $telemetry;
    }

    public function reject(): void
    {
        $this->received++;
        $this->rejected++;
    }

    public function flushIfNeeded(): void
    {
        if (count($this->buffer) >= self::BATCH_SIZE || microtime(true) - $this->lastFlush >= self::FLUSH_EVERY_S) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        $batch = $this->buffer;
        $this->buffer = [];
        $this->lastFlush = microtime(true);

        event(new TelemetryBatchReceived($batch));
        $this->inserted += count($batch);
    }

    public function received(): int
    {
        return $this->received;
    }

    public function inserted(): int
    {
        return $this->inserted;
    }

    public function rejected(): int
    {
        return $this->rejected;
    }

    public function lag(): int
    {
        return max(0, $this->received - $this->inserted - $this->rejected);
    }
}
