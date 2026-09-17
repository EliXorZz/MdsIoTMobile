<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TelemetrySeeder extends Seeder
{
    private const BATCH_SIZE = 1000;

    private const DAYS_BACK = 365;

    // Granularity tiers: insert fewer raw rows for old data since the
    // continuous aggregates only need enough points to fill each bucket.
    private const TIERS = [
        ['days' => 1,   'interval' => 30],    // last 24 h  → 30 s  (feeds telemetry_1m)
        ['days' => 7,   'interval' => 300],   // last 7 d   → 5 min (feeds telemetry_5m)
        ['days' => 365, 'interval' => 3600],  // rest       → 1 h   (feeds telemetry_1h/1d)
    ];

    private const DEVICES = [
        ['device_id' => 'sensor-001', 'room_id' => 'salle-203', 'temp_base' => 21.5, 'co2_base' => 480,  'co2_peak' => 1100],
        ['device_id' => 'sensor-002', 'room_id' => 'salle-204', 'temp_base' => 22.0, 'co2_base' => 520,  'co2_peak' => 1400],
        ['device_id' => 'sensor-003', 'room_id' => 'salle-205', 'temp_base' => 20.8, 'co2_base' => 450,  'co2_peak' => 950],
    ];

    public function run(): void
    {
        $this->upsertDevices();

        $end = now()->startOfMinute();

        foreach (self::DEVICES as $cfg) {
            $this->command->info("Inserting {$cfg['device_id']}…");
            $seq = 0;
            $this->insertDevice($cfg, $end, $seq);
        }

        $this->command->info('Refreshing continuous aggregates…');
        // The chain is hierarchical: 5m feeds 1h, 1h feeds 1d.
        // Each level must be refreshed over the full seeded range before the next.
        DB::statement("CALL refresh_continuous_aggregate('telemetry_1m',  NOW() - INTERVAL '2 days',   NOW())");
        DB::statement("CALL refresh_continuous_aggregate('telemetry_5m',  NOW() - INTERVAL '400 days', NOW())");
        DB::statement("CALL refresh_continuous_aggregate('telemetry_1h',  NOW() - INTERVAL '400 days', NOW())");
        DB::statement("CALL refresh_continuous_aggregate('telemetry_1d',  NOW() - INTERVAL '400 days', NOW())");

        $this->command->info('Done.');
    }

    private function upsertDevices(): void
    {
        foreach (self::DEVICES as $cfg) {
            DB::table('devices')->upsert(
                [
                    'device_id' => $cfg['device_id'],
                    'room_id' => $cfg['room_id'],
                    'ventilation' => false,
                    'online' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                ['device_id'],
                ['room_id'],
            );
        }
    }

    private function insertDevice(array $cfg, Carbon $end, int &$seq): void
    {
        // Walk tiers from most-recent to oldest so tier boundaries don't overlap.
        $tierEnd = $end->copy();

        foreach (self::TIERS as $tier) {
            $tierStart = $end->copy()->subDays($tier['days']);

            // Clamp to global start.
            $globalStart = $end->copy()->subDays(self::DAYS_BACK);
            if ($tierStart->lt($globalStart)) {
                $tierStart = $globalStart->copy();
            }

            // Skip tiers that would be entirely beyond the previous tier end.
            if ($tierStart->gte($tierEnd)) {
                continue;
            }

            $this->insertRange($cfg, $tierStart, $tierEnd, $tier['interval'], $seq);

            $tierEnd = $tierStart->copy();
        }
    }

    private function insertRange(
        array $cfg,
        Carbon $start,
        Carbon $end,
        int $intervalSeconds,
        int &$seq,
    ): void {
        $rows = [];
        $current = $start->copy();

        while ($current->lt($end)) {
            $rows[] = $this->makeRow($cfg, $current, $seq++);

            if (count($rows) >= self::BATCH_SIZE) {
                DB::table('telemetry')->insertOrIgnore($rows);
                $rows = [];
            }

            $current->addSeconds($intervalSeconds);
        }

        if (! empty($rows)) {
            DB::table('telemetry')->insertOrIgnore($rows);
        }
    }

    private function makeRow(array $cfg, Carbon $ts, int $seq): array
    {
        $hour = (int) $ts->format('G');
        $hourFraction = $hour + (int) $ts->format('i') / 60.0;

        // CO2 : Monte de 8h à 14h (heures de cours), retombe ensuite, plancher la nuit
        $schoolCurve = max(0.0, sin(($hourFraction - 6.0) * M_PI / 14.0));
        $co2 = $cfg['co2_base'] + ($cfg['co2_peak'] - $cfg['co2_base']) * $schoolCurve;
        $co2 += mt_rand(-80, 80);
        $co2 = (int) max(420, min(2500, $co2));

        // Température : légèrement plus chaude en début d'après-midi
        $tempCurve = max(0.0, sin(($hourFraction - 7.0) * M_PI / 16.0));
        $temperature = round($cfg['temp_base'] + 0.7 * $tempCurve + mt_rand(-30, 30) / 100.0, 2);

        return [
            'observed_at' => $ts->toIso8601String(),
            'device_id' => $cfg['device_id'],
            'room_id' => $cfg['room_id'],
            'message_id' => $cfg['device_id'].'-seed-'.$seq,
            'temperature' => $temperature,
            'co2' => $co2,
        ];
    }
}
