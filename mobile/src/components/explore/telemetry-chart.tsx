import { useRef, useState } from "react";
import {
  LayoutChangeEvent,
  PanResponder,
  StyleSheet,
  View,
} from "react-native";
import Svg, { Circle, Line, Polyline } from "react-native-svg";

import { ThemedText } from "@/components/themed-text";
import { Radius, Spacing } from "@/constants/theme";
import { useTheme } from "@/hooks/use-theme";
import type { MetricKey, TelemetryPoint } from "@/types/telemetry";
import { roundMetricValue } from "@/utils/telemetry";

type Range = "2h" | "24h" | "7j" | "30j";

type Props = {
  points: TelemetryPoint[];
  metricKey: MetricKey;
  unit: string;
  color: string;
  gridColor: string;
  height?: number;
  range?: Range;
  minRange?: number;
};

const CHART_PADDING = 8;
const Y_AXIS_WIDTH = 44;

function formatValue(value: number, unit: string) {
  return `${roundMetricValue(value)}${unit}`;
}

function formatXLabel(bucket: string, range?: Range) {
  const d = new Date(bucket);
  if (!range || range === "2h" || range === "24h") {
    return d.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
  }
  return d.toLocaleDateString([], { day: "2-digit", month: "2-digit" });
}

function formatTooltipTime(bucket: string, range?: Range) {
  const d = new Date(bucket);
  if (!range || range === "2h" || range === "24h") {
    return d.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
  }
  return d.toLocaleDateString([], {
    day: "2-digit",
    month: "2-digit",
    year: "2-digit",
  });
}

