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
    .filter((p): p is [number, number] => Number.isFinite(p[0]) && Number.isFinite(p[1]));
  if (pts.length === 0)
    return;

  let minLng = Infinity;
  let minLat = Infinity;
  let maxLng = -Infinity;
  let maxLat = -Infinity;

  for (const p of pts) {
    const lng = p[0];
    const lat = p[1];
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
    /* ignore */
  }
}
function openSession(id: number) {
  router.push(`/sessions/${id}`);
}

onMounted(async () => {
  await fetchLastWithCoords(props.limit);
  if (sessionsWithCoords.value.length === 0) {
    // fallback centar ostaje; bez fit-a
  }
});

watch(items, () => fitToMarkers());
watch(styleUrl, () => requestAnimationFrame(() => mapRef.value?.resize()));
</script>

<template>
  <div class="card bg-base-100 shadow-lg w-full">
    <div class="card-body">
      <h2 class="card-title flex items-center justify-between">
        <span>{{ title }}</span>
        <span v-if="!loading" class="badge badge-ghost">
          {{ sessionsWithCoords.length }} lokacija
        </span>
      </h2>

      <!-- skeleton -->
      <div
        v-if="loading"
        class="w-full rounded bg-base-300 animate-pulse"
        :style="{ height: typeof height === 'number' ? `${height}px` : height }"
      />

      <!-- empty state -->
      <div v-else-if="sessionsWithCoords.length === 0" class="text-sm opacity-70">
        Nema sesija sa koordinatama. Dodaj lokaciju tokom kreiranja ulova ili
        <NuxtLink class="link" to="/catches/new">
          dodaj sada
        </NuxtLink>.
      </div>

      <!-- mapa -->
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
</template>
