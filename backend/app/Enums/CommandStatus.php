<?php

namespace App\Enums;

enum CommandStatus: string
{
    case Pending = 'PENDING';
    case Sent = 'SENT';
    case Acknowledged = 'ACKNOWLEDGED';
    case Failed = 'FAILED';
    case Timeout = 'TIMEOUT';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Acknowledged, self::Failed, self::Timeout => true,
            default => false,
        };
    }
}
