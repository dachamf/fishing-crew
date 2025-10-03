<script setup lang="ts">
import type { MglEvent } from "@indoorequal/vue-maplibre-gl";
import type { LngLatLike, Map as MapLibreMap } from "maplibre-gl";

import { CENTER_SERBIA } from "~/lib/constants";

// v-model za koordinate { lng, lat }
const props = defineProps<{
  coords?: { lng: number | null; lat: number | null };
  editable?: boolean;
  height?: string;
  recenterOnChange?: boolean;
}>();

const emit = defineEmits<{
  (e: "update:coords", v: { lng: number | null; lat: number | null }): void;
}>();

useHead({
  link: [{ rel: "preconnect", href: "https://tiles.openfreemap.org", crossorigin: "" }],
});

const { resolved } = useTheme();
const styleUrl = computed(() => {
  return resolved.value === "dark"
    ? "/styles/dark.json"
    : "https://tiles.openfreemap.org/styles/liberty";
});

const DEFAULT_CENTER: LngLatLike = CENTER_SERBIA;
const center = shallowRef<LngLatLike>(DEFAULT_CENTER);
const zoom = ref(8);
const isEditable = computed(() => props.editable ?? true);
const initialCentered = ref(false);
const toast = useToast();

function toNumOrNull(v: unknown): number | null {
  // prazan string, undefined, null -> null; sve ostalo -> broj ili null ako nije finite
  if (v === undefined || v === null || (typeof v === "string" && v.trim() === ""))
    return null;
  const n = Number(v as any);
  return Number.isFinite(n) ? n : null;
}

type LngLatTuple = [number, number];

function toLngLatTuple(ll: LngLatLike): LngLatTuple {
  if (Array.isArray(ll)) {
    const [lng, lat] = ll as [number, number];
    return [Number(lng), Number(lat)];
  }
  // maplibre-gl LngLat i plain objekti imaju .lng i .lat
  const anyLl = ll as { lng: number; lat: number };
  return [Number(anyLl.lng), Number(anyLl.lat)];
}

function setCoords(ll: LngLatLike) {
  // v-model:center
  center.value = ll;

  const [lng, lat] = toLngLatTuple(ll);
  const valid = Number.isFinite(lng) && Number.isFinite(lat);

  emit("update:coords", {
    lng: valid ? lng : null,
    lat: valid ? lat : null,
  });
}

function onDblClick(mglEvent: MglEvent<"dblclick">) {
  if (!isEditable.value)
    return;
  const lng = mglEvent?.event?.lngLat?.lng;
  const lat = mglEvent?.event?.lngLat?.lat;
  if (lng != null && lat != null)
    setCoords([lng, lat]);
}

const mapRef = shallowRef<MapLibreMap | null>(null);

function onLoad(e: MglEvent<"load">): void {
  mapRef.value = (e as any).map as MapLibreMap;
  requestAnimationFrame(() => mapRef.value?.resize());
}

onMounted(() => {
  const c = props.coords;
  if (c && c.lng != null && c.lat != null) {
    center.value = [Number(c.lng), Number(c.lat)];
  }
});
/* --- computed za template bez "!" i bez NaN --- */
const coordsVal = computed(() => {
  const c = props.coords;
  return {
    lng: toNumOrNull(c?.lng),
    lat: toNumOrNull(c?.lat),
  };
});
// marker samo kad su obe vrednosti FINITE
const hasCoords = computed(() => coordsVal.value.lng !== null && coordsVal.value.lat !== null);

// auto-centar kad stignu validne koordinate (i na prvi render)
watch(
  () => [coordsVal.value.lng, coordsVal.value.lat] as const,
  ([lng, lat]) => {
    if (lng !== null && lat !== null) {
      // uvek ažuriramo v-model:center
      center.value = [lng, lat];

      // ako želiš i “bliži” zoom pri picku, odkomentariši:
      if (props.recenterOnChange) {
        // zadrži postojeći zoom ako je veći, ili podigni do 12
        zoom.value = Math.max(Number(zoom.value) || 0, 12);
        // i osveži mapu
        requestAnimationFrame(() => mapRef.value?.resize());
      }
      else {
        // stari behavior (samo prvi put)
        if (!initialCentered.value)
          initialCentered.value = true;
      }
    }
  },
  { immediate: true },
);

watch(styleUrl, () => {
  requestAnimationFrame(() => mapRef.value?.resize());
});
const markerTuple = computed<LngLatTuple | null>(() =>
  hasCoords.value ? [Number(coordsVal.value.lng), Number(coordsVal.value.lat)] : null,
);

function geo() {
  if (!navigator.geolocation)
    return;
  navigator.geolocation.getCurrentPosition(
    pos => setCoords([pos.coords.longitude, pos.coords.latitude]),
    (err: any) => {
      toast.error(toErrorMessage(err.value));
    },
  );
}
</script>

<template>
  <ClientOnly>
    <div
      :style="{
        height: props.height ?? '100%',
      }"
      class="relative w-full"
    >
      <MglMap
        v-model:center="center"
        v-model:zoom="zoom"
        class="absolute inset-0"
        :map-style="styleUrl"
        @map:load="onLoad"
        @map:dblclick="onDblClick"
      >
        <MglNavigationControl />
        <MglMarker
          v-if="hasCoords && markerTuple"
          :draggable="isEditable"
          class-name="z-50"
          :coordinates="markerTuple"
          @update:coordinates="setCoords"
        >
          <template #marker>
            <div class="hover:cursor-grab active:cursor-grabbing">
              <Icon
                name="tabler:map-pin-filled"
                size="36"
                :class="isEditable ? 'text-warning' : 'text-accent'"
                class="drop-shadow"
              />
            </div>
          </template>
        </MglMarker>
      </MglMap>

      <!-- overlay badge sada je u istom (relative) wrapperu -->
      <div class="pointer-events-none absolute left-2 top-2 z-20 flex gap-2">
        <span v-if="hasCoords" class="badge pointer-events-auto">
          {{ Number(coordsVal.lng).toFixed(6) }}, {{ Number(coordsVal.lat).toFixed(6) }}
        </span>
      </div>
    </div>

    <template #placeholder>
      <div
        class="w-full grid place-items-center text-sm opacity-70"
        :style="{
          height: props.height ?? '100%',
        }"
      >
        Učitavanje mape…
      </div>
    </template>
  </ClientOnly>

  <div class="pointer-events-none absolute inset-x-0 bottom-3 z-20 flex justify-center">
    <button
      v-if="isEditable"
      class="btn btn-xs btn-outline pointer-events-auto shadow-md backdrop-blur-sm bg-base-100/80"
      aria-label="Use my location"
      @click.stop.prevent="geo()"
    >
      Izaberi trenutnu lokaciju 📍
    </button>
  </div>
</template>
