import { StyleSheet, View } from "react-native";

import { ThemedText } from "@/components/themed-text";
import { ThemedView } from "@/components/themed-view";
import {
  POLLING_INTERVAL_MS,
  TELEMETRY_HISTORY_TIME,
  TELEMETRY_HISTORY_SIZE,
} from "@/constants/api";
import { Radius, Spacing } from "@/constants/theme";
import { useTheme } from "@/hooks/use-theme";
import { useGetDeviceTelemetryQuery } from "@/store/api";
import type { Device, Metric } from "@/types/telemetry";
import { isTelemetryStale, roundMetricValue } from "@/utils/telemetry";

import { cardShadow } from "./shared-styles";
import { TelemetryChart } from "./telemetry-chart";

type Props = {
  device: Device;
  metric: Metric;
  widthPercent: `${number}%`;
};

export function DeviceCard({ device, metric, widthPercent }: Props) {
  const theme = useTheme();

  const { data: history = [] } = useGetDeviceTelemetryQuery(
    {
      deviceId: device.id,
      perPage: TELEMETRY_HISTORY_SIZE,
      bucket: TELEMETRY_HISTORY_TIME,
    },
    { pollingInterval: POLLING_INTERVAL_MS },
  );

  // Dérivées du même historique que le graphique pour rester synchronisées avec le dernier point tracé.
  const latestPoint = history[history.length - 1] ?? null;
  const value = latestPoint
    ? (latestPoint[metric.key] ?? null)
    : (device.latest_telemetry?.[metric.key] ?? null);
  const observedAt =
    latestPoint?.observed_at ?? device.latest_telemetry?.observed_at ?? null;
  const isOnline = device.online;
  // Un objet peut rester `online` (connecté au broker) tout en ayant arrêté sa
  // télémétrie (mode pause) : la valeur affichée ne décrit alors plus la salle.
  const isStale = isTelemetryStale(observedAt);

  return (
    <ThemedView
      type="backgroundElement"
      style={[
        styles.card,
        cardShadow,
        { borderColor: theme.border, width: widthPercent },
      ]}
    >
      <ThemedView style={styles.header}>
        <ThemedView style={styles.headerInfo}>
          <ThemedText type="subtitle" style={styles.deviceId}>
            {device.id}
          </ThemedText>

          <ThemedText type="small" themeColor="textSecondary">
            {observedAt
              ? new Date(observedAt).toLocaleString()
              : "Aucune mesure"}
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
  );
}

const styles = StyleSheet.create({
  card: {
    padding: Spacing.four,
    borderRadius: Radius.large,
    borderWidth: 1,
    gap: Spacing.three,
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
