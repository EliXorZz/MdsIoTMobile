import { useEffect, useState } from "react";
import {
  ActivityIndicator,
  Platform,
  RefreshControl,
  ScrollView,
  StyleSheet,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { ThemedText } from "@/components/themed-text";
import { ThemedView } from "@/components/themed-view";
import { WebBadge } from "@/components/web-badge";
import { BottomTabInset, MaxContentWidth, Spacing } from "@/constants/theme";
import { useTheme } from "@/hooks/use-theme";

const API_URL = "http://10.182.28.105:8000/api/data";

type SensorData = {
  id: number;
  temperature: number;
  humidity: number;
  created_at: string;
};

export default function TabTwoScreen() {
  const safeAreaInsets = useSafeAreaInsets();

  const insets = {
    ...safeAreaInsets,
    bottom: safeAreaInsets.bottom + BottomTabInset + Spacing.three,
  };

  const theme = useTheme();

  const [data, setData] = useState<SensorData[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const fetchData = async () => {
    try {
      setError(null);

      const response = await fetch(API_URL);

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const json = await response.json();

      setData(json);
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Impossible de récupérer les données",
      );
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, []);

  const handleRefresh = () => {
    setRefreshing(true);
    fetchData();
  };

  return (
    <ScrollView
      style={[styles.scrollView, { backgroundColor: theme.background }]}
      contentInset={insets}
      contentContainerStyle={[
        styles.contentContainer,
        {
          paddingTop: Platform.OS === "web" ? Spacing.six : insets.top,
          paddingBottom: Platform.OS === "web" ? Spacing.four : insets.bottom,
        },
      ]}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={handleRefresh} />
      }
    >
      <ThemedView style={styles.container}>
        <ThemedView style={styles.header}>
          <ThemedText type="title">Données des capteurs</ThemedText>

          <ThemedText type="small" themeColor="textSecondary">
            Dernières mesures reçues
          </ThemedText>
        </ThemedView>

        {loading && (
          <ThemedView style={styles.center}>
            <ActivityIndicator size="large" />
            <ThemedText type="small">Chargement des données...</ThemedText>
          </ThemedView>
        )}

        {error && (
          <ThemedView type="backgroundElement" style={styles.errorCard}>
            <ThemedText type="subtitle">Erreur</ThemedText>

            <ThemedText type="small">{error}</ThemedText>
          </ThemedView>
        )}

        {!loading && !error && data.length === 0 && (
          <ThemedView type="backgroundElement" style={styles.emptyCard}>
            <ThemedText type="subtitle">Aucune donnée</ThemedText>

            <ThemedText type="small">
              Aucun relevé n'a encore été reçu.
            </ThemedText>
          </ThemedView>
        )}

        {!loading && !error && data.length > 0 && (
          <ThemedView style={styles.dataList}>
            {data.map((item) => (
              <ThemedView
                key={item.id}
                type="backgroundElement"
                style={styles.dataCard}
              >
                <ThemedView style={styles.cardHeader}>
                  <ThemedText type="subtitle">Mesure #{item.id}</ThemedText>

                  <ThemedText type="small" themeColor="textSecondary">
                    {new Date(item.created_at).toLocaleString()}
                  </ThemedText>
                </ThemedView>

                <ThemedView style={styles.values}>
                  <ThemedView style={styles.value}>
                    <ThemedText type="small">Température</ThemedText>

                    <ThemedText type="title">{item.temperature} °C</ThemedText>
                  </ThemedView>

                  <ThemedView style={styles.value}>
                    <ThemedText type="small">Humidité</ThemedText>

                    <ThemedText type="title">{item.humidity} %</ThemedText>
                  </ThemedView>
                </ThemedView>
              </ThemedView>
            ))}
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

  center: {
    alignItems: "center",
    justifyContent: "center",
    gap: Spacing.three,
    padding: Spacing.six,
  },

  dataList: {
    gap: Spacing.three,
    paddingHorizontal: Spacing.four,
  },

  dataCard: {
    padding: Spacing.four,
    borderRadius: Spacing.four,
    gap: Spacing.four,
  },

  cardHeader: {
    gap: Spacing.one,
  },

  values: {
    flexDirection: "row",
    justifyContent: "space-between",
    gap: Spacing.four,
  },

  value: {
    flex: 1,
    gap: Spacing.one,
  },

  errorCard: {
    marginHorizontal: Spacing.four,
    padding: Spacing.four,
    borderRadius: Spacing.four,
    gap: Spacing.two,
  },

  emptyCard: {
    marginHorizontal: Spacing.four,
    padding: Spacing.four,
    borderRadius: Spacing.four,
    alignItems: "center",
    gap: Spacing.two,
  },
});
