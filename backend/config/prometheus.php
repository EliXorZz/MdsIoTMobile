<?php

use Spatie\Prometheus\Actions\RenderCollectorsAction;
use Spatie\Prometheus\Http\Middleware\AllowIps;

return [
    'enabled' => true,

    'urls' => [
        'default' => 'metrics',
    ],

    // Empty = allow all (Prometheus scrapes from within Docker network only)
    'allowed_ips' => [],

    'default_namespace' => 'app',

    'middleware' => [],

    'actions' => [
        'render_collectors' => RenderCollectorsAction::class,
    ],

    'wipe_storage_after_rendering' => false,

    // Redis keeps counters alive across process restarts and shared between
    // subscriber, worker, and backend containers.
    'cache' => 'redis',
];
