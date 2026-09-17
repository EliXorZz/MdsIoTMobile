<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        // 1m — base depuis les données brutes (latest sensor value + short ranges)
        DB::statement("
            CREATE MATERIALIZED VIEW telemetry_1m
            WITH (timescaledb.continuous, timescaledb.materialized_only = true) AS
            SELECT
                time_bucket('1 minute', observed_at)                                   AS bucket,
                device_id,
                approx_percentile(0.5,  percentile_agg(temperature))::float            AS median_temperature,
                approx_percentile(0.05, percentile_agg(temperature))::float            AS min_temperature,
                approx_percentile(0.95, percentile_agg(temperature))::float            AS max_temperature,
                approx_percentile(0.5,  percentile_agg(co2::double precision))::float  AS median_co2,
                approx_percentile(0.05, percentile_agg(co2::double precision))::float  AS min_co2,
                approx_percentile(0.95, percentile_agg(co2::double precision))::float  AS max_co2,
                COUNT(*)::int                                                           AS samples
            FROM telemetry
            GROUP BY 1, 2
            WITH NO DATA
        ");

        // 5m — base depuis les données brutes
        // p5/p95 sur temperature et co2 pour ignorer les outliers (spikes capteur)
        DB::statement("
            CREATE MATERIALIZED VIEW telemetry_5m
            WITH (timescaledb.continuous, timescaledb.materialized_only = true) AS
            SELECT
                time_bucket('5 minutes', observed_at)                                  AS bucket,
                device_id,
                approx_percentile(0.5,  percentile_agg(temperature))::float            AS median_temperature,
                approx_percentile(0.05, percentile_agg(temperature))::float            AS min_temperature,
                approx_percentile(0.95, percentile_agg(temperature))::float            AS max_temperature,
                approx_percentile(0.5,  percentile_agg(co2::double precision))::float  AS median_co2,
                approx_percentile(0.05, percentile_agg(co2::double precision))::float  AS min_co2,
                approx_percentile(0.95, percentile_agg(co2::double precision))::float  AS max_co2,
                COUNT(*)::int                                                           AS samples
            FROM telemetry
            GROUP BY 1, 2
            WITH NO DATA
        ");

        // 1h — hierarchical depuis 5m (vue 7 derniers jours)
        DB::statement("
            CREATE MATERIALIZED VIEW telemetry_1h
            WITH (timescaledb.continuous, timescaledb.materialized_only = true) AS
            SELECT
                time_bucket('1 hour', bucket)                                         AS bucket,
                device_id,
                approx_percentile(0.5, percentile_agg(median_temperature))::float     AS median_temperature,
                MIN(min_temperature)::float                                            AS min_temperature,
                MAX(max_temperature)::float                                            AS max_temperature,
                approx_percentile(0.5, percentile_agg(median_co2))::float             AS median_co2,
                MIN(min_co2)::float                                                    AS min_co2,
                MAX(max_co2)::float                                                    AS max_co2,
                SUM(samples)::int                                                      AS samples
            FROM telemetry_5m
            GROUP BY 1, 2
            WITH NO DATA
        ");

        // 1d — hierarchical depuis 1h (vue mois / année)
        DB::statement("
            CREATE MATERIALIZED VIEW telemetry_1d
            WITH (timescaledb.continuous, timescaledb.materialized_only = true) AS
            SELECT
                time_bucket('1 day', bucket)                                          AS bucket,
                device_id,
                approx_percentile(0.5, percentile_agg(median_temperature))::float     AS median_temperature,
                MIN(min_temperature)::float                                            AS min_temperature,
                MAX(max_temperature)::float                                            AS max_temperature,
                approx_percentile(0.5, percentile_agg(median_co2))::float             AS median_co2,
                MIN(min_co2)::float                                                    AS min_co2,
                MAX(max_co2)::float                                                    AS max_co2,
                SUM(samples)::int                                                      AS samples
            FROM telemetry_1h
            GROUP BY 1, 2
            WITH NO DATA
        ");

        DB::statement("SELECT add_continuous_aggregate_policy('telemetry_1m',
            start_offset  => INTERVAL '6 hours',
            end_offset    => NULL,
            schedule_interval => INTERVAL '1 minute')");

        DB::statement("SELECT add_continuous_aggregate_policy('telemetry_5m',
            start_offset  => INTERVAL '1 day',
            end_offset    => INTERVAL '5 minutes',
            schedule_interval => INTERVAL '5 minutes')");

        DB::statement("SELECT add_continuous_aggregate_policy('telemetry_1h',
            start_offset  => INTERVAL '7 days',
            end_offset    => INTERVAL '1 hour',
            schedule_interval => INTERVAL '1 hour')");

        DB::statement("SELECT add_continuous_aggregate_policy('telemetry_1d',
            start_offset  => INTERVAL '1 year',
            end_offset    => INTERVAL '1 day',
            schedule_interval => INTERVAL '1 day')");

        DB::statement("CALL refresh_continuous_aggregate('telemetry_1m', NULL, NULL)");
        DB::statement("CALL refresh_continuous_aggregate('telemetry_5m', NULL, NULL)");
        DB::statement("CALL refresh_continuous_aggregate('telemetry_1h', NULL, NULL)");
        DB::statement("CALL refresh_continuous_aggregate('telemetry_1d', NULL, NULL)");
    }

    public function down(): void
    {
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS telemetry_1d CASCADE');
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS telemetry_1h CASCADE');
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS telemetry_5m CASCADE');
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS telemetry_1m CASCADE');
    }
};
