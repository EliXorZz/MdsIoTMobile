<?php

namespace App\Console\Commands;

use App\Jobs\StoreDatalakeTelemetry;
use Illuminate\Support\Facades\DB;

class MongoConsumer extends BatchConsumer
{
    protected $signature = 'datalake:consume
                            {--batch=500  : Number of messages per batch}
                            {--timeout=5  : Seconds before flushing a partial batch}';

    protected $description = 'Consume the datalake RabbitMQ queue in batches and store into MongoDB';

    protected function processBatch(array $messages): void
    {
        $docs = [];

        foreach ($messages as $msg) {
            $envelope = json_decode($msg->getBody(), true);
            /** @var StoreDatalakeTelemetry $job */
            $job = unserialize($envelope['data']['command']);

            $docs[] = [
                'topic' => $job->topic,
                'payload' => $job->payload,
                'enqueued_at' => $job->enqueuedAt->toIso8601String(),
            ];
        }

        DB::connection('mongodb')
            ->table('raw_telemetry')
            ->insert($docs);
    }

    protected function queue(): string
    {
        return 'datalake';
    }
}
