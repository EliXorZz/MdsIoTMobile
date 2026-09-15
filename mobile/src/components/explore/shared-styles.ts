import { StyleSheet } from "react-native";

import { Radius, Spacing } from "@/constants/theme";

export const cardShadow = {
  shadowColor: "#000",
  shadowOffset: { width: 0, height: 1 },
  shadowOpacity: 0.08,
  shadowRadius: 3,
  elevation: 2,
} as const;

export const exploreStyles = StyleSheet.create({
  horizontalList: {
    gap: Spacing.two,
    paddingVertical: Spacing.half,
  },

  selectionButton: {
    paddingHorizontal: Spacing.four,
    paddingVertical: Spacing.two,
    borderRadius: Radius.full,
    borderWidth: 1,
  },

  pressed: {
    opacity: 0.7,
  },

  emptyCard: {
    padding: Spacing.four,
    borderRadius: Radius.large,
    alignItems: "center",
    borderWidth: 1,
  },

  center: {
    alignItems: "center",
    justifyContent: "center",
    padding: Spacing.four,
  },

  grid: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: Spacing.three,
  },
});