export function TelemetryChart({
  points,
  metricKey,
  unit,
  color,
  gridColor,
  height = 120,
  range,
  minRange = 1,
}: Props) {
  const theme = useTheme();
  const [width, setWidth] = useState(0);
  const [activeIndex, setActiveIndex] = useState<number | null>(null);

  const coordinatesRef = useRef<Array<{ x: number; y: number }>>([]);

  const panResponder = useRef(
    PanResponder.create({
      onStartShouldSetPanResponder: () => true,
      onMoveShouldSetPanResponder: () => true,
      onPanResponderGrant: (evt) => {
        const idx = findNearest(coordinatesRef.current, evt.nativeEvent.locationX);
        setActiveIndex(idx);
      },
      onPanResponderMove: (evt) => {
        const idx = findNearest(coordinatesRef.current, evt.nativeEvent.locationX);
        setActiveIndex(idx);
      },
      onPanResponderRelease: () => setActiveIndex(null),
      onPanResponderTerminate: () => setActiveIndex(null),
    }),
  ).current;

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
  const dataMin = Math.min(...values);
  const dataMax = Math.max(...values);
  const dataRange = dataMax - dataMin;
  const mid = (dataMin + dataMax) / 2;

  const effectiveRange = Math.max(dataRange, minRange);
  const padding = effectiveRange * 0.15;
  const chartMin = dataMin - padding;
  const chartRange = effectiveRange + padding * 2;

  const usableWidth = Math.max(width - CHART_PADDING * 2, 0);
  const usableHeight = height - CHART_PADDING * 2;
  const stepX = values.length > 1 ? usableWidth / (values.length - 1) : 0;

  const coordinates = values.map((value, index) => ({
    x: CHART_PADDING + index * stepX,
    y:
      CHART_PADDING +
      usableHeight -
      ((value - chartMin) / chartRange) * usableHeight,
  }));

  coordinatesRef.current = coordinates;

  const last = coordinates[coordinates.length - 1];
  const activeCoord = activeIndex !== null ? coordinates[activeIndex] : null;
  const activeEntry = activeIndex !== null ? series[activeIndex] : null;

  const tooltipLeft =
    activeCoord !== null && activeCoord.x > width / 2
      ? undefined
      : (activeCoord?.x ?? 0) + 8;
  const tooltipRight =
    activeCoord !== null && activeCoord.x > width / 2
      ? width - activeCoord.x + 8
      : undefined;

  return (
    <View>
      <View style={styles.row}>
        <View style={[styles.yAxis, { height }]}>
          <ThemedText type="small" themeColor="textSecondary">
            {formatValue(dataMax, unit)}
          </ThemedText>
          <ThemedText type="small" themeColor="textSecondary">
            {formatValue(mid, unit)}
          </ThemedText>
          <ThemedText type="small" themeColor="textSecondary">
            {formatValue(dataMin, unit)}
          </ThemedText>
        </View>

        <View
          onLayout={handleLayout}
          style={{ flex: 1, height }}
          {...panResponder.panHandlers}
        >
          {width > 0 && (
            <>
              <Svg width={width} height={height}>
                {/* Grid lines */}
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

                {/* Active cursor vertical line */}
                {activeCoord && (
                  <Line
                    x1={activeCoord.x}
                    y1={CHART_PADDING}
                    x2={activeCoord.x}
                    y2={CHART_PADDING + usableHeight}
                    stroke={color}
                    strokeWidth={1}
                    strokeDasharray="3 3"
                    opacity={0.5}
                  />
                )}

                {/* Data line */}
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

                {/* Data point dots — only for sparse series */}
                {coordinates.length <= 50 && coordinates.map((coord, i) => (
                  <Circle
                    key={i}
                    cx={coord.x}
                    cy={coord.y}
                    r={2}
                    fill={color}
                    opacity={0.45}
                  />
                ))}

                {/* Last point */}
                <Circle cx={last.x} cy={last.y} r={3.5} fill={color} />

                {/* Active point */}
                {activeCoord && (
                  <>
                    <Circle
                      cx={activeCoord.x}
                      cy={activeCoord.y}
                      r={7}
                      fill={color}
                      opacity={0.15}
                    />
                    <Circle
                      cx={activeCoord.x}
                      cy={activeCoord.y}
                      r={3.5}
                      fill={color}
                    />
                  </>
                )}
              </Svg>

              {/* Tooltip */}
              {activeCoord && activeEntry && (
                <View
                  pointerEvents="none"
                  style={[
                    styles.tooltip,
                    { backgroundColor: theme.backgroundElement, borderColor: gridColor },
                    tooltipLeft !== undefined ? { left: tooltipLeft } : undefined,
                    tooltipRight !== undefined ? { right: tooltipRight } : undefined,
                    {
                      top: Math.max(
                        CHART_PADDING,
                        activeCoord.y - 32,
                      ),
                    },
                  ]}
                >
                  <ThemedText
                    type="small"
                    style={{ color, fontWeight: "700" }}
                  >
                    {formatValue(activeEntry.value, unit)}
                  </ThemedText>
                  <ThemedText type="small" themeColor="textSecondary">
                    {formatTooltipTime(activeEntry.bucket, range)}
                  </ThemedText>
                </View>
              )}
            </>
          )}
        </View>
      </View>

      <View style={[styles.xAxis, { paddingLeft: Y_AXIS_WIDTH }]}>
        <ThemedText type="small" themeColor="textSecondary">
          {formatXLabel(series[0].bucket, range)}
        </ThemedText>
        <ThemedText type="small" themeColor="textSecondary">
          {formatXLabel(series[series.length - 1].bucket, range)}
        </ThemedText>
      </View>
    </View>
  );
}

function findNearest(
  coords: Array<{ x: number; y: number }>,
  x: number,
): number | null {
  if (!coords.length) return null;
  let minDist = Infinity;
  let nearest = 0;
  for (let i = 0; i < coords.length; i++) {
    const d = Math.abs(coords[i].x - x);
    if (d < minDist) {
      minDist = d;
      nearest = i;
    }
  }
  return nearest;
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

  tooltip: {
    position: "absolute",
    paddingHorizontal: Spacing.two,
    paddingVertical: Spacing.one,
    borderRadius: Radius.small,
    borderWidth: 1,
    gap: 2,
  },
});
