<?php

namespace App\Actions;

use App\Enums\CommandStatus;
use App\Logging\LogEvent;
use App\Logging\StructuredLog;
use App\Models\Command;
use App\Models\Device;
use App\Services\MqttPublisher;
use Illuminate\Support\Str;

class SendCommand
{
    private const TIMEOUT_SECONDS = 30;

    public function __construct(private readonly MqttPublisher $mqtt) {}

    public function execute(Device $device, string $action, array $params): Command
    {
        $commandId = (string) Str::uuid();
        $issuedAt = now();
        $expiresAt = $issuedAt->addSeconds(self::TIMEOUT_SECONDS);

        $command = Command::create([
            'command_id' => $commandId,
            'device_id' => $device->device_id,
            'action' => $action,
            'params' => $params,
            'status' => CommandStatus::Pending,
            'issued_at' => $issuedAt,
            'timeout_at' => $expiresAt,
        ]);

        StructuredLog::withContext([
            'commandId' => $commandId,
            'deviceId' => $device->device_id,
            'action' => $action,
            'status' => CommandStatus::Pending->value,
        ])->info(LogEvent::CommandIssued, 'Command persisted');

        $topic = sprintf('campus/v1/devices/%s/commands', $device->device_id);
        $payload = $this->buildPayload($commandId, $action, $params, $expiresAt);

        try {
            $this->mqtt->publish($topic, json_encode($payload));

            $command->update(['status' => CommandStatus::Sent, 'sent_at' => now()]);

            StructuredLog::withContext([
                'commandId' => $commandId,
                'deviceId' => $device->device_id,
                'action' => $action,
                'topic' => $topic,
                'status' => CommandStatus::Sent->value,
            ])->info(LogEvent::CommandSent, 'Command published to MQTT');
        } catch (\Throwable $e) {
            $command->update(['status' => CommandStatus::Failed]);

            StructuredLog::withContext([
                'commandId' => $commandId,
                'deviceId' => $device->device_id,
                'action' => $action,
                'reason' => $e->getMessage(),
                'status' => CommandStatus::Failed->value,
            ])->error(LogEvent::CommandSendFailed, 'Failed to publish command to MQTT');

            return $command->fresh();
        }

        return $command->fresh();
    }

    private function buildPayload(string $commandId, string $action, array $params, \DateTimeInterface $expiresAt): array
    {
        return array_merge(
            [
                'schema_version' => 1,
                'command_id' => $commandId,
                'action' => $action,
                'expires_at' => $expiresAt->format('Y-m-d\TH:i:s.v\Z'),
            ],
            $params,
        );
    }
}
