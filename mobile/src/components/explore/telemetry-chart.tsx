import { useState } from "react";
import { LayoutChangeEvent, StyleSheet, View } from "react-native";
import Svg, { Circle, Line, Polyline } from "react-native-svg";

import { ThemedText } from "@/components/themed-text";
import { Spacing } from "@/constants/theme";
import type { MetricKey, TelemetryPoint } from "@/types/telemetry";
import { roundMetricValue } from "@/utils/telemetry";

type Props = {
  points: TelemetryPoint[];
  metricKey: MetricKey;
  unit: string;
  color: string;
  gridColor: string;
  height?: number;
};

const CHART_PADDING = 8;
const Y_AXIS_WIDTH = 44;

function formatValue(value: number, unit: string) {
  return `${roundMetricValue(value)}${unit}`;
}

function formatTime(bucket: string) {
  return new Date(bucket).toLocaleTimeString([], {
    hour: "2-digit",
    minute: "2-digit",
  });
}

export function TelemetryChart({
  points,
  metricKey,
  unit,
  color,
  gridColor,
  height = 120,
}: Props) {
  const [width, setWidth] = useState(0);

  const handleLayout = (event: LayoutChangeEvent) => {
    setWidth(event.nativeEvent.layout.width);
  };

  const series = points
    .map((point) => ({
      value: point[metricKey],
      bucket: point.bucket,
    }))
    .filter(
      (entry): entry is { value: number; bucket: string } =>
        entry.value !== undefined && entry.value !== null,
    );

  if (series.length < 2) {
    return (
      <View onLayout={handleLayout} style={[styles.placeholder, { height }]}>
        <ThemedText type="small" themeColor="textSecondary">
          Historique insuffisant pour le graphique.
        </ThemedText>
      </View>
    );
  }

  const values = series.map((entry) => entry.value);
  const min = Math.min(...values);
  const max = Math.max(...values);
  const mid = (min + max) / 2;
  const range = max - min || 1;

  const usableWidth = Math.max(width - CHART_PADDING * 2, 0);
  const usableHeight = height - CHART_PADDING * 2;
  const stepX = values.length > 1 ? usableWidth / (values.length - 1) : 0;

  const coordinates = values.map((value, index) => ({
    x: CHART_PADDING + index * stepX,
    y: CHART_PADDING + usableHeight - ((value - min) / range) * usableHeight,
  }));

  const last = coordinates[coordinates.length - 1];

  return (
    <View>
      <View style={styles.row}>
        <View style={[styles.yAxis, { height }]}>
          <ThemedText type="small" themeColor="textSecondary">
            {formatValue(max, unit)}
          </ThemedText>
          <ThemedText type="small" themeColor="textSecondary">
            {formatValue(mid, unit)}
          </ThemedText>
          <ThemedText type="small" themeColor="textSecondary">
            {formatValue(min, unit)}
          </ThemedText>
        </View>

        <View onLayout={handleLayout} style={{ flex: 1, height }}>
          {width > 0 && (
            <Svg width={width} height={height}>
              <Line
                x1={CHART_PADDING}
                y1={CHART_PADDING}
                x2={width - CHART_PADDING}
                y2={CHART_PADDING}
                stroke={gridColor}
                strokeWidth={1}
                strokeDasharray="4 4"
              />
              <Line
                x1={CHART_PADDING}
                y1={CHART_PADDING + usableHeight / 2}
                x2={width - CHART_PADDING}
                y2={CHART_PADDING + usableHeight / 2}
                stroke={gridColor}
                strokeWidth={1}
                strokeDasharray="4 4"
              />
              <Line
                x1={CHART_PADDING}
                y1={CHART_PADDING + usableHeight}
                x2={width - CHART_PADDING}
                y2={CHART_PADDING + usableHeight}
                stroke={gridColor}
                strokeWidth={1}
                strokeDasharray="4 4"
              />

              <Polyline
                points={coordinates
                  .map((point) => `${point.x},${point.y}`)
                  .join(" ")}
                fill="none"
                stroke={color}
                strokeWidth={2}
                strokeLinejoin="round"
                strokeLinecap="round"
              />

              <Circle cx={last.x} cy={last.y} r={3.5} fill={color} />
            </Svg>
          )}
        </View>
      </View>

      <View style={[styles.xAxis, { paddingLeft: Y_AXIS_WIDTH }]}>
        <ThemedText type="small" themeColor="textSecondary">
          {formatTime(series[0].bucket)}
        </ThemedText>
        <ThemedText type="small" themeColor="textSecondary">
          {formatTime(series[series.length - 1].bucket)}
        </ThemedText>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  placeholder: {
    justifyContent: "center",
    alignItems: "center",
    gap: Spacing.one,
  },

  row: {
    flexDirection: "row",
  },

  yAxis: {
    width: Y_AXIS_WIDTH,
    justifyContent: "space-between",
    paddingRight: Spacing.one,
  },

  xAxis: {
    flexDirection: "row",
    justifyContent: "space-between",
    marginTop: Spacing.one,
  },
});
