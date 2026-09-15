<?php

namespace App\Providers;

use App\Events\CommandResultReceived;
use App\Events\DeviceAvailabilityChanged;
use App\Events\DeviceStateReceived;
use App\Events\TelemetryReceived;
use App\Listeners\HandleCommandResult;
use App\Listeners\StoreTelemetry;
use App\Listeners\UpdateDeviceAvailability;
use App\Listeners\UpdateDeviceState;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Event::listen(TelemetryReceived::class, StoreTelemetry::class);
        Event::listen(DeviceStateReceived::class, UpdateDeviceState::class);
        Event::listen(DeviceAvailabilityChanged::class, UpdateDeviceAvailability::class);
        Event::listen(CommandResultReceived::class, HandleCommandResult::class);

        JsonResource::macro('paginationInformation', function ($request, $paginated, $default) {
            return [
                'pagination' => [
                    'current_page' => $paginated['current_page'],
                    'last_page'    => $paginated['last_page'],
                    'per_page'     => $paginated['per_page'],
                    'total'        => $paginated['total'],
                    'from'         => $paginated['from'],
                    'to'           => $paginated['to'],
                ],
            ];
        });
    }
}
