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
import { useNetworkStatus } from "@/hooks/use-network-status";
import { useTheme } from "@/hooks/use-theme";
import { useGetDevicesQuery } from "@/store/api";
import { AVAILABLE_METRICS, type Metric, type Room } from "@/types/telemetry";
import { formatQueryError } from "@/utils/errors";

export default function ExploreScreen() {
  const safeAreaInsets = useSafeAreaInsets();
  const theme = useTheme();
  const { width } = useWindowDimensions();

  const isConnected = useNetworkStatus();

  const isWide = width >= WideBreakpoint;
  const isExtraWide = width >= ExtraWideBreakpoint;

  const cardWidthPercent = isExtraWide ? "32%" : isWide ? "48%" : "100%";

  const {
    data: devices = [],
    isLoading,
    isFetching,
    error,
    fulfilledTimeStamp,
    refetch,
  } = useGetDevicesQuery(undefined, {
    pollingInterval: POLLING_INTERVAL_MS,
  });

  // `pollingInterval` + `refetchOnReconnect` continuent de retenter l'appel toutes les
  // 5s même hors-ligne ; `isFetching` hors-ligne signale une tentative de reconnexion en cours.
  const isReconnecting = !isConnected && isFetching;
  const lastUpdatedLabel = fulfilledTimeStamp
    ? new Date(fulfilledTimeStamp).toLocaleTimeString()
    : null;

  const [selectedRoomId, setSelectedRoomId] = useState<string | null>(null);
  const [selectedMetric, setSelectedMetric] = useState<Metric | null>(null);
  const [refreshing, setRefreshing] = useState(false);

  /**
   * Calcule les insets utilisés pour éviter que le contenu
   * passe sous les éléments système / la bottom tab bar.
   */
  const contentInsets = useMemo(
    () => ({
      top: safeAreaInsets.top,
      bottom: safeAreaInsets.bottom + BottomTabInset + Spacing.three,
    }),
    [safeAreaInsets.top, safeAreaInsets.bottom],
  );

  /**
   * Génère la liste unique des salles présentes dans les capteurs.
   */
  const rooms = useMemo<Room[]>(() => {
    const uniqueRooms = new Map<string, Room>();

    for (const device of devices) {
      if (!uniqueRooms.has(device.room_id)) {
        uniqueRooms.set(device.room_id, {
          id: device.room_id,
          name: device.room_id,
        });
      }
    }

    return Array.from(uniqueRooms.values());
  }, [devices]);

  /**
   * Salle actuellement sélectionnée.
   */
  const selectedRoom = useMemo(
    () => rooms.find((room) => room.id === selectedRoomId) ?? null,
    [rooms, selectedRoomId],
  );

  /**
   * Capteurs appartenant à la salle sélectionnée.
   */
  const roomDevices = useMemo(
    () =>
      selectedRoomId
        ? devices.filter((device) => device.room_id === selectedRoomId)
        : [],
    [devices, selectedRoomId],
  );

  /**
   * Métriques disponibles pour la salle sélectionnée.
   */
  const availableMetrics = useMemo(
    () =>
      AVAILABLE_METRICS.filter((metric) =>
        roomDevices.some(
          (device) => device.latest_telemetry?.[metric.key] !== undefined,
        ),
      ),
    [roomDevices],
  );

  /**
   * Si la salle sélectionnée n'existe plus après un refresh,
   * on revient à aucune salle.
   */
  useEffect(() => {
    if (
      selectedRoomId !== null &&
      !rooms.some((room) => room.id === selectedRoomId)
    ) {
      setSelectedRoomId(null);
      setSelectedMetric(null);
    }
  }, [rooms, selectedRoomId]);

  /**
   * Si la métrique sélectionnée n'est plus disponible pour
   * la salle actuelle, on sélectionne automatiquement la première.
   */
  useEffect(() => {
    if (availableMetrics.length === 0) {
      setSelectedMetric(null);
      return;
    }

    const currentMetricIsAvailable =
      selectedMetric !== null &&
      availableMetrics.some((metric) => metric.key === selectedMetric.key);

    if (!currentMetricIsAvailable) {
      setSelectedMetric(availableMetrics[0]);
    }
  }, [availableMetrics, selectedMetric]);

  const handleRoomChange = (room: Room) => {
    setSelectedRoomId(room.id);
    setSelectedMetric(null);
  };

  const handleRefresh = async () => {
    if (refreshing) {
      return;
    }

    setRefreshing(true);

    try {
      await refetch().unwrap();
    } catch {
      // L'erreur est déjà gérée par RTK Query via `error`.
    } finally {
      setRefreshing(false);
    }
  };

  const isShowingCachedData = !isConnected && devices.length > 0;

  return (
    <ScrollView
      style={[
        styles.scrollView,
        {
          backgroundColor: theme.background,
        },
      ]}
      contentInset={contentInsets}
      contentContainerStyle={[
        styles.contentContainer,
        {
          paddingTop: Platform.OS === "web" ? Spacing.six : contentInsets.top,

          paddingBottom:
            Platform.OS === "web" ? Spacing.four : contentInsets.bottom,
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

          <ThemedText type="small" themeColor="textSecondary">
            {lastUpdatedLabel
              ? `Dernière mise à jour : ${lastUpdatedLabel}`
              : "En attente des premières données…"}
          </ThemedText>
        </ThemedView>

        {/* HORS LIGNE */}
        {isShowingCachedData && (
          <ThemedView
            type="backgroundElement"
            style={[
              styles.errorCard,
              {
                borderColor: theme.textSecondary,
              },
            ]}
          >
            <ThemedText type="smallBold">📴 Hors ligne</ThemedText>

            <ThemedText type="small" themeColor="textSecondary">
              Affichage des dernières données enregistrées
              {fulfilledTimeStamp
                ? ` (${new Date(fulfilledTimeStamp).toLocaleString()})`
                : ""}
              .
            </ThemedText>

            <ThemedText type="small" themeColor="textSecondary">
              {isReconnecting
                ? "Tentative de reconnexion…"
                : "Nouvelle tentative automatique dès que le réseau revient."}
            </ThemedText>
          </ThemedView>
        )}

        {/* ERREUR */}
        {error && !isShowingCachedData && (
          <ThemedView
            type="backgroundElement"
            style={[
              styles.errorCard,
              {
                borderColor: theme.danger,
              },
            ]}
          >
            <ThemedText type="smallBold" style={{ color: theme.danger }}>
              ⚠ Erreur
            </ThemedText>

            <ThemedText type="small" themeColor="textSecondary">
              {isConnected
                ? "Impossible de récupérer les capteurs."
                : "Aucune donnée en cache disponible hors ligne."}
            </ThemedText>

            {__DEV__ && (
              <ThemedText type="code" themeColor="textSecondary">
                {formatQueryError(error)}
              </ThemedText>
            )}
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

        {/* METRIQUES */}
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

        {/* DONNEES */}
        {selectedRoom && selectedMetric && (
          <ThemedView style={styles.section}>
            <ThemedView
              type="backgroundElement"
              style={[
                styles.measurementHeader,
                {
                  borderColor: theme.border,
                },
              ]}
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
