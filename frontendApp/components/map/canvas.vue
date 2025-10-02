<!-- components/map/MapCanvas.vue -->
<script setup lang="ts">
import type { MglEvent } from "@indoorequal/vue-maplibre-gl";
import type { LngLatBoundsLike, LngLatLike, Map as MapLibreMap, PaddingOptions } from "maplibre-gl";

import { MglMarker } from "@indoorequal/vue-maplibre-gl"; // ← sigurno, jer smo u ClientOnly

defineOptions({ name: "MapCanvas", inheritAttrs: false });

const props = withDefaults(
  defineProps<{
    styleUrl: string;
    center?: LngLatLike | null;
    zoom?: number;
    bounds?: LngLatBoundsLike | null;
    fitBoundsPadding?: number | Partial<PaddingOptions>;
    height?: number | string;
    interactive?: boolean;
    doubleClickZoom?: boolean;

    /** NOVO: markeri koje treba iscrtać (opciono) */
    points?: Array<{ id?: string | number; coordinates: [number, number]; draggable?: boolean }>;
  }>(),
  {
    center: null,
    zoom: 9,
    bounds: null,
    fitBoundsPadding: 24,
    height: 320,
    interactive: true,
    doubleClickZoom: true,
    points: () => [],
  },
);

const emit = defineEmits<{
  (e: "load", ev: MglEvent<"load">): void;
  (e: "ready", map: MapLibreMap): void;
  /** (opciono) bubble-ovanje promene koordinate markera */
  (e: "update:point", p: { id?: string | number; coordinates: [number, number] }): void;
}>();
const styleObj = computed(() => ({
  height: typeof props.height === "number" ? `${props.height}px` : props.height,
}));

const mapRef = shallowRef<MapLibreMap | null>(null);
let rafId: number | null = null;
function scheduleResize() {
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
      map.fitBounds(props.bounds, { padding: p, duration: 0 });
      return;
    }
    catch {}
  }
  if (props.center)
    map.setCenter(props.center as LngLatLike);
  if (typeof props.zoom === "number")
    map.setZoom(props.zoom);
}
function onLoad(ev: MglEvent<"load">) {
  mapRef.value = (ev as any).map as MapLibreMap;
  applyView();
  scheduleResize();
  emit("load", ev);
  if (mapRef.value)
    emit("ready", mapRef.value);
}
watch(
  () => props.styleUrl,
  () => scheduleResize(),
);
watch(
  () => [props.bounds, props.center, props.zoom] as const,
  () => {
    if (mapRef.value)
      applyView();
  },
  { deep: true },
);
</script>

<template>
  <div
    v-bind="$attrs"
    :style="styleObj"
    class="relative rounded-xl overflow-hidden"
  >
    <ClientOnly>
      <div class="absolute inset-0">
        <MglMap
          class="h-full w-full"
          :map-style="props.styleUrl"
          :interactive="props.interactive"
          :double-click-zoom="props.doubleClickZoom"
          @map:load="onLoad"
        >
          <!-- Markeri su sada unutra (klijent-only) -->
          <MglMarker
            v-for="p in props.points"
            :key="p.id ?? `${p.coordinates[0]}-${p.coordinates[1]}`"
            :coordinates="p.coordinates"
            :draggable="p.draggable ?? false"
            @update:coordinates="
              (coords: [number, number]) => emit('update:point', { id: p.id, coordinates: coords })
            "
          />
          <!-- I dalje zadržavamo slot za slojeve/overlays po potrebi -->
          <slot />
        </MglMap>
      </div>

      <template #placeholder>
        <div class="h-full w-full skeleton" />
      </template>
    </ClientOnly>
  </div>
</template>
