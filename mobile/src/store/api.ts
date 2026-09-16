import { createApi, fetchBaseQuery } from "@reduxjs/toolkit/query/react";

import { API_URL } from "@/constants/api";

import type {
  Device,
  DevicesResponse,
  TelemetryPoint,
  TelemetryResponse,
} from "@/types/telemetry";

const rawBaseQuery = fetchBaseQuery({
  baseUrl: API_URL,
});

const loggingBaseQuery: typeof rawBaseQuery = async (
  args,
  api,
  extraOptions,
) => {
  const start = Date.now();

  const request =
    typeof args === "string"
      ? {
          url: args,
          method: "GET",
          params: undefined,
        }
      : {
          url: args.url,
          method: args.method ?? "GET",
          params: args.params,
        };

  const fullUrl = `${API_URL}${request.url}`;

  console.log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
  console.log("📡 [API REQUEST]");
  console.log("➡️", request.method, fullUrl);

  if (request.params) {
    console.log("🔎 Params:", request.params);
  }

  try {
    const result = await rawBaseQuery(args, api, extraOptions);

    const duration = Date.now() - start;

    if (result.error) {
      console.error("❌ [API ERROR]");
      console.error("➡️", request.method, fullUrl);
      console.error("⏱️", `${duration}ms`);
      console.error("Status:", result.error.status);
      console.error("Error:", result.error);

      return result;
    }

    console.log("✅ [API RESPONSE]");
    console.log("⬅️", request.method, fullUrl);
    console.log("⏱️", `${duration}ms`);
    console.log("📦 Data:", result.data);

    return result;
  } catch (error) {
    console.error("💥 [API EXCEPTION]");
    console.error(error);

    throw error;
  } finally {
    console.log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
  }
};

export const api = createApi({
  reducerPath: "api",
  baseQuery: loggingBaseQuery,

  tagTypes: ["Device", "Telemetry"],

  refetchOnReconnect: true,
  refetchOnFocus: true,

  endpoints: (builder) => ({
    getDevices: builder.query<Device[], void>({
      query: () => "/devices",

      transformResponse: (response: DevicesResponse) => {
        console.log("🔄 [TRANSFORM /devices]");
        console.log("Raw response:", response);

        return response.data;
      },

      providesTags: ["Device"],
    }),

    getDeviceTelemetry: builder.query<
      TelemetryPoint[],
      {
        deviceId: string;
        perPage?: number;
        bucket?: number;
      }
    >({
      query: ({ deviceId, perPage = 30, bucket = 5 }) => ({
        url: `/devices/${deviceId}/telemetry`,
        params: {
          per_page: perPage,
          bucket,
        },
      }),

      transformResponse: (response: TelemetryResponse) => {
        console.log("🔄 [TRANSFORM TELEMETRY]");
        console.log("Raw response:", response);

        return [...response.data].reverse();
      },

      providesTags: (_result, _error, { deviceId }) => [
        {
          type: "Telemetry",
          id: deviceId,
        },
      ],
    }),
  }),
});

export const { useGetDevicesQuery, useGetDeviceTelemetryQuery } = api;
