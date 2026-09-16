import type { SerializedError } from "@reduxjs/toolkit";
import type { FetchBaseQueryError } from "@reduxjs/toolkit/query";

/** Renders an RTK Query error into a short diagnostic string for on-screen debugging. */
export function formatQueryError(
  error: FetchBaseQueryError | SerializedError | undefined,
) {
  if (!error) return "";

  if ("status" in error) {
    const detail =
      typeof error.data === "string" ? error.data : JSON.stringify(error.data);
    return `[${error.status}] ${detail}`;
  }

  return error.message ?? "Erreur inconnue";
}
