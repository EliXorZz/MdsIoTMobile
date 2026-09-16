-- ============================================================
-- Datalake brut: ReplacingMergeTree
-- Déduplication éventuelle par message_id via le moteur.
-- ORDER BY (device_id, observed_at, message_id) → efficacité
-- des requêtes par capteur + plage temporelle.
-- PARTITION BY mois pour les purges et compactions rapides.
-- ============================================================
CREATE TABLE IF NOT EXISTS telemetry_raw (
    message_id   String,
    device_id    LowCardinality(String),
    room_id      LowCardinality(String),
    observed_at  DateTime64(3, 'UTC'),
    temperature  Float32,
    co2          Float32,
    _inserted_at DateTime64(3, 'UTC') DEFAULT now64()
)
ENGINE = ReplacingMergeTree(_inserted_at)
ORDER BY (device_id, observed_at, message_id)
PARTITION BY toYYYYMM(observed_at);

-- ============================================================
-- Agrégats 1 minute
-- AggregatingMergeTree stocke des états partiels (quantileState)
-- fusionnés à la lecture (quantileMerge) → médiane exacte
-- sans stocker toutes les valeurs brutes.
-- ============================================================
CREATE TABLE IF NOT EXISTS telemetry_1m (
    device_id    LowCardinality(String),
    bucket       DateTime('UTC'),
    temperature  AggregateFunction(quantile(0.5), Float32),
    co2          AggregateFunction(quantile(0.5), Float32)
)
ENGINE = AggregatingMergeTree()
ORDER BY (device_id, bucket)
PARTITION BY toYYYYMM(bucket);

CREATE MATERIALIZED VIEW IF NOT EXISTS telemetry_1m_mv TO telemetry_1m AS
SELECT
    device_id,
    toStartOfInterval(observed_at, INTERVAL 1 MINUTE) AS bucket,
    quantileState(0.5)(temperature)                    AS temperature,
    quantileState(0.5)(co2)                            AS co2
FROM telemetry_raw
GROUP BY device_id, bucket;

-- ============================================================
-- Agrégats 5 minutes
-- ============================================================
CREATE TABLE IF NOT EXISTS telemetry_5m (
    device_id    LowCardinality(String),
    bucket       DateTime('UTC'),
    temperature  AggregateFunction(quantile(0.5), Float32),
    co2          AggregateFunction(quantile(0.5), Float32)
)
ENGINE = AggregatingMergeTree()
ORDER BY (device_id, bucket)
PARTITION BY toYYYYMM(bucket);

CREATE MATERIALIZED VIEW IF NOT EXISTS telemetry_5m_mv TO telemetry_5m AS
SELECT
    device_id,
    toStartOfInterval(observed_at, INTERVAL 5 MINUTE) AS bucket,
    quantileState(0.5)(temperature)                    AS temperature,
    quantileState(0.5)(co2)                            AS co2
FROM telemetry_raw
GROUP BY device_id, bucket;

-- ============================================================
-- Agrégats 30 minutes
-- ============================================================
CREATE TABLE IF NOT EXISTS telemetry_30m (
    device_id    LowCardinality(String),
    bucket       DateTime('UTC'),
    temperature  AggregateFunction(quantile(0.5), Float32),
    co2          AggregateFunction(quantile(0.5), Float32)
)
ENGINE = AggregatingMergeTree()
ORDER BY (device_id, bucket)
PARTITION BY toYYYYMM(bucket);

CREATE MATERIALIZED VIEW IF NOT EXISTS telemetry_30m_mv TO telemetry_30m AS
SELECT
    device_id,
    toStartOfInterval(observed_at, INTERVAL 30 MINUTE) AS bucket,
    quantileState(0.5)(temperature)                     AS temperature,
    quantileState(0.5)(co2)                             AS co2
FROM telemetry_raw
GROUP BY device_id, bucket;

-- ============================================================
-- Agrégats 1 heure
-- ============================================================
CREATE TABLE IF NOT EXISTS telemetry_1h (
    device_id    LowCardinality(String),
    bucket       DateTime('UTC'),
    temperature  AggregateFunction(quantile(0.5), Float32),
    co2          AggregateFunction(quantile(0.5), Float32)
)
ENGINE = AggregatingMergeTree()
ORDER BY (device_id, bucket)
PARTITION BY toYYYYMM(bucket);

CREATE MATERIALIZED VIEW IF NOT EXISTS telemetry_1h_mv TO telemetry_1h AS
SELECT
    device_id,
    toStartOfInterval(observed_at, INTERVAL 1 HOUR) AS bucket,
    quantileState(0.5)(temperature)                  AS temperature,
    quantileState(0.5)(co2)                          AS co2
FROM telemetry_raw
GROUP BY device_id, bucket;

-- ============================================================
-- Agrégats 1 jour
-- ============================================================
CREATE TABLE IF NOT EXISTS telemetry_1d (
    device_id    LowCardinality(String),
    bucket       DateTime('UTC'),
    temperature  AggregateFunction(quantile(0.5), Float32),
    co2          AggregateFunction(quantile(0.5), Float32)
)
ENGINE = AggregatingMergeTree()
ORDER BY (device_id, bucket)
PARTITION BY toYYYYMM(bucket);

CREATE MATERIALIZED VIEW IF NOT EXISTS telemetry_1d_mv TO telemetry_1d AS
SELECT
    device_id,
    toStartOfInterval(observed_at, INTERVAL 1 DAY) AS bucket,
    quantileState(0.5)(temperature)                 AS temperature,
    quantileState(0.5)(co2)                         AS co2
FROM telemetry_raw
GROUP BY device_id, bucket;
