<?php

namespace App\Listeners;

use App\Events\CommandResultReceived;
use App\Logging\StructuredLog;
use App\Models\CommandResult;
use App\Models\Device;

class HandleCommandResult
{
    public function handle(CommandResultReceived $event): void
    {
        $r = $event->result;

        StructuredLog::withContext([
            'deviceId' => $r->device_id,
            'eventId' => $r->command_id,
            'topic' => sprintf('campus/v1/devices/%s/results', $r->device_id),
            'status' => $r->status,
            'reason' => $r->reason,
            'ventilation' => $r->ventilation,
            'executedAt' => $r->executed_at?->toIso8601String(),
            'reportedAt' => $r->reported_at?->toIso8601String(),
        ])->info('command_result.received', 'Command result received');

        Device::upsert(
            [['device_id' => $r->device_id]],
            ['device_id'],
            [],
        );

        CommandResult::updateOrCreate(
            ['command_id' => $r->command_id],
            [
                'device_id' => $r->device_id,
                'status' => $r->status,
                'ventilation' => $r->ventilation,
                'reason' => $r->reason,
                'executed_at' => $r->executed_at,
                'reported_at' => $r->reported_at,
            ],
        );
    }
}
