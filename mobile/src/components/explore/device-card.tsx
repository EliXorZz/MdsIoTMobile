import { Pressable, StyleSheet, View } from "react-native";

import { ThemedText } from "@/components/themed-text";
import { ThemedView } from "@/components/themed-view";
import { POLLING_INTERVAL_MS } from "@/constants/api";
import { Radius, Spacing } from "@/constants/theme";
import { useTheme } from "@/hooks/use-theme";
import { useGetDeviceTelemetryQuery } from "@/store/api";
import type { Device, Metric } from "@/types/telemetry";
import { roundMetricValue } from "@/utils/telemetry";

import { cardShadow } from "./shared-styles";
import { TelemetryChart } from "./telemetry-chart";

type Props = {
  device: Device;
  metric: Metric;
  widthPercent: `${number}%`;
  onPress?: () => void;
};

export function DeviceCard({ device, metric, widthPercent, onPress }: Props) {
  const theme = useTheme();

  const { data: history = [] } = useGetDeviceTelemetryQuery(
    { deviceId: device.id },
    { pollingInterval: POLLING_INTERVAL_MS },
  );

  const latestPoint = history[history.length - 1] ?? null;
  const value = latestPoint
    ? (latestPoint[metric.key] ?? null)
    : (device.latest_telemetry?.[metric.key] ?? null);
  const bucket =
    latestPoint?.bucket ?? device.latest_telemetry?.bucket ?? null;
  const isOnline = device.online;
  const isStale = device.is_stale;

  return (
    <Pressable onPress={onPress} style={{ width: widthPercent }}>
    <ThemedView
      type="backgroundElement"
      style={[
        styles.card,
        cardShadow,
        { borderColor: theme.border },
      ]}
    >
      <ThemedView style={styles.header}>
        <ThemedView style={styles.headerInfo}>
          <ThemedText type="subtitle" style={styles.deviceId}>
            {device.id}
          </ThemedText>

          <ThemedText type="small" themeColor="textSecondary">
            {bucket ? new Date(bucket).toLocaleString() : "Aucune mesure"}
          </ThemedText>

          {isStale && (
            <ThemedText type="small" style={{ color: theme.warning }}>
              ⏳ Mesure ancienne
            </ThemedText>
          )}
        </ThemedView>

        <ThemedView type="backgroundSelected" style={styles.statusPill}>
          <View
            style={[
              styles.statusDot,
              { backgroundColor: isOnline ? theme.success : theme.danger },
            ]}
          />
          <ThemedText type="small">
            {isOnline ? "En ligne" : "Hors ligne"}
          </ThemedText>
        </ThemedView>
      </ThemedView>

      <ThemedText
        type="title"
        style={[styles.metricValue, { color: theme.tint }]}
      >
        {value !== null ? `${roundMetricValue(value)} ${metric.unit}` : "—"}
      </ThemedText>

      <TelemetryChart
        points={history}
        metricKey={metric.key}
        unit={metric.unit}
        color={theme.tint}
        gridColor={theme.border}
      />

      <ThemedView type="backgroundSelected" style={styles.ventilationBadge}>
        <ThemedText type="small" themeColor="textSecondary">
          Ventilation {device.ventilation ? "activée" : "désactivée"}
        </ThemedText>
      </ThemedView>
    </ThemedView>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  card: {
    padding: Spacing.four,
    borderRadius: Radius.large,
    borderWidth: 1,
    gap: Spacing.three,
    width: "100%",
  },

  header: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "flex-start",
    gap: Spacing.two,
  },

  headerInfo: {
    flex: 1,
    gap: Spacing.half,
  },

  deviceId: {
    fontSize: 20,
    lineHeight: 26,
  },

  statusPill: {
    flexDirection: "row",
    alignItems: "center",
    gap: Spacing.one,
    paddingHorizontal: Spacing.two,
    paddingVertical: Spacing.half,
    borderRadius: Radius.full,
  },

  statusDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
  },

  metricValue: {
    fontSize: 36,
    lineHeight: 40,
  },

  ventilationBadge: {
    alignSelf: "flex-start",
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.half,
    borderRadius: Radius.full,
  },
});
