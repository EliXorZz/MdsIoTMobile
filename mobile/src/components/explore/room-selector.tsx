import { ActivityIndicator, Pressable, ScrollView } from "react-native";

import { ThemedText } from "@/components/themed-text";
import { ThemedView } from "@/components/themed-view";
import { useTheme } from "@/hooks/use-theme";
import type { Room } from "@/types/telemetry";

import { exploreStyles as styles } from "./shared-styles";

type Props = {
  rooms: Room[];
  selectedRoom: Room | null;
  onSelect: (room: Room) => void;
  loading: boolean;
};

export function RoomSelector({
  rooms,
  selectedRoom,
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

  if (rooms.length === 0) {
    return (
      <ThemedView
        type="backgroundElement"
        style={[styles.emptyCard, { borderColor: theme.border }]}
      >
        <ThemedText type="small" themeColor="textSecondary">
          Aucune salle disponible.
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
      {rooms.map((room) => {
        const selected = selectedRoom?.id === room.id;

        return (
          <Pressable
            key={room.id}
            onPress={() => onSelect(room)}
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
              {room.name}
            </ThemedText>
          </Pressable>
        );
      })}
    </ScrollView>
  );
}
