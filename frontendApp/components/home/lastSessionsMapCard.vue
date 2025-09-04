<script setup lang="ts">
import type { LngLatLike, Map as MapLibreMap } from "maplibre-gl";

import { CENTER_SERBIA } from "~/lib/constants";

type Props = { title?: string; limit?: number; height?: number | string };
const props = withDefaults(defineProps<Props>(), {
  title: "Mapa poslednjih izlazaka na vodu (sesija)",
  limit: 10,
  height: 300,
});

const router = useRouter();
const { mode } = useTheme();
const styleUrl = computed(() =>
  mode.value === "dark" ? "/styles/dark.json" : "https://tiles.openfreemap.org/styles/liberty",
);

const { items, loading, fetchLastWithCoords } = useMapSessions();

const center = shallowRef<LngLatLike>(CENTER_SERBIA);
const zoom = ref(6.8);
const mapRef = shallowRef<MapLibreMap | null>(null);

const sessionsWithCoords = computed(() =>
  (items.value || []).filter(s => s.latitude != null && s.longitude != null),
);

function onLoad(e: any) {
  mapRef.value = e?.map as MapLibreMap;
  requestAnimationFrame(() => mapRef.value?.resize());
  fitToMarkers();
}

function fitToMarkers() {
  const map = mapRef.value;
  if (!map)
    return;

  const pts = sessionsWithCoords.value
    .map(s => [Number(s.longitude), Number(s.latitude)] as [number, number])
    .filter(([lng, lat]) => Number.isFinite(lng) && Number.isFinite(lat));

  if (!pts.length)
    return;

  let minLng = Infinity;
  let minLat = Infinity;
  let maxLng = -Infinity;
  let maxLat = -Infinity;
  for (const [lng, lat] of pts) {
    if (lng < minLng)
      minLng = lng;
    if (lng > maxLng)
      maxLng = lng;
    if (lat < minLat)
      minLat = lat;
    if (lat > maxLat)
      maxLat = lat;
  }
  try {
    map.fitBounds(
      [
        [minLng, minLat],
        [maxLng, maxLat],
      ],
      { padding: 32, maxZoom: 12 },
    );
  }
  catch {
    /* noop */
  }
}

function openSession(id: number) {
  router.push(`/sessions/${id}`);
}

onMounted(async () => {
  await fetchLastWithCoords(props.limit);
});

watch(items, () => fitToMarkers());
watch(styleUrl, () => requestAnimationFrame(() => mapRef.value?.resize()));

// opciono: blagi SWR refresh dok je tab aktivan
useSWR(() => fetchLastWithCoords(props.limit), { intervalMs: 60000, enabled: true });
</script>

<template>
  <!-- ✅ koristi loading iz composable-a -->
  <UiSkeletonCard :loading="loading">
    <div class="card bg-base-100 shadow-lg w-full">
      <div class="card-body">
        <h2 class="card-title flex items-center justify-between">
          <span>{{ title }}</span>
          <span
            v-if="!loading"
            class="badge badge-ghost"
            aria-label="Broj lokacija"
          >
            {{ sessionsWithCoords.length }} lokacija
          </span>
        </h2>

        <!-- ✅ prazno stanje -->
        <UiEmptyState
          v-if="!loading && sessionsWithCoords.length === 0"
          title="Nema sesija na mapi"
          desc="Započni sesiju i zabeleži lokaciju."
          cta-text="Pokreni sesiju"
          to="/catches/new"
          icon="tabler:map"
        />

        <!-- ✅ mapa -->
        <ClientOnly v-else>
          <div
            class="relative w-full"
            :style="{ height: typeof height === 'number' ? `${height}px` : height }"
          >
            <MglMap
              v-model:center="center"
              v-model:zoom="zoom"
              class="absolute inset-0"
              :map-style="styleUrl"
              @map:load="onLoad"
            >
              <MglNavigationControl />
              <MglMarker
                v-for="s in sessionsWithCoords"
                :key="s.id"
                :coordinates="[Number(s.longitude), Number(s.latitude)]"
                class-name="z-40"
              >
                <template #marker>
                  <button
                    class="hover:cursor-pointer active:scale-95 transition"
                    :aria-label="`Otvori sesiju #${s.id}`"
                    @click="openSession(s.id)"
                  >
                    <Icon
                      name="tabler:map-pin-filled"
                      size="28"
                      class="text-primary drop-shadow"
                    />
                  </button>
                </template>
              </MglMarker>
            </MglMap>

            <div class="pointer-events-none absolute left-2 top-2 z-10 flex gap-2">
              <span class="badge badge-ghost pointer-events-auto">Klik na pin → detalj</span>
            </div>
          </div>

          <template #placeholder>
            <div
              class="w-full grid place-items-center text-sm opacity-70"
              :style="{ height: typeof height === 'number' ? `${height}px` : height }"
            >
              Učitavanje mape…
            </div>
          </template>
        </ClientOnly>
      </div>
    </div>
  </UiSkeletonCard>
</template>
