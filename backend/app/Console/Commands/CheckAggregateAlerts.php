<?php

namespace App\Console\Commands;

use App\Events\DeviceAlertChanged;
use App\Logging\StructuredLog;
use App\Models\Device;
use App\Models\TelemetryOneMinute;
use Illuminate\Console\Command;

class CheckAggregateAlerts extends Command
{
    protected $signature = 'alerts:check-aggregates';

    protected $description = 'Raise or resolve CO2 alerts from the aggregated telemetry metric';

    private const RAISE_THRESHOLD = 1500.0;

    private const RESOLVE_THRESHOLD = 1200.0;

    private const UNIT = 'ppm';

    public function handle(): int
    {
        $latestBuckets = TelemetryOneMinute::query()
            ->select('device_id', 'bucket', 'median_co2', 'samples')
            ->distinct('device_id')
            ->orderBy('device_id')
            ->orderByDesc('bucket')
            ->get();

        if ($latestBuckets->isEmpty()) {
            return self::SUCCESS;
        }

        $devices = Device::whereIn('device_id', $latestBuckets->pluck('device_id'))
            ->get()
            ->keyBy('device_id');

        foreach ($latestBuckets as $bucket) {
            $device = $devices[$bucket->device_id] ?? null;

            if ($device === null || (int) $bucket->samples < 1) {
                continue;
            }

            $co2 = (float) $bucket->median_co2;

            if (! $device->co2_alert && $co2 > self::RAISE_THRESHOLD) {
                $device->update(['co2_alert' => true]);

                StructuredLog::withContext([
                    'deviceId' => $device->device_id,
                    'bucket' => $bucket->bucket->toIso8601String(),
                ])->warning('sensor.alert_raised', 'CO2 metric exceeded health threshold', [
                    'metric' => 'co2',
                    'value' => $co2,
                    'unit' => self::UNIT,
                    'threshold' => self::RAISE_THRESHOLD,
                    'samples' => (int) $bucket->samples,
                    'status' => 'raised',
                ]);

                DeviceAlertChanged::dispatch(
                    $device->device_id,
                    $device->room_id,
                    'co2',
                    $co2,
                    self::UNIT,
                    self::RAISE_THRESHOLD,
                    'raised',
                );
            } elseif ($device->co2_alert && $co2 < self::RESOLVE_THRESHOLD) {
                $device->update(['co2_alert' => false]);

                StructuredLog::withContext([
                    'deviceId' => $device->device_id,
                    'bucket' => $bucket->bucket->toIso8601String(),
                ])->info('sensor.alert_resolved', 'CO2 metric returned below health threshold', [
                    'metric' => 'co2',
                    'value' => $co2,
                    'unit' => self::UNIT,
                    'threshold' => self::RESOLVE_THRESHOLD,
                    'samples' => (int) $bucket->samples,
                    'status' => 'resolved',
                ]);

                DeviceAlertChanged::dispatch(
                    $device->device_id,
                    $device->room_id,
                    'co2',
                    $co2,
                    self::UNIT,
                    self::RESOLVE_THRESHOLD,
                    'resolved',
                );
            }
        }

        return self::SUCCESS;
    }
}
