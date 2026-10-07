<?php

namespace App\Listeners;

use App\Enums\CommandStatus;
use App\Enums\DeviceResultStatus;
use App\Events\CommandResultReceived;
use App\Logging\LogEvent;
use App\Logging\StructuredLog;
use App\Models\Command;
use App\Models\CommandResult;
use App\Models\Device;

class HandleCommandResult
{
    public function handle(CommandResultReceived $event): void
    {
        $r = $event->result;

        StructuredLog::withContext([
            'commandId' => $r->command_id,
            'deviceId' => $r->device_id,
            'topic' => sprintf('campus/v1/devices/%s/results', $r->device_id),
            'status' => $r->status->value,
            'reason' => $r->reason,
            'ventilation' => $r->ventilation,
            'executedAt' => $r->executed_at?->toIso8601String(),
            'reportedAt' => $r->reported_at?->toIso8601String(),
        ])->info(LogEvent::CommandResultReceived, 'Command result received');

        Device::upsert(
            [['device_id' => $r->device_id]],
            ['device_id'],
            [],
        );

        $alreadyStored = CommandResult::where('command_id', $r->command_id)->exists();

        if ($alreadyStored) {
            StructuredLog::withContext([
                'commandId' => $r->command_id,
                'deviceId' => $r->device_id,
            ])->warning(LogEvent::CommandDuplicate, 'Duplicate command result ignored');

            return;
        }

        CommandResult::create([
            'device_id' => $r->device_id,
            'command_id' => $r->command_id,
            'status' => $r->status->value,
            'ventilation' => $r->ventilation,
            'reason' => $r->reason,
            'executed_at' => $r->executed_at,
            'reported_at' => $r->reported_at,
        ]);

        $this->updateCommandLifecycle($r->command_id, $r->status);
    }

    private function updateCommandLifecycle(string $commandId, DeviceResultStatus $deviceStatus): void
    {
        $command = Command::find($commandId);

        if (! $command) {
            return;
        }

        if ($command->status->isTerminal()) {
            StructuredLog::withContext([
                'commandId' => $commandId,
                'deviceId' => $command->device_id,
                'current' => $command->status->value,
            ])->warning(LogEvent::CommandLateAck, 'ACK received after terminal status');

            return;
        }

        $newStatus = $deviceStatus === DeviceResultStatus::Executed ? CommandStatus::Acknowledged : CommandStatus::Failed;

        $command->update(['status' => $newStatus, 'acked_at' => now()]);

        $logEvent = $newStatus === CommandStatus::Acknowledged
            ? LogEvent::CommandAcknowledged
            : LogEvent::CommandFailed;

        StructuredLog::withContext([
            'commandId' => $commandId,
            'deviceId' => $command->device_id,
            'action' => $command->action,
            'status' => $newStatus->value,
        ])->info($logEvent, 'Command lifecycle updated from ACK');
    }
}
