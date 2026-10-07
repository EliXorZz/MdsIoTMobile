import { createApi, fetchBaseQuery } from "@reduxjs/toolkit/query/react";

import { API_URL } from "@/constants/api";

import type {
  Command,
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

  tagTypes: ["Device", "Telemetry", "Command"],

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

      onCacheEntryAdded: async (_, { updateCachedData, cacheDataLoaded, cacheEntryRemoved }) => {
        try {
          await cacheDataLoaded;
        } catch {
          return;
        }

        let active = true;
        cacheEntryRemoved.then(() => { active = false; });

        const applyEvent = (block: string) => {
          const match = block.match(/^data: (.+)$/m);
          if (!match) return;
          try {
            const event = JSON.parse(match[1]) as { type: string; device_id: string } & Record<string, unknown>;
            console.log("📡 [SSE EVENT]", event);
            updateCachedData((draft) => {
              const device = draft.find((d) => d.id === event.device_id);
              if (!device) return;
              if (event.type === "availability") device.online = event.online as boolean;
              else if (event.type === "state") device.ventilation = event.ventilation as boolean;
              else if (event.type === "alert") {
                if (device.latest_telemetry) device.latest_telemetry.co2_alert = event.status === "triggered";
              }
            });
          } catch {}
        };

        const connect = () =>
          new Promise<void>((resolve) => {
            const xhr = new XMLHttpRequest();
            xhr.open("GET", `${API_URL}/devices/events`);
            xhr.setRequestHeader("Accept", "text/event-stream");
            xhr.setRequestHeader("Cache-Control", "no-cache");

            let lastIndex = 0;
            let buffer = "";

            xhr.onprogress = () => {
              const chunk = xhr.responseText.slice(lastIndex);
              lastIndex = xhr.responseText.length;
              buffer += chunk;
              const blocks = buffer.split("\n\n");
              buffer = blocks.pop() ?? "";
              for (const block of blocks) applyEvent(block);
            };

            xhr.onload = () => resolve();
            xhr.onerror = () => resolve();

            cacheEntryRemoved.then(() => xhr.abort());

            console.log("📡 [SSE] Connecting to", `${API_URL}/devices/events`);
            xhr.send();
          });

        while (active) {
          await connect();
          if (!active) break;
          console.log("📡 [SSE] Disconnected, retrying in 5s");
          await new Promise((r) => setTimeout(r, 5000));
        }
      },
    }),

    getDeviceTelemetry: builder.query<
      TelemetryPoint[],
      {
        deviceId: string;
        from?: string;
        to?: string;
      }
    >({
      query: ({ deviceId, from, to }) => ({
        url: `/devices/${deviceId}/telemetry`,
        params: {
          ...(from && { from }),
          ...(to && { to }),
        },
      }),

      transformResponse: (response: TelemetryResponse) => {
        console.log("🔄 [TRANSFORM TELEMETRY]");
        console.log("Raw response:", response);

        return response.data;
      },

      providesTags: (_result, _error, { deviceId }) => [
        {
          type: "Telemetry",
          id: deviceId,
        },
      ],
    }),

    sendCommand: builder.mutation<
      Command,
      { deviceId: string; enabled: boolean }
    >({
      query: ({ deviceId, enabled }) => ({
        url: `/devices/${deviceId}/commands`,
        method: "POST",
        body: { action: "set_ventilation", params: { enabled } },
      }),
      invalidatesTags: ["Device"],
    }),
  }),
});

export const {
  useGetDevicesQuery,
  useGetDeviceTelemetryQuery,
  useSendCommandMutation,
} = api;
