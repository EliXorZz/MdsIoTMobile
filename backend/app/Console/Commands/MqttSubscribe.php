<?php

namespace App\Console\Commands;

use App\Data\AvailabilityData;
use App\Data\CommandResultData;
use App\Data\DeviceStateData;
use App\Events\CommandResultReceived;
use App\Events\DeviceAvailabilityChanged;
use App\Events\DeviceStateReceived;
use App\Events\RawTelemetryReceived;
use App\Logging\LogEvent;
use App\Logging\StructuredLog;
use Illuminate\Console\Command;
use PhpMqtt\Client\ConnectionManager;
use PhpMqtt\Client\Exceptions\ClientNotConnectedToBrokerException;
use PhpMqtt\Client\Exceptions\ConnectingToBrokerFailedException;

class MqttSubscribe extends Command
{
    protected $signature = 'mqtt:subscribe';

    protected $description = 'Subscribe to campus MQTT topics and dispatch Laravel events';

    private const RECONNECT_DELAY_S = 3;

    private const TOPICS = [
        'telemetry' => 'campus/v1/devices/+/telemetry',
        'state' => 'campus/v1/devices/+/state',
        'availability' => 'campus/v1/devices/+/availability',
        'results' => 'campus/v1/devices/+/results',
    ];

    private string $subscriberId;

    private int $telemetryCount = 0;

    private ?float $lastTelemetryLogAt = null;

    public function handle(): void
    {
        $this->subscriberId = gethostname().':'.getmypid();

        while (true) {
            try {
                $this->subscribeAndLoop();
            } catch (ConnectingToBrokerFailedException $e) {
                StructuredLog::withContext([
                    'subscriberId' => $this->subscriberId,
                    'reason' => $e->getMessage(),
                    'status' => 'disconnected',
                ])->error(LogEvent::MqttConnectionFailed, 'Cannot connect to the MQTT broker');
            } catch (ClientNotConnectedToBrokerException $e) {
                StructuredLog::withContext([
                    'subscriberId' => $this->subscriberId,
                    'reason' => $e->getMessage(),
                    'status' => 'disconnected',
                ])->error(LogEvent::MqttSubscriptionFailed, 'Cannot subscribe to MQTT topics');
            } catch (\Throwable $e) {
                StructuredLog::withContext([
                    'subscriberId' => $this->subscriberId,
                    'reason' => $e->getMessage(),
                    'status' => 'disconnected',
                ])->error(LogEvent::MqttConnectionLost, 'MQTT loop terminated unexpectedly');
            }

            StructuredLog::withContext([
                'subscriberId' => $this->subscriberId,
                'status' => 'reconnecting',
                'delayS' => self::RECONNECT_DELAY_S,
            ])->debug(LogEvent::MqttReconnectAttempt, 'Scheduling next connection attempt');

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
                    'status' => $isAutoReconnect ? 'reconnected' : 'connected',
                ])->info(LogEvent::MqttConnected, 'Connected to the MQTT broker');
            });

            foreach (self::TOPICS as $type => $topicFilter) {
                $mqtt->subscribe($topicFilter, $this->onMessage($type), 1);
            }

            StructuredLog::withContext([
                'subscriberId' => $this->subscriberId,
                'status' => 'subscribed',
                'topics' => array_values(self::TOPICS),
            ])->info(LogEvent::MqttSubscribed, 'Subscribed to campus device topics');

            $mqtt->registerLoopEventHandler(function () {
                $now = microtime(true);
                $this->lastTelemetryLogAt ??= $now;
                if ($now - $this->lastTelemetryLogAt >= 10.0) {
                    StructuredLog::withContext([
                        'subscriberId' => $this->subscriberId,
                        'count' => $this->telemetryCount,
                    ])->info(LogEvent::TelemetryReceived, 'Telemetry throughput');
                    $this->lastTelemetryLogAt = $now;
                }
            });

            $mqtt->loop();
        } finally {
            try {
                $manager->disconnect();
            } catch (\Throwable) {
            }

            StructuredLog::withContext([
                'subscriberId' => $this->subscriberId,
                'count' => $this->telemetryCount,
            ])->info(LogEvent::TelemetryReceived, 'Subscriber disconnected — final count');
        }
    }

    private function onMessage(string $type): callable
    {
        return fn (string $topic, string $message) => match ($type) {
            'telemetry' => $this->onTelemetry($topic, $message),
            'state' => $this->onState($topic, $message),
            'availability' => $this->onAvailability($topic, $message),
            'results' => $this->onResult($topic, $message),
            default => null,
        };
    }

    private function onTelemetry(string $topic, string $message): void
    {
        RawTelemetryReceived::dispatch($topic, $message);
        $this->telemetryCount++;
    }

    private function onState(string $topic, string $message): void
    {
        $payload = json_decode($message, true);
        $log = StructuredLog::withContext([
            'deviceId' => $this->deviceIdFromTopic($topic),
            'topic' => $topic,
        ]);

        try {
            DeviceStateReceived::dispatch(DeviceStateData::from($payload));
            $log->info(LogEvent::StateReceived, 'Device state received', ['status' => 'accepted']);
        } catch (\Throwable $e) {
            $log->warning(LogEvent::StateRejected, 'Invalid device state rejected', [
                'status' => 'rejected',
                'reason' => 'validation_failed',
                'validationError' => $this->shortException($e),
            ]);
        }
    }

    private function onAvailability(string $topic, string $message): void
    {
        $payload = json_decode($message, true);
        $log = StructuredLog::withContext([
            'deviceId' => $this->deviceIdFromTopic($topic),
            'topic' => $topic,
        ]);

        try {
            $availability = AvailabilityData::from($payload);
            DeviceAvailabilityChanged::dispatch($availability);
            $log->info(LogEvent::AvailabilityReceived, 'Device availability received', [
                'status' => 'accepted',
                'onlineStatus' => $availability->status->value,
            ]);
        } catch (\Throwable $e) {
            $log->warning(LogEvent::AvailabilityRejected, 'Invalid availability payload rejected', [
                'status' => 'rejected',
                'reason' => 'validation_failed',
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
            'topic' => $topic,
            'eventId' => $eventId,
        ]);

        try {
            $result = CommandResultData::from($payload);
            CommandResultReceived::dispatch($result);

            $log->info(LogEvent::CommandResultReceived, 'Command result received', ['status' => $result->status]);
        } catch (\Throwable $e) {
            $log->warning(LogEvent::CommandResultRejected, 'Invalid command result rejected', [
                'status' => 'rejected',
                'reason' => 'validation_failed',
                'validationError' => $this->shortException($e),
            ]);
        }
    }

    private function deviceIdFromTopic(string $topic): string
    {
        return explode('/', $topic)[3] ?? 'unknown';
    }

    private function shortException(\Throwable $e): string
    {
        return $e::class.': '.substr($e->getMessage(), 0, 120);
    }
}
