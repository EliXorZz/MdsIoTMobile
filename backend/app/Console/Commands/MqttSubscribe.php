<?php

namespace App\Console\Commands;

use App\Data\AvailabilityData;
use App\Data\CommandResultData;
use App\Data\DeviceStateData;
use App\Data\TelemetryData;
use App\Events\CommandResultReceived;
use App\Events\DeviceAvailabilityChanged;
use App\Events\DeviceStateReceived;
use App\Services\TelemetryIngestionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use PhpMqtt\Client\Facades\MQTT;

class MqttSubscribe extends Command
{
    protected $signature = 'mqtt:subscribe';

    protected $description = 'Subscribe to campus MQTT topics and dispatch Laravel events';

    private string $subscriberId;

    private int $prevReceived = 0;

    private float $lastStats = 0;

    public function __construct(private readonly TelemetryIngestionService $ingestion)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $this->subscriberId = gethostname().':'.getmypid();

        $mqtt = MQTT::connection();

        $mqtt->subscribe('campus/v1/devices/+/telemetry', $this->onTelemetry(...), 1);
        $mqtt->subscribe('campus/v1/devices/+/state', $this->onState(...), 1);
        $mqtt->subscribe('campus/v1/devices/+/availability', $this->onAvailability(...), 1);
        $mqtt->subscribe('campus/v1/devices/+/results', $this->onResult(...), 1);

        $this->lastStats = microtime(true);

        $mqtt->registerLoopEventHandler(function () {
            $this->ingestion->flushIfNeeded();

            if (microtime(true) - $this->lastStats >= 1.0) {
                $this->pushStats();
            }
        });

        $this->info('Listening on campus/v1/devices/+ …');

        $mqtt->loop(true);
        $this->ingestion->flush();

        $mqtt->disconnect();
    }

    private function pushStats(): void
    {
        $elapsed = microtime(true) - $this->lastStats;
        $this->lastStats = microtime(true);
        $rate = (int) (($this->ingestion->received() - $this->prevReceived) / $elapsed);
        $this->prevReceived = $this->ingestion->received();

        Redis::hSet('mqtt:subscribers', $this->subscriberId, json_encode([
            'id' => $this->subscriberId,
            'received' => $this->ingestion->received(),
            'rejected' => $this->ingestion->rejected(),
            'rate' => $rate,
            'db' => $this->ingestion->inserted(),
            'lag' => $this->ingestion->lag(),
            'updated_at' => microtime(true),
        ]));
    }

    private function onTelemetry(string $topic, string $message): void
    {
        $payload = json_decode($message, true);
        $deviceId = $this->deviceIdFromTopic($topic);

        if (! $payload || ($payload['device_id'] ?? null) !== $deviceId) {
            $this->warn("[$topic] dropped — mismatched or missing device_id");

            return;
        }

        try {
            $this->ingestion->push(TelemetryData::from($payload));
        } catch (\Throwable) {
            $this->ingestion->reject();
        }
    }

    private function onState(string $topic, string $message): void
    {
        DeviceStateReceived::dispatch(DeviceStateData::from(json_decode($message, true)));
    }

    private function onAvailability(string $topic, string $message): void
    {
        DeviceAvailabilityChanged::dispatch(AvailabilityData::from(json_decode($message, true)));
    }

    private function onResult(string $topic, string $message): void
    {
        CommandResultReceived::dispatch(CommandResultData::from(json_decode($message, true)));
    }

    private function deviceIdFromTopic(string $topic): string
    {
        return explode('/', $topic)[3] ?? '';
    }
}
