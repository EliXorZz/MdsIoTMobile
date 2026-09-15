import { useEffect, useMemo, useState } from "react";
import {
  Platform,
  RefreshControl,
  ScrollView,
  StyleSheet,
  useWindowDimensions,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { DeviceList } from "@/components/explore/device-list";
import { MetricSelector } from "@/components/explore/metric-selector";
import { RoomSelector } from "@/components/explore/room-selector";
import { ThemedText } from "@/components/themed-text";
import { ThemedView } from "@/components/themed-view";
import { WebBadge } from "@/components/web-badge";
import { POLLING_INTERVAL_MS } from "@/constants/api";
import {
  BottomTabInset,
  ExtraWideBreakpoint,
  MaxContentWidth,
  Radius,
  Spacing,
  WideBreakpoint,
} from "@/constants/theme";
import { useTheme } from "@/hooks/use-theme";
import { useGetDevicesQuery } from "@/store/api";
import { AVAILABLE_METRICS, type Metric, type Room } from "@/types/telemetry";

export default function ExploreScreen() {
  const safeAreaInsets = useSafeAreaInsets();

  const insets = {
    ...safeAreaInsets,
    bottom: safeAreaInsets.bottom + BottomTabInset + Spacing.three,
  };

  const theme = useTheme();

  const { width } = useWindowDimensions();
  const isWide = width >= WideBreakpoint;
  const isExtraWide = width >= ExtraWideBreakpoint;
  const cardWidthPercent = isExtraWide ? "32%" : isWide ? "48%" : "100%";

  // Une seule requête, mise en cache et rafraîchie au rythme des données du simulateur.
  const {
    data: devices = [],
    isLoading,
    error,
    refetch,
  } = useGetDevicesQuery(undefined, {
    pollingInterval: POLLING_INTERVAL_MS,
  });

  const [selectedRoomId, setSelectedRoomId] = useState<string | null>(null);
  const [selectedMetric, setSelectedMetric] = useState<Metric | null>(null);
  const [refreshing, setRefreshing] = useState(false);

  const rooms: Room[] = useMemo(
    () =>
      Array.from(
        new Map(
          devices.map((device) => [
            device.room_id,
            { id: device.room_id, name: device.room_id },
          ]),
        ).values(),
      ),
    [devices],
  );

  const selectedRoom = rooms.find((room) => room.id === selectedRoomId) ?? null;

  const roomDevices = useMemo(
    () => devices.filter((device) => device.room_id === selectedRoomId),
    [devices, selectedRoomId],
  );

  const availableMetrics = useMemo(
    () =>
      AVAILABLE_METRICS.filter((metric) =>
        roomDevices.some(
          (device) => device.latest_telemetry?.[metric.key] !== undefined,
        ),
      ),
    [roomDevices],
  );

  // Sélectionne automatiquement la première métrique disponible pour la salle choisie.
  useEffect(() => {
    if (!selectedMetric && availableMetrics.length > 0) {
      setSelectedMetric(availableMetrics[0]);
    }
  }, [availableMetrics, selectedMetric]);

  const handleRoomChange = (room: Room) => {
    setSelectedRoomId(room.id);
    setSelectedMetric(null);
  };

  const handleRefresh = async () => {
    setRefreshing(true);
    try {
      await refetch();
    } finally {
      setRefreshing(false);
    }
  };

  return (
    <ScrollView
      style={[
        styles.scrollView,
        {
          backgroundColor: theme.background,
        },
      ]}
      contentInset={insets}
      contentContainerStyle={[
        styles.contentContainer,
        {
          paddingTop: Platform.OS === "web" ? Spacing.six : insets.top,

          paddingBottom: Platform.OS === "web" ? Spacing.four : insets.bottom,
        },
      ]}
      refreshControl={
        <RefreshControl
          refreshing={refreshing}
          onRefresh={handleRefresh}
          tintColor={theme.text}
          colors={[theme.tint]}
        />
      }
    >
      <ThemedView style={styles.container}>
        {/* HEADER */}
        <ThemedView style={styles.header}>
          <ThemedView type="backgroundElement" style={styles.eyebrow}>
            <ThemedText type="small" themeColor="textSecondary">
              Capteurs IoT
            </ThemedText>
          </ThemedView>

          <ThemedText type="title" style={styles.title}>
            Données des capteurs
          </ThemedText>

          <ThemedText
            type="small"
            themeColor="textSecondary"
            style={styles.subtitle}
          >
            Sélectionnez une salle et une métrique pour visualiser les dernières
            mesures.
          </ThemedText>
        </ThemedView>

        {/* ERREUR */}
        {error && (
          <ThemedView
            type="backgroundElement"
            style={[styles.errorCard, { borderColor: theme.danger }]}
          >
            <ThemedText type="smallBold" style={{ color: theme.danger }}>
              ⚠ Erreur
            </ThemedText>

            <ThemedText type="small" themeColor="textSecondary">
              Impossible de récupérer les capteurs.
            </ThemedText>
          </ThemedView>
        )}

        {/* SALLES */}
        <ThemedView style={styles.section}>
          <ThemedText type="subtitle" style={styles.sectionTitle}>
            Salle
          </ThemedText>

          <RoomSelector
            rooms={rooms}
            selectedRoom={selectedRoom}
            onSelect={handleRoomChange}
            loading={isLoading}
          />
        </ThemedView>

        {/* METRICS */}
        {selectedRoom && (
          <ThemedView style={styles.section}>
            <ThemedText type="subtitle" style={styles.sectionTitle}>
              Métrique
            </ThemedText>

            <MetricSelector
              metrics={availableMetrics}
              selectedMetric={selectedMetric}
              onSelect={setSelectedMetric}
              loading={isLoading}
            />
          </ThemedView>
        )}

        {/* DONNÉES */}
        {selectedRoom && selectedMetric && (
          <ThemedView style={styles.section}>
            <ThemedView
              type="backgroundElement"
              style={[styles.measurementHeader, { borderColor: theme.border }]}
            >
              <ThemedView>
                <ThemedText type="subtitle">{selectedMetric.name}</ThemedText>

                <ThemedText type="small" themeColor="textSecondary">
                  {selectedRoom.name} · {roomDevices.length} capteur
                  {roomDevices.length > 1 ? "s" : ""}
                </ThemedText>
              </ThemedView>
            </ThemedView>

            <DeviceList
              devices={roomDevices}
              metric={selectedMetric}
              loading={isLoading}
              cardWidthPercent={cardWidthPercent}
            />
          </ThemedView>
        )}

        {Platform.OS === "web" && <WebBadge />}
      </ThemedView>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  scrollView: {
    flex: 1,
  },

  contentContainer: {
    flexGrow: 1,
    flexDirection: "row",
    justifyContent: "center",
  },

  container: {
    width: "100%",
    maxWidth: MaxContentWidth,
    flexGrow: 1,
  },

  header: {
    alignItems: "center",
    gap: Spacing.two,
    paddingHorizontal: Spacing.four,
    paddingVertical: Spacing.six,
  },

  eyebrow: {
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.half,
    borderRadius: Radius.full,
  },

  title: {
    textAlign: "center",
  },

  subtitle: {
    textAlign: "center",
    maxWidth: 320,
  },

  sectionTitle: {
    fontSize: 20,
    lineHeight: 26,
  },

  section: {
    gap: Spacing.three,
    paddingHorizontal: Spacing.four,
    marginBottom: Spacing.four,
  },

  measurementHeader: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    padding: Spacing.four,
    borderRadius: Radius.large,
    borderWidth: 1,
    marginBottom: Spacing.three,
  },

  errorCard: {
    marginHorizontal: Spacing.four,
    padding: Spacing.four,
    borderRadius: Radius.large,
    borderWidth: 1,
    gap: Spacing.two,
    marginBottom: Spacing.four,
  },
});
