<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;
use RuntimeException;

class ClickHouseService
{
    private function url(): string
    {
        return sprintf('http://%s:%d', config('clickhouse.host'), config('clickhouse.port'));
    }

    private function client(): PendingRequest
    {
        return Http::withHeaders([
            'X-ClickHouse-User' => config('clickhouse.user'),
            'X-ClickHouse-Key'  => config('clickhouse.password'),
        ]);
    }

    /**
     * Insert a single row into telemetry_raw.
     * ClickHouse inserts are extremely fast — pas besoin de batching côté PHP
     * pour un POC; en prod on activerait async_insert=1 côté serveur.
     */
    public function insert(array $row): void
    {
        $url = $this->url() . '?query=' . rawurlencode('INSERT INTO telemetry_raw FORMAT JSONEachRow');

        $response = $this->client()
            ->withBody(json_encode($row), 'text/plain')
            ->post($url);

        if (!$response->successful()) {
            throw new RuntimeException('ClickHouse insert failed: ' . $response->body());
        }
    }

    /**
     * Execute a SELECT query with named parameters ({name:Type} syntax in SQL,
     * sent as param_name=value in the URL).
     * Returns an array of associative arrays (one per row).
     */
    public function select(string $sql, array $params = []): array
    {
        $qs = '';
        foreach ($params as $name => $value) {
            $qs .= '&param_' . rawurlencode($name) . '=' . rawurlencode((string) $value);
        }

        $url = $this->url() . ($qs ? '?' . ltrim($qs, '&') : '');

        $response = $this->client()
            ->withBody($sql . "\nFORMAT JSONEachRow", 'text/plain')
            ->post($url);

        if (!$response->successful()) {
            throw new RuntimeException('ClickHouse query failed: ' . $response->body());
        }

        $body = trim($response->body());
        if ($body === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            fn($line) => json_decode($line, true),
            explode("\n", $body)
        )));
    }

    /**
     * Latest telemetry per device in one batch query using argMax.
     * argMax(value, timestamp) → valeur au timestamp le plus récent.
     * Retourne un tableau indexé par device_id.
     */
    public function latestTelemetryForDevices(array $deviceIds): array
    {
        if (empty($deviceIds)) {
            return [];
        }

        $in = implode(',', array_map(fn($id) => "'" . addslashes($id) . "'", $deviceIds));

        $rows = $this->select("
            SELECT
                device_id,
                ROUND(argMax(temperature, observed_at) * 2) / 2.0      AS temperature,
                ROUND(argMax(co2, observed_at))::Int32                  AS co2,
                formatDateTime(max(observed_at), '%Y-%m-%dT%H:%i:%SZ') AS observed_at
            FROM telemetry_raw
            FINAL
            WHERE device_id IN ({$in})
            GROUP BY device_id
        ");

        return array_column($rows, null, 'device_id');
    }
}
