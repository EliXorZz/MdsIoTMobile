<?php

namespace App\Console\Commands;

use App\Data\TelemetryData;
use App\Jobs\ProcessTelemetry;
use App\Logging\LogEvent;
use App\Logging\StructuredLog;
use App\Models\Telemetry;
use App\Prometheus\PipelineMetrics;
class TelemetryConsumer extends BatchConsumer
{
    protected $signature = 'telemetry:consume
                            {--batch=2000 : Number of messages per batch}
                            {--timeout=0.2 : Seconds before flushing a partial batch}';

    protected $description = 'Consume the telemetry RabbitMQ queue in batches';

    private int $lagSumMs = 0;

    private int $lagMaxMs = 0;

    private int $lagCount = 0;

    private float $lagWindowStartedAt = 0.0;

    protected function processBatch(array $messages): void
    {
        $nowMs = (int) round(microtime(true) * 1000);
        $rows = [];

        foreach ($messages as $msg) {
            $envelope = json_decode($msg->getBody(), true);
            /** @var ProcessTelemetry $job */
            $job = unserialize($envelope['data']['command']);

            $lagMs = max(0, $nowMs - $job->enqueuedAt->getTimestampMs());
            $this->lagSumMs += $lagMs;
            $this->lagMaxMs = max($this->lagMaxMs, $lagMs);
            $this->lagCount++;

            try {
                $data = TelemetryData::from(json_decode($job->payload, true));
            } catch (\Throwable $e) {
                StructuredLog::withContext([
                    'topic'   => $job->topic,
                    'payload' => substr($job->payload, 0, 200),
                    'reason'  => $e->getMessage(),
                ])->error(LogEvent::TelemetryInvalidPayload, 'Invalid payload, message dropped');

                app(PipelineMetrics::class)->rejected('invalid_payload');
                continue;
            }

            $ageS = (int) round(microtime(true) - $data->observed_at->getTimestamp());
            if ($ageS >= 60) {
                StructuredLog::withContext([
                    'topic'      => $job->topic,
                    'deviceId'   => $data->device_id,
                    'messageId'  => $data->message_id,
                    'observedAt' => $data->observed_at->toIso8601String(),
                    'ageS'       => $ageS,
                ])->warning(LogEvent::TelemetryExpired, 'Expired telemetry dropped');

                app(PipelineMetrics::class)->rejected('expired');
                continue;
            }

            $rows[] = [
                'observed_at' => $data->observed_at->toDateTimeString('microsecond'),
                'device_id'   => $data->device_id,
                'room_id'     => $data->room_id,
                'message_id'  => $data->message_id,
                'temperature' => $data->temperature->value,
                'co2'         => $data->co2->value,
            ];
        }

        if (! empty($rows)) {
            $inserted = Telemetry::insertOrIgnore($rows);

            $duplicates = count($rows) - $inserted;

            $metrics = app(PipelineMetrics::class);
            if ($inserted > 0) {
                $metrics->stored($inserted);
            }
            if ($duplicates > 0) {
                $metrics->duplicates($duplicates);
                StructuredLog::withContext([
                    'attempted'  => count($rows),
                    'inserted'   => $inserted,
                    'duplicates' => $duplicates,
                ])->warning(LogEvent::TelemetryDuplicates, 'Duplicate telemetry rows ignored');
            }
        }

        $this->reportLag();
    }

    private function reportLag(): void
    {
        $now = microtime(true);

        if ($this->lagWindowStartedAt === 0.0) {
            $this->lagWindowStartedAt = $now;
        }

        if ($this->lagCount === 0 || ($now - $this->lagWindowStartedAt) < 10.0) {
            return;
        }

        StructuredLog::withContext([
            'queue'    => $this->queue(),
            'count'    => $this->lagCount,
            'avgLagMs' => (int) round($this->lagSumMs / $this->lagCount),
            'maxLagMs' => $this->lagMaxMs,
        ])->info(LogEvent::TelemetryConsumerLag, 'Ingestion lag (enqueue → consolidation)');

        $this->lagWindowStartedAt = $now;
        $this->lagSumMs = 0;
        $this->lagMaxMs = 0;
        $this->lagCount = 0;
    }

    protected function queue(): string
    {
        return 'telemetry';
    }
}
