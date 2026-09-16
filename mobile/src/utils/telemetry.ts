import { STALE_TELEMETRY_MS } from "@/constants/api";

/** Rounds a telemetry reading to one decimal place for display. */
export function roundMetricValue(value: number) {
  return Math.round(value * 10) / 10;
}

// La disponibilité réseau d'un objet et la fraîcheur de sa dernière mesure sont deux
// informations distinctes (un objet en pause reste `online` sans émettre de télémétrie).
export function isTelemetryStale(
  bucket: string | null,
  now: number = Date.now(),
) {
  if (!bucket) return false;
  return now - new Date(bucket).getTime() > STALE_TELEMETRY_MS;
}
