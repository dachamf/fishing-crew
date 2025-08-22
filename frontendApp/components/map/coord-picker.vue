<script setup lang="ts">
import type { Map as MapLibreMap, LngLatLike } from 'maplibre-gl'
import type { MglEvent } from "@indoorequal/vue-maplibre-gl";
import {CENTER_SERBIA} from "~/lib/constants";


const { mode } = useTheme()
const styleUrl = computed(() => {
  return mode.value === "dark" ? "/styles/dark.json" : "https://tiles.openfreemap.org/styles/liberty";
});

// v-model za koordinate { lng, lat }
const props = defineProps<{
  coords?: { lng: number | null, lat: number | null }
  editable?: boolean
  height?: string
}>()

const emit = defineEmits<{
  (e: 'update:coords', v: { lng: number | null, lat: number | null }): void
}>()

const DEFAULT_CENTER: LngLatLike = CENTER_SERBIA
const center = shallowRef<LngLatLike>(DEFAULT_CENTER)
const zoom = ref(8)
const isEditable = computed(() => props.editable ?? true)
const initialCentered = ref(false)

const toNumOrNull = (v: unknown): number | null => {
  // prazan string, undefined, null -> null; sve ostalo -> broj ili null ako nije finite
  if (v === undefined || v === null || (typeof v === 'string' && v.trim() === '')) return null
  const n = Number(v as any)
  return Number.isFinite(n) ? n : null
}

type LngLatTuple = [number, number]

function toLngLatTuple(ll: LngLatLike): LngLatTuple {
  if (Array.isArray(ll)) {
    const [lng, lat] = ll as [number, number]
    return [Number(lng), Number(lat)]
  }
  // maplibre-gl LngLat i plain objekti imaju .lng i .lat
  const anyLl = ll as { lng: number; lat: number }
  return [Number(anyLl.lng), Number(anyLl.lat)]
}

function setCoords(ll: LngLatLike) {
  // v-model:center
  center.value = ll

  const [lng, lat] = toLngLatTuple(ll)
  const valid = Number.isFinite(lng) && Number.isFinite(lat)

  emit('update:coords', {
    lng: valid ? lng : null,
    lat: valid ? lat : null,
  })
}

function onDblClick(mglEvent: MglEvent<"dblclick">) {
  const lng = mglEvent?.event?.lngLat?.lng;
  const lat = mglEvent?.event?.lngLat?.lat;
  if (lng != null && lat != null) {
    setCoords([lng, lat])
  }
}

const mapRef = shallowRef<MapLibreMap | null>(null)

const onLoad = (e: MglEvent<'load'>): void => {
  mapRef.value = (e as any).map as MapLibreMap
  requestAnimationFrame(() => mapRef.value?.resize())
}

onMounted(() => {
  const c = props.coords
  if (c && c.lng != null && c.lat != null) {
    center.value = [Number(c.lng), Number(c.lat)]
  }
});
/* --- computed za template bez "!" i bez NaN --- */
const coordsVal = computed(() => {
  const c = props.coords
  return {
    lng: toNumOrNull(c?.lng),
    lat: toNumOrNull(c?.lat),
  }
});
// marker samo kad su obe vrednosti FINITE
const hasCoords = computed(() => coordsVal.value.lng !== null && coordsVal.value.lat !== null)

// auto-centar kad stignu validne koordinate (i na prvi render)
watch(
  () => [coordsVal.value.lng, coordsVal.value.lat] as const,
  ([lng, lat]) => {
    if (!initialCentered.value && lng !== null && lat !== null) {
      center.value = [lng, lat]
      // (opciono) zoom.value = Math.max(Number(zoom.value) || 0, 12)
      initialCentered.value = true
    }
  },
  { immediate: true }
)
const markerTuple = computed<LngLatTuple | null>(() =>
  hasCoords.value ? [Number(coordsVal.value.lng), Number(coordsVal.value.lat)] : null
)
</script>

<template>
    <ClientOnly>
      <div class="h-full w-full">
        <MglMap
          v-model:center="center"
          v-model:zoom="zoom"
          :map-style="styleUrl"
          @map:load="onLoad"
          @map:dblclick="onDblClick"
        >
          <MglNavigationControl/>
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
      </div>

      <template #placeholder>
        <div class="h-full w-full grid place-items-center text-sm opacity-70">
          Učitavanje mape…
        </div>
      </template>
    </ClientOnly>

    <div class="pointer-events-none absolute left-2 top-2 z-10 flex gap-2">
      <span v-if="hasCoords" class="badge pointer-events-auto">
        {{ Number(coordsVal.lng).toFixed(6) }}, {{ Number(coordsVal.lat).toFixed(6) }}
      </span>
    </div>
</template>
