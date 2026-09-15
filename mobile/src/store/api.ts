import { createApi, fetchBaseQuery } from "@reduxjs/toolkit/query/react";

import { API_URL } from "@/constants/api";
import type {
  Device,
  DevicesResponse,
  TelemetryPoint,
  TelemetryResponse,
} from "@/types/telemetry";

export const api = createApi({
  reducerPath: "api",
  baseQuery: fetchBaseQuery({ baseUrl: API_URL }),
  tagTypes: ["Device", "Telemetry"],
  endpoints: (builder) => ({
    getDevices: builder.query<Device[], void>({
      query: () => "/devices",
      transformResponse: (response: DevicesResponse) => response.data,
      providesTags: ["Device"],
    }),

    getDeviceTelemetry: builder.query<
      TelemetryPoint[],
      { deviceId: string; perPage?: number }
    >({
      query: ({ deviceId, perPage = 30 }) => ({
        url: `/devices/${deviceId}/telemetry`,
        params: { per_page: perPage },
      }),
      // L'API renvoie les mesures les plus récentes en premier ; on les remet dans l'ordre chronologique pour le graphique.
      transformResponse: (response: TelemetryResponse) =>
        [...response.data].reverse(),
      providesTags: (_result, _error, { deviceId }) => [
        { type: "Telemetry", id: deviceId },
      ],
    }),
  }),
});

export const { useGetDevicesQuery, useGetDeviceTelemetryQuery } = api;
