<?php

namespace App\Events;

interface SSEEvent
{
    public function sseType(): string;

    public function ssePayload(): array;
}
