import { ActivityIndicator, View } from "react-native";

import { ThemedText } from "@/components/themed-text";
import { ThemedView } from "@/components/themed-view";
import { useTheme } from "@/hooks/use-theme";
import type { Device, Metric } from "@/types/telemetry";

import { DeviceCard } from "./device-card";
import { exploreStyles as styles } from "./shared-styles";

type Props = {
  devices: Device[];
  metric: Metric;
  loading: boolean;
  cardWidthPercent: `${number}%`;
};

export function DeviceList({
  devices,
  metric,
  loading,
  cardWidthPercent,
}: Props) {
  const theme = useTheme();

  if (loading) {
    return (
      <ThemedView style={styles.center}>
        <ActivityIndicator size="small" color={theme.tint} />
      </ThemedView>
    );
  }

  if (devices.length === 0) {
    return (
      <ThemedView
        type="backgroundElement"
        style={[styles.emptyCard, { borderColor: theme.border }]}
      >
        <ThemedText type="small" themeColor="textSecondary">
          Aucun capteur dans cette salle.
        </ThemedText>
      </ThemedView>
    );
  }

  return (
    <View style={styles.grid}>
      {devices.map((device) => (
        <DeviceCard
          key={device.id}
          device={device}
          metric={metric}
          widthPercent={cardWidthPercent}
        />
      ))}
    </View>
  );
}
