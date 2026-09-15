<?php

namespace App\Console\Commands;

use App\Data\AvailabilityData;
use App\Data\CommandResultData;
use App\Data\DeviceStateData;
use App\Data\TelemetryData;
use App\Events\CommandResultReceived;
use App\Events\DeviceAvailabilityChanged;
use App\Events\DeviceStateReceived;
use App\Events\TelemetryReceived;
use Illuminate\Console\Command;
use PhpMqtt\Client\Facades\MQTT;

class MqttSubscribe extends Command
{
    protected $signature = 'mqtt:subscribe';
    protected $description = 'Subscribe to campus MQTT topics and dispatch Laravel events';

    public function handle(): void
    {
        $mqtt = MQTT::connection();

        $mqtt->subscribe('campus/v1/devices/+/telemetry', $this->onTelemetry(...), 1);
        $mqtt->subscribe('campus/v1/devices/+/state', $this->onState(...), 1);
        $mqtt->subscribe('campus/v1/devices/+/availability', $this->onAvailability(...), 1);
        $mqtt->subscribe('campus/v1/devices/+/results', $this->onResult(...), 1);

        $this->info('Listening on campus/v1/devices/+ …');

        $mqtt->loop(true);
        $mqtt->disconnect();
    }

    private function onTelemetry(string $topic, string $message): void
    {
        $payload = json_decode($message, true);
        $deviceId = $this->deviceIdFromTopic($topic);

        if (! $payload || ($payload['device_id'] ?? null) !== $deviceId) {
            $this->warn("[$topic] dropped — mismatched or missing device_id");
            return;
        }

        TelemetryReceived::dispatch(TelemetryData::from($payload));
    }

    private function onState(string $topic, string $message): void
    {
        $payload = json_decode($message, true);
        DeviceStateReceived::dispatch(DeviceStateData::from($payload));
    }

    private function onAvailability(string $topic, string $message): void
    {
        $payload = json_decode($message, true);
        DeviceAvailabilityChanged::dispatch(AvailabilityData::from($payload));
    }

    private function onResult(string $topic, string $message): void
    {
        $payload = json_decode($message, true);
        CommandResultReceived::dispatch(CommandResultData::from($payload));
    }

    private function deviceIdFromTopic(string $topic): string
    {
        return explode('/', $topic)[3] ?? '';
    }
}
