/** Rounds a telemetry reading to one decimal place for display. */
export function roundMetricValue(value: number) {
  return Math.round(value * 10) / 10;
}
