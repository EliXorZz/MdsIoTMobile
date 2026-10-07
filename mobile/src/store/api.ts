import { createApi, fetchBaseQuery } from "@reduxjs/toolkit/query/react";

import { API_URL } from "@/constants/api";

import type {
  Command,
  Device,
  DevicesResponse,
  TelemetryPoint,
  TelemetryResponse,
} from "@/types/telemetry";

export const api = createApi({
  reducerPath: "api",
  baseQuery: fetchBaseQuery({ baseUrl: API_URL }),

  tagTypes: ["Device", "Telemetry", "Command"],

  refetchOnReconnect: true,
  refetchOnFocus: true,

  endpoints: (builder) => ({
    getDevices: builder.query<Device[], void>({
      query: () => "/devices",

      transformResponse: (response: DevicesResponse) => response.data,

      providesTags: ["Device"],

      onCacheEntryAdded: async (_, { updateCachedData, cacheDataLoaded, cacheEntryRemoved }) => {
        try {
          await cacheDataLoaded;
        } catch {
          return;
        }

        let active = true;
        cacheEntryRemoved.then(() => { active = false; });

        const applyRaw = (data: string) => {
          try {
            const event = JSON.parse(data) as { type: string; device_id: string } & Record<string, unknown>;
            console.log("📡 [SSE EVENT]", event);
            updateCachedData((draft) => {
              const device = draft.find((d) => d.id === event.device_id);
              if (!device) return;
              if (event.type === "availability") device.online = event.online as boolean;
              else if (event.type === "state" && event.ventilation != null) device.ventilation = event.ventilation as boolean;
              else if (event.type === "alert") {
                if (device.latest_telemetry) device.latest_telemetry.co2_alert = event.status === "triggered";
              }
            });
          } catch {}
        };

        const url = `${API_URL}/devices/events`;

        if (typeof EventSource !== "undefined") {
          // Web : EventSource natif, pas de preflight CORS, reconnexion auto
          console.log("📡 [SSE] Connecting (EventSource) to", url);
          const es = new EventSource(url);
          es.onmessage = (e) => applyRaw(e.data);
          es.onerror = () => console.log("📡 [SSE] EventSource error");
          cacheEntryRemoved.then(() => es.close());
          await cacheEntryRemoved;
        } else {
          // Native : XHR streaming
          const connect = () =>
            new Promise<void>((resolve) => {
              const xhr = new XMLHttpRequest();
              xhr.open("GET", url);
              xhr.setRequestHeader("Accept", "text/event-stream");

              let lastIndex = 0;
              let buffer = "";

              xhr.onprogress = () => {
                const chunk = xhr.responseText.slice(lastIndex);
                lastIndex = xhr.responseText.length;
                buffer += chunk;
                const blocks = buffer.split("\n\n");
                buffer = blocks.pop() ?? "";
                for (const block of blocks) {
                  const match = block.match(/^data: (.+)$/m);
                  if (match) applyRaw(match[1]);
                }
              };

              xhr.onload = () => resolve();
              xhr.onerror = () => resolve();

              cacheEntryRemoved.then(() => xhr.abort());

              console.log("📡 [SSE] Connecting (XHR) to", url);
              xhr.send();
            });

          while (active) {
            await connect();
            if (!active) break;
            console.log("📡 [SSE] Disconnected, retrying in 5s");
            await new Promise((r) => setTimeout(r, 5000));
          }
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

      transformResponse: (response: TelemetryResponse) => response.data,

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
