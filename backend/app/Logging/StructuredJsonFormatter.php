<?php

namespace App\Logging;

use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;

/**
 * Emits one JSON object per line with context fields at the top level.
 * Compatible with Loki / Grafana label extraction.
 */
class StructuredJsonFormatter implements FormatterInterface
{
    public function format(LogRecord $record): string
    {
        $entry = [
            'timestamp' => $record->datetime->format(\DateTimeInterface::RFC3339_EXTENDED),
            'service' => 'backend',
            'level' => strtolower($record->level->name),
            'message' => $record->message,
        ];

        // Flatten context so eventType, deviceId, etc. are top-level Loki labels.
        foreach ($record->context as $key => $value) {
            $entry[$key] = $value;
        }

        return json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
    }

    public function formatBatch(array $records): string
    {
        return implode('', array_map($this->format(...), $records));
    }
}
