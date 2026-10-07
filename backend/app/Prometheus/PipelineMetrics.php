<?php

namespace App\Prometheus;

use Prometheus\CollectorRegistry;
use Prometheus\Counter;

class PipelineMetrics
{
    private const NS = 'app';

    public function __construct(private readonly CollectorRegistry $registry) {}

    public function mqttReceived(): void
    {
        $this->counter('telemetry_mqtt_received', 'Telemetry messages received via MQTT by the subscriber')
            ->inc();
    }

    public function stored(int $count): void
    {
        $this->counter('telemetry_stored', 'Telemetry rows successfully stored in TimescaleDB')
            ->incBy($count);
    }

    public function rejected(string $reason): void
    {
        $this->counter('telemetry_rejected', 'Telemetry messages rejected before storage', ['reason'])
            ->inc([$reason]);
    }

    public function duplicates(int $count): void
    {
        $this->counter('telemetry_duplicates', 'Duplicate telemetry rows ignored by insertOrIgnore')
            ->incBy($count);
    }

    public function datalakeStored(int $count): void
    {
        $this->counter('datalake_stored', 'Raw telemetry documents written to MongoDB')
            ->incBy($count);
    }

    private function counter(string $name, string $help, array $labelNames = []): Counter
    {
        return $this->registry->getOrRegisterCounter(self::NS, $name, $help, $labelNames);
    }
}
