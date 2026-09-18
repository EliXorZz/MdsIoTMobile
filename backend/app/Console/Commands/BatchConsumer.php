<?php

namespace App\Console\Commands;

use App\Logging\LogEvent;
use App\Logging\StructuredLog;
use Illuminate\Console\Command;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;

abstract class BatchConsumer extends Command
{
    protected $signature = 'batch:consume
                            {--batch=100  : Number of messages per batch}
                            {--timeout=5  : Seconds before flushing a partial batch}';

    private bool $running = true;

    /** @var AMQPMessage[] */
    private array $batch = [];

    private float $batchStartedAt = 0.0;

    private int $totalReceived = 0;

    private ?float $lastLoggedAt = null;

    private string $workerId = '';

    private AMQPChannel $channel;

    public function handle(AMQPChannel $channel): void
    {
        $this->channel = $channel;
        $this->workerId = gethostname().':'.getmypid();

        $batchSize = (int) $this->option('batch');
        $timeout = (int) $this->option('timeout');

        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, fn () => $this->running = false);
        pcntl_signal(SIGINT, fn () => $this->running = false);

        StructuredLog::withContext(['queue' => $this->queue(), 'batch_size' => $batchSize, 'timeout' => $timeout])
            ->info(LogEvent::BatchListening, 'Listening on RabbitMQ queue');

        $this->channel->queue_declare($this->queue(), passive: false, durable: true, exclusive: false, auto_delete: false);

        $this->channel->basic_qos(prefetch_size: 0, prefetch_count: $batchSize, a_global: false);

        $this->channel->basic_consume(
            queue: $this->queue(),
            callback: function (AMQPMessage $msg) use ($batchSize, $timeout) {
                if (empty($this->batch)) {
                    $this->batchStartedAt = microtime(true);
                }

                $this->batch[] = $msg;
                $this->totalReceived++;

                if (
                    count($this->batch) >= $batchSize ||
                    (microtime(true) - $this->batchStartedAt) >= $timeout
                ) {
                    $this->flush();
                }
            },
        );

        while ($this->running && $this->channel->is_consuming()) {
            try {
                $this->channel->wait(null, non_blocking: false, timeout: 0.2);
            } catch (AMQPTimeoutException) {
                // idle tick — no messages within 200ms
            }

            if (! empty($this->batch) && (microtime(true) - $this->batchStartedAt) >= $timeout) {
                $this->flush();
            }

            $now = microtime(true);
            $this->lastLoggedAt ??= $now;
            if ($now - $this->lastLoggedAt >= 10.0) {
                StructuredLog::withContext([
                    'workerId' => $this->workerId,
                    'queue' => $this->queue(),
                    'count' => $this->totalReceived,
                ])->info(LogEvent::BatchThroughput, 'Consumer throughput');
                $this->lastLoggedAt = $now;
            }

        }

        if (! empty($this->batch)) {
            $this->flush();
        }

        StructuredLog::withContext([
            'workerId' => $this->workerId,
            'queue' => $this->queue(),
            'count' => $this->totalReceived,
        ])->info(LogEvent::BatchThroughput, 'Consumer shutting down — final count');
    }

    private function flush(): void
    {
        $batch = $this->batch;
        $this->batch = [];

        try {
            $this->processBatch($batch);

            end($batch)->ack(multiple: true);

        } catch (\Throwable $e) {
            end($batch)->nack(requeue: true, multiple: true);

            StructuredLog::withContext(['queue' => $this->queue(), 'count' => count($batch), 'reason' => $e->getMessage()])
                ->error(LogEvent::BatchNacked, 'Batch failed, requeued');
        }
    }

    abstract protected function queue(): string;

    /** @param AMQPMessage[] $messages */
    abstract protected function processBatch(array $messages): void;
}
