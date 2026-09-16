<?php

return [
    'host'     => env('CLICKHOUSE_HOST', 'localhost'),
    'port'     => (int) env('CLICKHOUSE_PORT', 8123),
    'user'     => env('CLICKHOUSE_USER', 'default'),
    'password' => env('CLICKHOUSE_PASSWORD', ''),
];
