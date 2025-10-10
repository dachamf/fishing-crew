<script setup lang="ts">
import type { MglEvent } from "@indoorequal/vue-maplibre-gl";
import type { LngLatBoundsLike, LngLatLike, Map as MapLibreMap, PaddingOptions } from "maplibre-gl";

const props = withDefaults(
  defineProps<{
    styleUrl: string;
    center?: LngLatLike | null;
    zoom?: number;
    bounds?: LngLatBoundsLike | null;
    fitBoundsPadding?: number | Partial<PaddingOptions>;
    /** NEW: cap fitBounds zoom */
    fitBoundsMaxZoom?: number;
    height?: number | string;
    interactive?: boolean;
    doubleClickZoom?: boolean;
    points?: Array<{ id?: string | number; coordinates: [number, number]; draggable?: boolean }>;
  }>(),
  {
    center: null,
    zoom: 9,
    bounds: null,
    fitBoundsPadding: 24,
    fitBoundsMaxZoom: 12, // 👈 sensible cap
    height: 320,
    interactive: true,
    doubleClickZoom: true,
    points: () => [],
  },
);

const emit = defineEmits<{
  (e: "load", ev: MglEvent<"load">): void;
  (e: "ready", map: MapLibreMap): void;
  (e: "update:point", p: { id?: string | number; coordinates: [number, number] }): void;
}>();

/* ---------- tema & stil kao u CoordPickeru ---------- */
const { mode } = useTheme();
const mapStyle = computed(() => {
  if (props.styleUrl && props.styleUrl.trim().length)
    return props.styleUrl;
  return mode.value === "dark"
    ? "/styles/dark.json"
    : "https://tiles.openfreemap.org/styles/liberty";
});

/* ---------- dimenzije + state ---------- */
const styleObj = computed(() => ({
  height: typeof props.height === "number" ? `${props.height}px` : props.height,
}));
const mapRef = shallowRef<MapLibreMap | null>(null);

/* ---------- sanitizacija koordinata ---------- */
function isFiniteLngLat(t: unknown): t is [number, number] {
  if (!Array.isArray(t) || t.length !== 2)
    return false;
  const [lng, lat] = t;
  return Number.isFinite(lng) && Number.isFinite(lat);
}
const cleanPoints = computed(() =>
  (props.points || []).filter(p => isFiniteLngLat(p.coordinates)),
);

/* ---------- helpers ---------- */
let rafId: number | null = null;
function resizeSoon() {
  if (rafId)
    cancelAnimationFrame(rafId);
  rafId = requestAnimationFrame(() => mapRef.value?.resize());
}

function applyView() {
  const map = mapRef.value;
  if (!map)
    return;

  if (props.bounds) {
    try {
      const p: any
        = typeof props.fitBoundsPadding === "number"
          ? {
              top: props.fitBoundsPadding,
              right: props.fitBoundsPadding,
              bottom: props.fitBoundsPadding,
              left: props.fitBoundsPadding,
            }
          : props.fitBoundsPadding;

      // --- guard: if bounds collapse to a single point, just center & set a sane zoom
      const [[minLng, minLat], [maxLng, maxLat]] = props.bounds as any;
      const isSinglePoint = Number(minLng) === Number(maxLng) && Number(minLat) === Number(maxLat);

      if (isSinglePoint) {
        map.setCenter([minLng, minLat]);
        map.setZoom(
          Math.min(props.fitBoundsMaxZoom ?? 11, typeof props.zoom === "number" ? props.zoom : 11),
        );
        return;
      }

      // normal case: fit + cap zoom
      map.fitBounds(props.bounds, {
        padding: p,
        duration: 0,
        maxZoom: props.fitBoundsMaxZoom ?? 11,
      });

      // extra safety clamp (some styles ignore maxZoom during transitions)
      const cap = props.fitBoundsMaxZoom ?? 11;
      if (map.getZoom() > cap)
        map.setZoom(cap);
      return;
    }
    catch {
      /* noop */
    }
  }

  if (props.center)
    map.setCenter(props.center as LngLatLike);
  if (typeof props.zoom === "number")
    map.setZoom(props.zoom);
}

function onLoad(ev: MglEvent<"load">) {
  mapRef.value = (ev as any).map as MapLibreMap;
  applyView();
  resizeSoon();
  emit("load", ev);
  if (mapRef.value)
    emit("ready", mapRef.value);
}

/* kad se promene bounds/center/zoom ili lista tačaka => osveži pogled */
watch(
  () => [props.bounds, props.center, props.zoom, cleanPoints.value.length] as const,
  () => {
    if (mapRef.value)
      applyView();
  },
);

/* promena stila (tema) => resize da ne “pukne” layout */
watch(mapStyle, (next) => {
  const map = mapRef.value;
  if (!map)
    return;
  try {
    // pokušaj bez uništavanja instance
    map.setStyle(next);
    // sitan resize da se map canvas ne “rastegne”
    requestAnimationFrame(() => map.resize());
  }
  catch {
    // ako lib/plug-in ne podrži hot setStyle,
    // :key na <MglMap> će ga remount-ovati pa smo mirni
  }
});
</script>

<template>
  <div class="relative rounded-xl overflow-hidden" :style="styleObj">
    <ClientOnly>
      <MglMap
        :key="mapStyle"
        :map-style="mapStyle"
        :interactive="props.interactive"
        :double-click-zoom="props.doubleClickZoom"
        @map:load="onLoad"
      >
        <template v-for="p in cleanPoints" :key="p.id ?? `${p.coordinates[0]}-${p.coordinates[1]}`">
          <MglMarker
            :coordinates="p.coordinates"
            :draggable="p.draggable ?? false"
            @update:coordinates="
              (coords: [number, number]) => emit('update:point', { id: p.id, coordinates: coords })
            "
          />
        </template>
        <slot />
      </MglMap>

      <template #placeholder>
        <div class="h-full w-full skeleton" />
      </template>
    </ClientOnly>
  </div>
</template>
