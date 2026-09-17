import * as Device from "expo-device";
import { useEffect, useState } from "react";
import {
  ActivityIndicator,
  Platform,
  ScrollView,
  StyleSheet,
  useWindowDimensions,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { AnimatedIcon } from "@/components/animated-icon";
import { HintRow } from "@/components/hint-row";
import { ThemedText } from "@/components/themed-text";
import { ThemedView } from "@/components/themed-view";
import { WebBadge } from "@/components/web-badge";
import {
  BottomTabInset,
  MaxContentWidth,
  Radius,
  Spacing,
} from "@/constants/theme";
import { useTheme } from "@/hooks/use-theme";

const API_URL = "http://10.217.76.105:8000/health";

function getDevMenuHint() {
  if (Platform.OS === "web") {
    return <ThemedText type="small">use browser devtools</ThemedText>;
  }

  if (Device.isDevice) {
    return (
      <ThemedText type="small">
        shake device or press <ThemedText type="code">m</ThemedText> in terminal
      </ThemedText>
    );
  }

  const shortcut = Platform.OS === "android" ? "cmd+m (or ctrl+m)" : "cmd+d";

  return (
    <ThemedText type="small">
      press <ThemedText type="code">{shortcut}</ThemedText>
    </ThemedText>
  );
}

export default function HomeScreen() {
  const [status, setStatus] = useState<"loading" | "success" | "error">(
    "loading",
  );
  const [response, setResponse] = useState<string>("");

  const theme = useTheme();
  const insets = useSafeAreaInsets();
  const { width } = useWindowDimensions();
  const isNarrow = width < 380;

  const statusColor =
    status === "success"
      ? theme.success
      : status === "error"
        ? theme.danger
        : theme.textSecondary;

  useEffect(() => {
    console.log("Fetching API status...");
    fetch(API_URL)
      .then((res) => {
        console.log("Received response:", res);
        if (!res.ok) {
          throw new Error(`HTTP error! status: ${res.status}`);
        }

        return res.text();
      })
      .then((data) => {
        setStatus("success");
        setResponse(data);
      })
      .catch((err: Error) => {
        setStatus("error");
        setResponse(err.message);
      });
  }, []);

  return (
    <ThemedView style={styles.screen}>
      <ScrollView
        contentContainerStyle={[
          styles.contentContainer,
          {
            paddingTop: Math.max(insets.top, Spacing.four),
            paddingBottom: BottomTabInset + Spacing.four,
          },
        ]}
      >
        <ThemedView style={styles.safeArea}>
          <ThemedView style={styles.heroSection}>
            <AnimatedIcon />

            <ThemedText
              type="title"
              style={[styles.title, isNarrow && styles.titleNarrow]}
            >
              Welcome to IOT
            </ThemedText>
          </ThemedView>

          <ThemedText
            type="code"
            themeColor="textSecondary"
            style={styles.code}
          >
            Test API : {API_URL}
          </ThemedText>

          <ThemedView
            type="backgroundElement"
            style={[styles.stepContainer, { borderColor: theme.border }]}
          >
            {status === "loading" ? (
              <HintRow
                title="API"
                hint={<ActivityIndicator size="small" color={theme.tint} />}
              />
            ) : (
              <HintRow
                title={status === "success" ? "API Online" : "Erreur API"}
                hint={
                  <ThemedView style={styles.statusHint}>
                    <View
                      style={[
                        styles.statusDot,
                        { backgroundColor: statusColor },
                      ]}
                    />
                    <ThemedText type="code">
                      {response || (status === "success" ? "200 OK" : "")}
                    </ThemedText>
                  </ThemedView>
                }
              />
            )}

            <HintRow title="Dev tools" hint={getDevMenuHint()} />
          </ThemedView>

          {Platform.OS === "web" && <WebBadge />}
        </ThemedView>
      </ScrollView>
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  screen: {
    flex: 1,
  },

  contentContainer: {
    flexGrow: 1,
    flexDirection: "row",
    justifyContent: "center",
  },

  safeArea: {
    flex: 1,
    paddingHorizontal: Spacing.four,
    alignItems: "center",
    gap: Spacing.three,
    width: "100%",
    maxWidth: MaxContentWidth,
  },

  heroSection: {
    alignItems: "center",
    justifyContent: "center",
    flex: 1,
    paddingHorizontal: Spacing.four,
    gap: Spacing.four,
  },

  title: {
    textAlign: "center",
  },

  titleNarrow: {
    fontSize: 36,
    lineHeight: 40,
  },

  code: {
    textTransform: "uppercase",
  },

  stepContainer: {
    gap: Spacing.three,
    alignSelf: "stretch",
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.four,
    borderRadius: Radius.large,
    borderWidth: 1,
  },

  statusHint: {
    flexDirection: "row",
    alignItems: "center",
    gap: Spacing.one,
  },

  statusDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
  },
});
