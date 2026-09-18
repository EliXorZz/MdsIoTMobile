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

    public function debug(LogEvent $event, string $message, array $context = []): void
    {
        $this->write('debug', $event, $message, $context);
    }

    public function info(LogEvent $event, string $message, array $context = []): void
    {
        $this->write('info', $event, $message, $context);
    }

    public function warning(LogEvent $event, string $message, array $context = []): void
    {
        $this->write('warning', $event, $message, $context);
    }

    public function error(LogEvent $event, string $message, array $context = []): void
    {
        $this->write('error', $event, $message, $context);
    }

    private function write(string $level, LogEvent $event, string $message, array $context): void
    {
        Log::{$level}($message, array_filter(
            ['eventType' => $event->value] + $context + $this->sharedContext,
            static fn ($value): bool => $value !== null,
        ));
    }
}
