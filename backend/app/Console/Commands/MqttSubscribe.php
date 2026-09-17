<?php

namespace App\Console\Commands;

use App\Data\AvailabilityData;
use App\Data\CommandResultData;
use App\Data\DeviceStateData;
use App\Data\TelemetryData;
use App\Events\CommandResultReceived;
use App\Events\DeviceAvailabilityChanged;
use App\Events\DeviceStateReceived;
use App\Logging\StructuredLog;
use App\Services\TelemetryIngestionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use PhpMqtt\Client\ConnectionManager;
use PhpMqtt\Client\Exceptions\ClientNotConnectedToBrokerException;
use PhpMqtt\Client\Exceptions\ConnectingToBrokerFailedException;

class MqttSubscribe extends Command
{
    protected $signature = 'mqtt:subscribe';

    protected $description = 'Subscribe to campus MQTT topics and dispatch Laravel events';

    private const RECONNECT_DELAY_S = 3;

    private const MAX_TELEMETRY_AGE_S = 30;

    private const TOPICS = [
        'telemetry'    => 'campus/v1/devices/+/telemetry',
        'state'        => 'campus/v1/devices/+/state',
        'availability' => 'campus/v1/devices/+/availability',
        'results'      => 'campus/v1/devices/+/results',
    ];

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

