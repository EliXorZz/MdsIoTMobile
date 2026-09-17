<?php

namespace App\Logging;

use Illuminate\Support\Facades\Log;

final class StructuredLog
{
    private function __construct(private readonly array $sharedContext = []) {}

    public static function withContext(array $context = []): self
    {
        return new self($context);
    }

    public function debug(string $eventType, string $message, array $context = []): void
    {
        $this->write('debug', $eventType, $message, $context);
    }

    public function info(string $eventType, string $message, array $context = []): void
    {
        $this->write('info', $eventType, $message, $context);
    }

    public function warning(string $eventType, string $message, array $context = []): void
    {
        $this->write('warning', $eventType, $message, $context);
    }

    public function error(string $eventType, string $message, array $context = []): void
    {
        $this->write('error', $eventType, $message, $context);
    }

    private function write(string $level, string $eventType, string $message, array $context): void
    {
        Log::{$level}($message, array_filter(
            ['eventType' => $eventType] + $context + $this->sharedContext,
            static fn ($value): bool => $value !== null,
        ));
    }
}
