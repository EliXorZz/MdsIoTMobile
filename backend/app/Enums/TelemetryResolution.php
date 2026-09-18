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
        return 'telemetry_'.$this->value;
    }

    public function currentBucketStart(): Carbon
    {
        return match ($this) {
            self::OneMinute   => now()->floorMinutes(1),
            self::FiveMinutes => now()->floorMinutes(5),
            self::OneHour     => now()->floorHours(1),
            self::OneDay      => now()->startOfDay(),
        };
    }

    public static function forRange(?string $from, ?string $to): self
    {
        $hours = ($from ? Carbon::parse($from) : now()->subDay())
            ->diffInHours($to ? Carbon::parse($to) : now());

        return match (true) {
            $hours <= 12  => self::OneMinute,
            $hours <= 48  => self::FiveMinutes,
            $hours <= 168 => self::OneHour,
            default       => self::OneDay,
        };
    }
}
