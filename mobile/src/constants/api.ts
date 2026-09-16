export const API_URL = "http://10.144.81.105:8000/api";

/** The simulator emits new telemetry roughly every 5 seconds. */
export const POLLING_INTERVAL_MS = 5000;

// Un objet en mode `pause` reste connecté (online) mais arrête sa télémétrie :
// au-delà de ce délai sans nouvelle mesure, la valeur affichée est signalée comme obsolète.
export const STALE_TELEMETRY_MS = 30_000;

/** Number of recent points fetched per device for the history chart. */
export const TELEMETRY_HISTORY_SIZE = 100;

export const TELEMETRY_HISTORY_TIME = 5;
