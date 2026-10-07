<?php

namespace App\Providers;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use PhpAmqpLib\Channel\AMQPChannel;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AMQPChannel::class, function () {
            /** @var RabbitMQQueue $queue */
            $queue = Queue::connection('rabbitmq');
            $channel = $queue->getChannel();
            return $channel;
        });
    }

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
