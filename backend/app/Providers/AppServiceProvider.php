<?php

namespace App\Providers;

use App\Events\CommandResultReceived;
use App\Events\DeviceAlertChanged;
use App\Events\DeviceAvailabilityChanged;
use App\Events\DeviceStateChanged;
use App\Listeners\BroadcastToSSE;
use App\Models\Device;
use App\Models\Telemetry;
use App\Prometheus\FixedLaravelCacheAdapter;
use App\Services\MqttPublisher;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use PhpAmqpLib\Channel\AMQPChannel;
use Prometheus\CollectorRegistry;
use Spatie\Prometheus\Facades\Prometheus;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MqttPublisher::class, function () {
            $conn = config('mqtt-client.connections.default');

            return new MqttPublisher(
                $conn['host'],
                (int) ($conn['port'] ?? 1883),
                $conn['connection_settings']['auth']['username'] ?? null,
                $conn['connection_settings']['auth']['password'] ?? null,
            );
        });

        $this->app->singleton(AMQPChannel::class, function () {
            /** @var RabbitMQQueue $queue */
            $queue = Queue::connection('rabbitmq');
            $channel = $queue->getChannel();

            return $channel;
        });

        $this->app->scoped(CollectorRegistry::class, function () {
            return new CollectorRegistry(
                new FixedLaravelCacheAdapter(Cache::store('redis')),
                false
            );
        });
    }

    public function boot(): void
    {
        Event::listen(DeviceStateChanged::class, BroadcastToSSE::class);
        Event::listen(DeviceAvailabilityChanged::class, BroadcastToSSE::class);
        Event::listen(DeviceAlertChanged::class, BroadcastToSSE::class);
        Event::listen(CommandResultReceived::class, BroadcastToSSE::class);

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

        Prometheus::addGauge('Devices total', fn () => Device::count(), 'devices_total');
        Prometheus::addGauge('Devices online', fn () => Device::where('online', true)->count(), 'devices_online');
        Prometheus::addGauge('Telemetry rows (last hour)', fn () => Telemetry::where('observed_at', '>=', now()->subHour())->count(), 'telemetry_last_hour');

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
