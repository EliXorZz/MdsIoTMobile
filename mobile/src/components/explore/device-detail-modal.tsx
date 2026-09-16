import { useMemo, useState } from "react";
import {
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { ThemedText } from "@/components/themed-text";
import { ThemedView } from "@/components/themed-view";
import { POLLING_INTERVAL_MS } from "@/constants/api";
import { Radius, Spacing } from "@/constants/theme";
import { useTheme } from "@/hooks/use-theme";
import { useGetDeviceTelemetryQuery } from "@/store/api";
import { AVAILABLE_METRICS, type Device, type Metric } from "@/types/telemetry";
import { roundMetricValue } from "@/utils/telemetry";

import { TelemetryChart } from "./telemetry-chart";

type Range = "2h" | "24h" | "7j" | "30j";

const RANGES: { key: Range; label: string }[] = [
  { key: "2h", label: "2h" },
  { key: "24h", label: "24h" },
  { key: "7j", label: "7 jours" },
  { key: "30j", label: "30 jours" },
];

function computeFrom(range: Range): string {
  const now = new Date();
  switch (range) {
    case "2h": {
      const d = new Date(now.getTime() - 2 * 60 * 60 * 1000);
      d.setSeconds(0, 0);
      return d.toISOString();
    }
    case "24h": {
      const d = new Date(now.getTime() - 24 * 60 * 60 * 1000);
      d.setMinutes(0, 0, 0);
      return d.toISOString();
    }
    case "7j": {
      const d = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
      d.setHours(0, 0, 0, 0);
      return d.toISOString();
    }
    case "30j": {
      const d = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000);
      d.setHours(0, 0, 0, 0);
      return d.toISOString();
    }
  }
}

type Props = {
  device: Device | null;
  onClose: () => void;
};

