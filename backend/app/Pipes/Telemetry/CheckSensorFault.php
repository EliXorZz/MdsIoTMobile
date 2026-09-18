<?php

namespace App\Pipes\Telemetry;

use App\Data\TelemetryData;
use App\Logging\LogEvent;
use App\Logging\StructuredLog;
use Closure;

class CheckSensorFault
{
    private const THRESHOLDS = [
        'temperature' => ['min' => -30.0, 'max' => 70.0,   'unit' => '°C'],
        'co2' => ['min' => 100.0, 'max' => 5000.0, 'unit' => 'ppm'],
    ];

    public function handle(TelemetryData $telemetry, Closure $next): mixed
    {
        $log = StructuredLog::withContext([
            'eventId' => $telemetry->message_id,
            'deviceId' => $telemetry->device_id,
            'topic' => "campus/v1/devices/{$telemetry->device_id}/telemetry",
        ]);

        $values = [
            'temperature' => $telemetry->temperature->value,
            'co2' => $telemetry->co2->value,
        ];

        foreach (self::THRESHOLDS as $metric => ['min' => $min, 'max' => $max, 'unit' => $unit]) {
            $value = $values[$metric];

            if ($value < $min || $value > $max) {
                $log->warning(LogEvent::TelemetryAnomaly, 'Sensor value outside physical range', [
                    'status' => 'anomaly',
                    'metric' => $metric,
                    'value' => $value,
                    'unit' => $unit,
                    'min' => $min,
                    'max' => $max,
                ]);
            }
        }

        return $next($telemetry);
    }
}
