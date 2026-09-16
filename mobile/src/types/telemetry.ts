export type LatestTelemetry = {
  bucket: string;
  temperature?: number;
  min_temperature?: number;
  max_temperature?: number;
  co2?: number;
  min_co2?: number;
  max_co2?: number;
  samples?: number;
};

export type Device = {
  id: string;
  room_id: string;
  online: boolean;
  is_stale: boolean;
  ventilation: boolean;
  boot_id: string;
  last_seen_at: string;
  created_at: string;
  updated_at: string;
  latest_telemetry: LatestTelemetry | null;
};

export type DevicesResponse = {
  data: Device[];
};

export type TelemetryPoint = {
  bucket: string;
  temperature?: number;
  min_temperature?: number;
  max_temperature?: number;
  co2?: number;
  min_co2?: number;
  max_co2?: number;
  samples?: number;
};

export type TelemetryResponse = {
  data: TelemetryPoint[];
};

export type Room = {
  id: string;
  name: string;
};

export type MetricKey = "temperature" | "co2";

export type Metric = {
  key: MetricKey;
  name: string;
  unit: string;
};

export const AVAILABLE_METRICS: Metric[] = [
  {
    key: "temperature",
    name: "Température",
    unit: "°C",
  },
  {
    key: "co2",
    name: "CO₂",
    unit: "ppm",
  },
];
