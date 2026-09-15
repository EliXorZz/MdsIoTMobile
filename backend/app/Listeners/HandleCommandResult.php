<?php

namespace App\Listeners;

use App\Events\CommandResultReceived;
use App\Models\CommandResult;
use App\Models\Device;
use Illuminate\Support\Facades\Log;

class HandleCommandResult
{
    public function handle(CommandResultReceived $event): void
    {
        $r = $event->result;

        Log::info('mqtt.result', [
            'device_id'  => $r->device_id,
            'command_id' => $r->command_id,
            'status'     => $r->status,
            'reason'     => $r->reason,
        ]);

        Device::upsert(
            [['device_id' => $r->device_id]],
            ['device_id'],
            [],
        );

        CommandResult::updateOrCreate(
            ['command_id' => $r->command_id],
            [
                'device_id'   => $r->device_id,
                'status'      => $r->status,
                'ventilation' => $r->ventilation,
                'reason'      => $r->reason,
                'executed_at' => $r->executed_at,
                'reported_at' => $r->reported_at,
            ],
        );
    }
}