export function DeviceDetailModal({ device, onClose }: Props) {
  const theme = useTheme();
  const insets = useSafeAreaInsets();
  const [range, setRange] = useState<Range>("2h");
  const [metric, setMetric] = useState<Metric>(AVAILABLE_METRICS[0]);

  const from = useMemo(() => computeFrom(range), [range]);

  const { data: history = [] } = useGetDeviceTelemetryQuery(
    { deviceId: device?.id ?? "", from },
    { pollingInterval: POLLING_INTERVAL_MS, skip: !device },
  );

  if (!device) return null;

  const latestPoint = history[history.length - 1] ?? null;
  const temp =
    latestPoint?.temperature ?? device.latest_telemetry?.temperature ?? null;
  const co2 =
    latestPoint?.co2 ?? device.latest_telemetry?.co2 ?? null;

  return (
    <Modal
      visible
      animationType="slide"
      onRequestClose={onClose}
      presentationStyle="pageSheet"
    >
      <ThemedView style={[styles.root, { backgroundColor: theme.background }]}>
        {/* Header */}
        <ThemedView
          style={[
            styles.header,
            {
              paddingTop: insets.top + Spacing.two,
              borderBottomColor: theme.border,
            },
          ]}
        >
          <ThemedText type="subtitle">{device.id}</ThemedText>
          <Pressable
            onPress={onClose}
            style={[styles.closeBtn, { backgroundColor: theme.backgroundSelected }]}
          >
            <ThemedText type="small">✕</ThemedText>
          </Pressable>
        </ThemedView>

        <ScrollView
          contentContainerStyle={[
            styles.content,
            { paddingBottom: insets.bottom + Spacing.six },
          ]}
        >
          {/* Status pills */}
          <ThemedView style={styles.row}>
            <ThemedView type="backgroundSelected" style={styles.pill}>
              <View
                style={[
                  styles.dot,
                  {
                    backgroundColor: device.online
                      ? theme.success
                      : theme.danger,
                  },
                ]}
              />
              <ThemedText type="small">
                {device.online ? "En ligne" : "Hors ligne"}
              </ThemedText>
            </ThemedView>

            <ThemedView type="backgroundSelected" style={styles.pill}>
              <ThemedText type="small" themeColor="textSecondary">
                Salle {device.room_id}
              </ThemedText>
            </ThemedView>

            <ThemedView type="backgroundSelected" style={styles.pill}>
              <ThemedText type="small" themeColor="textSecondary">
                Ventilation {device.ventilation ? "activée" : "désactivée"}
              </ThemedText>
            </ThemedView>

            {device.is_stale && (
              <ThemedView
                style={[
                  styles.pill,
                  { borderColor: theme.warning, borderWidth: 1 },
                ]}
              >
                <ThemedText type="small" style={{ color: theme.warning }}>
                  ⏳ Mesure ancienne
                </ThemedText>
              </ThemedView>
            )}
          </ThemedView>

          {/* Current values */}
          <ThemedView style={styles.valuesRow}>
            <ThemedView
              type="backgroundElement"
              style={[styles.valueCard, { borderColor: theme.border }]}
            >
              <ThemedText type="small" themeColor="textSecondary">
                Température
              </ThemedText>
              <ThemedText
                type="title"
                style={{ color: theme.tint, fontSize: 36 }}
              >
                {temp !== null ? `${roundMetricValue(temp)} °C` : "—"}
              </ThemedText>
              {latestPoint?.min_temperature != null &&
                latestPoint?.max_temperature != null && (
                  <ThemedText type="small" themeColor="textSecondary">
                    {roundMetricValue(latestPoint.min_temperature)} –{" "}
                    {roundMetricValue(latestPoint.max_temperature)} °C
                  </ThemedText>
                )}
            </ThemedView>

            <ThemedView
              type="backgroundElement"
              style={[styles.valueCard, { borderColor: theme.border }]}
            >
              <ThemedText type="small" themeColor="textSecondary">
                CO₂
              </ThemedText>
              <ThemedText
                type="title"
                style={{ color: theme.tint, fontSize: 36 }}
              >
                {co2 !== null ? `${Math.round(co2)} ppm` : "—"}
              </ThemedText>
              {latestPoint?.min_co2 != null && latestPoint?.max_co2 != null && (
                <ThemedText type="small" themeColor="textSecondary">
                  {Math.round(latestPoint.min_co2)} –{" "}
                  {Math.round(latestPoint.max_co2)} ppm
                </ThemedText>
              )}
            </ThemedView>
          </ThemedView>

          {/* Metric selector */}
          <ThemedView style={styles.selectorRow}>
            {AVAILABLE_METRICS.map((m) => (
              <Pressable
                key={m.key}
                onPress={() => setMetric(m)}
                style={[
                  styles.selectorTab,
                  {
                    backgroundColor:
                      metric.key === m.key ? theme.tint : theme.backgroundElement,
                  },
                ]}
              >
                <ThemedText
                  type="small"
                  style={{
                    color:
                      metric.key === m.key ? theme.onTint : theme.textSecondary,
                  }}
                >
                  {m.name}
                </ThemedText>
              </Pressable>
            ))}
          </ThemedView>

          {/* Range selector */}
          <ThemedView style={styles.selectorRow}>
            {RANGES.map((r) => (
              <Pressable
                key={r.key}
                onPress={() => setRange(r.key)}
                style={[
                  styles.selectorTab,
                  {
                    backgroundColor:
                      range === r.key
                        ? theme.backgroundSelected
                        : theme.backgroundElement,
                  },
                ]}
              >
                <ThemedText
                  type="small"
                  style={{
                    color: range === r.key ? theme.text : theme.textSecondary,
                  }}
                >
                  {r.label}
                </ThemedText>
              </Pressable>
            ))}
          </ThemedView>

          {/* Chart */}
          <ThemedView
            type="backgroundElement"
            style={[styles.chartCard, { borderColor: theme.border }]}
          >
            <TelemetryChart
              points={history}
              metricKey={metric.key}
              unit={metric.unit}
              color={theme.tint}
              gridColor={theme.border}
              height={220}
            />
          </ThemedView>

          {/* Device info */}
          <ThemedView
            type="backgroundElement"
            style={[styles.infoCard, { borderColor: theme.border }]}
          >
            {device.last_seen_at && (
              <ThemedView style={styles.infoRow}>
                <ThemedText type="small" themeColor="textSecondary">
                  Dernière activité
                </ThemedText>
                <ThemedText type="small">
                  {new Date(device.last_seen_at).toLocaleString()}
                </ThemedText>
              </ThemedView>
            )}
            <ThemedView style={styles.infoRow}>
              <ThemedText type="small" themeColor="textSecondary">
                Boot ID
              </ThemedText>
              <ThemedText type="small">{device.boot_id}</ThemedText>
            </ThemedView>
            {latestPoint?.samples != null && (
              <ThemedView style={styles.infoRow}>
                <ThemedText type="small" themeColor="textSecondary">
                  Échantillons (dernier bucket)
                </ThemedText>
                <ThemedText type="small">{latestPoint.samples}</ThemedText>
              </ThemedView>
            )}
          </ThemedView>
        </ScrollView>
      </ThemedView>
    </Modal>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
  },

  header: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingHorizontal: Spacing.four,
    paddingBottom: Spacing.three,
    borderBottomWidth: 1,
  },

  closeBtn: {
    width: 32,
    height: 32,
    borderRadius: 16,
    alignItems: "center",
    justifyContent: "center",
  },

  content: {
    padding: Spacing.four,
    gap: Spacing.three,
  },

  row: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: Spacing.two,
  },

  pill: {
    flexDirection: "row",
    alignItems: "center",
    gap: Spacing.one,
    paddingHorizontal: Spacing.two,
    paddingVertical: Spacing.half,
    borderRadius: Radius.full,
  },

  dot: {
    width: 8,
    height: 8,
    borderRadius: 4,
  },

  valuesRow: {
    flexDirection: "row",
    gap: Spacing.two,
  },

  valueCard: {
    flex: 1,
    padding: Spacing.three,
    borderRadius: Radius.large,
    borderWidth: 1,
    gap: Spacing.half,
  },

  selectorRow: {
    flexDirection: "row",
    gap: Spacing.two,
  },

  selectorTab: {
    flex: 1,
    paddingVertical: Spacing.two,
    borderRadius: Radius.medium,
    alignItems: "center",
  },

  chartCard: {
    padding: Spacing.three,
    borderRadius: Radius.large,
    borderWidth: 1,
  },

  infoCard: {
    padding: Spacing.three,
    borderRadius: Radius.large,
    borderWidth: 1,
    gap: Spacing.two,
  },

  infoRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    gap: Spacing.two,
  },
});
