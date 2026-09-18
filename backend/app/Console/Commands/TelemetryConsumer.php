<?php

namespace App\Console\Commands;

use App\Data\TelemetryData;
use App\Jobs\ProcessTelemetry;
use App\Logging\LogEvent;
use App\Logging\StructuredLog;
use App\Models\Telemetry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TelemetryConsumer extends BatchConsumer
{
    protected $signature = 'telemetry:consume
                            {--batch=2000  : Number of messages per batch}
                            {--timeout=5   : Seconds before flushing a partial batch}
                            {--max-age=300 : Maximum message age in seconds before dropping it (0 = disabled)}';

    protected $description = 'Consume the telemetry RabbitMQ queue in batches';

    protected function processBatch(array $messages): void
    {
        $rows = [];
        $maxAge = (int) $this->option('max-age');
        $now = Carbon::now();
        $maxLagMs = 0;

        foreach ($messages as $msg) {
            $envelope = json_decode($msg->getBody(), true);
            /** @var ProcessTelemetry $job */
            $job = unserialize($envelope['data']['command']);

            try {
                $data = TelemetryData::from(json_decode($job->payload, true));
            } catch (\Throwable $e) {
                StructuredLog::withContext([
                    'topic' => $job->topic,
                    'payload' => substr($job->payload, 0, 200),
                    'reason' => $e->getMessage(),
                ])->error(LogEvent::TelemetryInvalidPayload, 'Invalid payload, message dropped');

                continue;
            }

            $rows[] = [
                'observed_at' => $data->observed_at->toDateTimeString('microsecond'),
                'device_id' => $data->device_id,
                'room_id' => $data->room_id,
                'message_id' => $data->message_id,
                'temperature' => $data->temperature->value,
                'co2' => $data->co2->value,
            ];
        }

        if (! empty($rows)) {
            DB::transaction(static function () use ($rows): void {
                DB::statement('SET LOCAL synchronous_commit TO OFF');
                Telemetry::insertOrIgnore($rows);
            });
        }
    }

    protected function queue(): string
    {
        return 'telemetry';
    }
}
