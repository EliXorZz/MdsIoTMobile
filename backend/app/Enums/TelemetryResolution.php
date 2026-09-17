<?php

namespace App\Enums;

use Carbon\Carbon;

enum TelemetryResolution: string
{
    case OneMinute = '1m';
    case FiveMinutes = '5m';
    case OneHour = '1h';
    case OneDay = '1d';

    public function view(): string
    {
        return match ($this) {
            self::OneMinute => 'telemetry_1m',
            self::FiveMinutes => 'telemetry_5m',
            self::OneHour => 'telemetry_1h',
            self::OneDay => 'telemetry_1d',
        };
    }

    public function currentBucketStart(): Carbon
    {
        return match ($this) {
            self::OneMinute => now()->startOfMinute(),
            self::FiveMinutes => now()->startOfHour()->addMinutes((int) floor(now()->minute / 5) * 5),
            self::OneHour => now()->startOfHour(),
            self::OneDay => now()->startOfDay(),
        };
    }

    public static function forRange(?string $from, ?string $to): self
    {
        $start = $from ? Carbon::parse($from) : now()->subDay();
        $end = $to ? Carbon::parse($to) : now();

        $hours = $start->diffInHours($end);
        $days = $start->diffInDays($end);

        return match (true) {
            $hours <= 12 => self::OneMinute,
            $days <= 2 => self::FiveMinutes,
            $days <= 7 => self::OneHour,
            default => self::OneDay,
        };
    }
}
