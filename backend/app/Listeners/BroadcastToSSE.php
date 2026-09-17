<?php

namespace App\Listeners;

use App\Events\SSEEvent;
use Illuminate\Support\Facades\Redis;

class BroadcastToSSE
{
    public function handle(SSEEvent $event): void
    {
        Redis::publish('sse', json_encode([
            'type' => $event->sseType(),
            ...$event->ssePayload(),
        ]));
    }
}
