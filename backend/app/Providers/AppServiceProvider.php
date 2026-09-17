<?php

namespace App\Providers;

use App\Events\CommandResultReceived;
use App\Events\DeviceAlertChanged;
use App\Events\DeviceAvailabilityChanged;
use App\Events\DeviceStateReceived;
use App\Events\SSEEvent;
use App\Events\TelemetryBatchReceived;
use App\Listeners\BroadcastToSSE;
use App\Listeners\HandleCommandResult;
use App\Listeners\StoreTelemetryBatch;
use App\Listeners\UpdateDeviceAlert;
use App\Listeners\UpdateDeviceAvailability;
use App\Listeners\UpdateDeviceState;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Event::listen(CommandStarting::class, function (CommandStarting $event) {
            if (in_array($event->command, ['migrate:fresh', 'migrate:refresh', 'migrate:reset', 'db:wipe'])) {
                try {
                    DB::statement('DROP MATERIALIZED VIEW IF EXISTS telemetry_1d CASCADE');
                    DB::statement('DROP MATERIALIZED VIEW IF EXISTS telemetry_1h CASCADE');
                    DB::statement('DROP MATERIALIZED VIEW IF EXISTS telemetry_5m CASCADE');
                    DB::statement('DROP MATERIALIZED VIEW IF EXISTS telemetry_1m CASCADE');
                } catch (\Throwable) {
                }
            }
        });

        Event::listen(SSEEvent::class, BroadcastToSSE::class);

        Event::listen(DeviceStateReceived::class, UpdateDeviceState::class);
        Event::listen(DeviceAvailabilityChanged::class, UpdateDeviceAvailability::class);
        Event::listen(CommandResultReceived::class, HandleCommandResult::class);
        Event::listen(TelemetryBatchReceived::class, StoreTelemetryBatch::class);
        Event::listen(DeviceAlertChanged::class, UpdateDeviceAlert::class);

        JsonResource::macro('paginationInformation', function ($request, $paginated, $default) {
            return [
                'pagination' => [
                    'current_page' => $paginated['current_page'],
                    'last_page' => $paginated['last_page'],
                    'per_page' => $paginated['per_page'],
                    'total' => $paginated['total'],
                    'from' => $paginated['from'],
                    'to' => $paginated['to'],
                ],
            ];
        });
    }
}