        while (true) {
            try {
                $this->subscribeAndLoop();
            } catch (ConnectingToBrokerFailedException $e) {
                StructuredLog::withContext([
                    'subscriberId' => $this->subscriberId,
                    'reason'       => $e->getMessage(),
                    'status'       => 'disconnected',
                ])->error('mqtt.connection_failed', 'Cannot connect to the MQTT broker');
            } catch (ClientNotConnectedToBrokerException $e) {
                StructuredLog::withContext([
                    'subscriberId' => $this->subscriberId,
                    'reason'       => $e->getMessage(),
                    'status'       => 'disconnected',
                ])->error('mqtt.subscription_failed', 'Cannot subscribe to MQTT topics');
            } catch (\Throwable $e) {
                StructuredLog::withContext([
                    'subscriberId' => $this->subscriberId,
                    'reason'       => $e->getMessage(),
                    'status'       => 'disconnected',
                ])->error('mqtt.connection_lost', 'MQTT loop terminated unexpectedly');
            }

            $this->ingestion->flush();

            StructuredLog::withContext([
                'subscriberId' => $this->subscriberId,
                'status'       => 'reconnecting',
                'delayS'       => self::RECONNECT_DELAY_S,
            ])->debug('mqtt.reconnect_attempt', 'Scheduling next connection attempt');

            sleep(self::RECONNECT_DELAY_S);
        }
    }

    private function subscribeAndLoop(): void
    {
        $manager = app(ConnectionManager::class);
        $mqtt = $manager->connection();

        try {
            $mqtt->registerConnectedEventHandler(function ($client, bool $isAutoReconnect) {
                StructuredLog::withContext([
                    'subscriberId' => $this->subscriberId,
                    'status'       => $isAutoReconnect ? 'reconnected' : 'connected',
                ])->info('mqtt.connected', 'Connected to the MQTT broker');
            });

            $mqtt->registerLoopEventHandler(function () {
                $this->ingestion->flushIfNeeded();

                if (microtime(true) - $this->lastStats >= 1.0) {
                    $this->pushStats();
                }
            });

            foreach (self::TOPICS as $type => $topicFilter) {
                $mqtt->subscribe($topicFilter, $this->onMessage($type), 0);
            }

            $this->lastStats = microtime(true);

            StructuredLog::withContext([
                'subscriberId' => $this->subscriberId,
                'status'       => 'subscribed',
                'topics'       => array_values(self::TOPICS),
            ])->info('mqtt.subscribed', 'Subscribed to campus device topics');

            $mqtt->loop(true);
        } finally {
            try {
                $manager->disconnect();
            } catch (\Throwable) {
            }
        }
    }

    private function onMessage(string $type): callable
    {
        return fn (string $topic, string $message) => match ($type) {
            'telemetry'    => $this->onTelemetry($topic, $message),
            'state'        => $this->onState($topic, $message),
            'availability' => $this->onAvailability($topic, $message),
            'results'      => $this->onResult($topic, $message),
            default        => null,
        };
    }

    private function pushStats(): void
    {
        $elapsed = microtime(true) - $this->lastStats;
        $this->lastStats = microtime(true);
        $rate = (int) (($this->ingestion->received() - $this->prevReceived) / $elapsed);
        $this->prevReceived = $this->ingestion->received();

        Redis::hSet('mqtt:subscribers', $this->subscriberId, json_encode([
            'id'         => $this->subscriberId,
            'received'   => $this->ingestion->received(),
            'rejected'   => $this->ingestion->rejected(),
            'rate'       => $rate,
            'db'         => $this->ingestion->inserted(),
            'lag'        => $this->ingestion->lag(),
            'updated_at' => microtime(true),
        ]));
    }

    private function onTelemetry(string $topic, string $message): void
    {
        $payload = json_decode($message, true);
        $deviceId = $this->deviceIdFromTopic($topic);
        $eventId = is_array($payload) ? ($payload['message_id'] ?? null) : null;

        $log = StructuredLog::withContext([
            'deviceId' => $deviceId,
            'topic'    => $topic,
            'eventId'  => $eventId,
        ]);

        if (! is_array($payload)) {
            $this->ingestion->reject();
            $log->warning('telemetry.rejected', 'Malformed telemetry payload rejected', [
                'status' => 'rejected',
                'reason' => 'malformed_json',
            ]);

            return;
        }

        if (($payload['device_id'] ?? null) !== $deviceId) {
            $this->ingestion->reject();
            $log->warning('telemetry.topic_mismatch', 'Telemetry device_id does not match topic', [
                'status'          => 'rejected',
                'reason'          => 'device_id_mismatch',
                'payloadDeviceId' => $payload['device_id'] ?? null,
            ]);

            return;
        }

        try {
            $telemetry = TelemetryData::from($payload);

            $ageSeconds = now()->diffInSeconds($telemetry->observed_at, true);
            if ($ageSeconds > self::MAX_TELEMETRY_AGE_S) {
                $this->ingestion->reject();
                $log->warning('telemetry.stale', 'Telemetry message is too old and was rejected', [
                    'status'     => 'rejected',
                    'reason'     => 'stale',
                    'observedAt' => $telemetry->observed_at->toIso8601String(),
                    'ageSeconds' => $ageSeconds,
                ]);

                return;
            }

            $this->ingestion->push($telemetry);
        } catch (\Throwable $e) {
            $this->ingestion->reject();
            $log->warning('telemetry.rejected', 'Invalid telemetry payload rejected', [
                'status'          => 'rejected',
                'reason'          => 'validation_failed',
                'validationError' => $this->shortException($e),
            ]);
        }
    }

    private function onState(string $topic, string $message): void
    {
        $payload = json_decode($message, true);
        $log = StructuredLog::withContext([
            'deviceId' => $this->deviceIdFromTopic($topic),
            'topic'    => $topic,
        ]);

        try {
            DeviceStateReceived::dispatch(DeviceStateData::from($payload));
            $log->info('state.received', 'Device state received', ['status' => 'accepted']);
        } catch (\Throwable $e) {
            $log->warning('state.rejected', 'Invalid device state rejected', [
                'status'          => 'rejected',
                'reason'          => 'validation_failed',
                'validationError' => $this->shortException($e),
            ]);
        }
    }

    private function onAvailability(string $topic, string $message): void
    {
        $payload = json_decode($message, true);
        $log = StructuredLog::withContext([
            'deviceId' => $this->deviceIdFromTopic($topic),
            'topic'    => $topic,
        ]);

        try {
            $availability = AvailabilityData::from($payload);
            DeviceAvailabilityChanged::dispatch($availability);
            $log->info('availability.received', 'Device availability received', [
                'status'       => 'accepted',
                'onlineStatus' => $availability->status->value,
            ]);
        } catch (\Throwable $e) {
            $log->warning('availability.rejected', 'Invalid availability payload rejected', [
                'status'          => 'rejected',
                'reason'          => 'validation_failed',
                'validationError' => $this->shortException($e),
            ]);
        }
    }

    private function onResult(string $topic, string $message): void
    {
        $payload = json_decode($message, true);
        $eventId = is_array($payload) ? ($payload['command_id'] ?? null) : null;
        $log = StructuredLog::withContext([
            'deviceId' => $this->deviceIdFromTopic($topic),
            'topic'    => $topic,
            'eventId'  => $eventId,
        ]);

        try {
            $result = CommandResultData::from($payload);
            CommandResultReceived::dispatch($result);
            $log->info('command_result.received', 'Command result received', ['status' => $result->status]);
        } catch (\Throwable $e) {
            $log->warning('command_result.rejected', 'Invalid command result rejected', [
                'status'          => 'rejected',
                'reason'          => 'validation_failed',
                'validationError' => $this->shortException($e),
            ]);
        }
    }

    private function deviceIdFromTopic(string $topic): string
    {
        return explode('/', $topic)[3] ?? '';
    }

    private function shortException(\Throwable $e): string
    {
        return $e::class.': '.substr($e->getMessage(), 0, 120);
    }
}
