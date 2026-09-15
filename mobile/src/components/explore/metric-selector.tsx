import { ActivityIndicator, Pressable, ScrollView } from "react-native";

import { ThemedText } from "@/components/themed-text";
import { ThemedView } from "@/components/themed-view";
import { useTheme } from "@/hooks/use-theme";
import type { Metric } from "@/types/telemetry";

import { exploreStyles as styles } from "./shared-styles";

type Props = {
  metrics: Metric[];
  selectedMetric: Metric | null;
  onSelect: (metric: Metric) => void;
  loading: boolean;
};

export function MetricSelector({
  metrics,
  selectedMetric,
  onSelect,
  loading,
}: Props) {
  const theme = useTheme();

  if (loading) {
    return (
      <ThemedView style={styles.center}>
        <ActivityIndicator size="small" color={theme.tint} />
      </ThemedView>
    );
  }

  if (metrics.length === 0) {
    return (
      <ThemedView
        type="backgroundElement"
        style={[styles.emptyCard, { borderColor: theme.border }]}
      >
        <ThemedText type="small" themeColor="textSecondary">
          Aucune métrique disponible.
        </ThemedText>
      </ThemedView>
    );
  }

  return (
    <ScrollView
      horizontal
      showsHorizontalScrollIndicator={false}
      contentContainerStyle={styles.horizontalList}
    >
      {metrics.map((metric) => {
        const selected = selectedMetric?.key === metric.key;

        return (
          <Pressable
            key={metric.key}
            onPress={() => onSelect(metric)}
            style={({ pressed }) => [
              styles.selectionButton,
              {
                backgroundColor: selected
                  ? theme.tint
                  : theme.backgroundElement,
                borderColor: selected ? theme.tint : theme.border,
              },
              pressed && styles.pressed,
            ]}
          >
            <ThemedText
              type="small"
              style={{ color: selected ? theme.onTint : theme.text }}
            >
              {metric.name}
            </ThemedText>
          </Pressable>
        );
      })}
    </ScrollView>
  );
}
