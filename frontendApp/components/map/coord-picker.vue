<script setup lang="ts">
import type {LngLatLike} from "maplibre-gl";
import type {MglEvent} from "@indoorequal/vue-maplibre-gl";
import {CENTER_SERBIA} from "~/lib/constants";


const { mode } = useTheme()
const style = computed(() => {
  return mode.value === "dark" ? "/styles/dark.json" : "https://tiles.openfreemap.org/styles/liberty";
});

// v-model za koordinate { lng, lat }
const props = defineProps<{
  coords?: { lng: number | null, lat: number | null }
}>()

const emit = defineEmits<{
  (e: 'update:coords', v: { lng: number | null, lat: number | null }): void
}>()

// Početni centar (Beograd kao primer)
const DEFAULT_CENTER: LngLatLike = CENTER_SERBIA
const center = shallowRef<LngLatLike>(DEFAULT_CENTER)   // v-model:center
const zoom = ref(8)

onMounted(() => {
  if (props.coords?.lng != null && props.coords?.lat != null) {
    center.value = [Number(props.coords.lng), Number(props.coords.lat)]
  }
});

// Marker drag i double-click upisuju coords nazad prema roditelju
function setCoords(ll: LngLatLike) {
  // v-model:center
  center.value = ll
  const [lng, lat] = Array.isArray(ll) ? ll : [ll.lng, ll.lat]
  emit('update:coords', { lng, lat });
}

function onDblClick(mglEvent: MglEvent<"dblclick">) {
  const lng = mglEvent?.event?.lngLat?.lng;
  const lat = mglEvent?.event?.lngLat?.lat;
  if (lng != null && lat != null) {
    setCoords([lng, lat])
  }
}

const onLoad = (e: any) => requestAnimationFrame(() => e?.map?.resize?.())

</script>

<template>
  <div class="w-full h-full min-h-[50svh] overflow-hidden rounded-2xl border border-base-300 relative">
    <ClientOnly>
      <div class="h-full w-full">
        <MglMap
          v-model:center="center"
          v-model:zoom="zoom"
          :map-style="style"
          @map:load="onLoad"
          @map:dblclick="onDblClick"
        >
          <MglNavigationControl />

          <MglMarker
            v-if="coords?.lng != null && coords?.lat != null"
            draggable
            class-name="z-50"
            :coordinates="[Number(coords!.lng), Number(coords!.lat)]"
            @update:coordinates="setCoords"
          >
            <template #marker>
              <div class="hover:cursor-grab active:cursor-grabbing">
                <Icon name="tabler:map-pin-filled" size="36" class="text-warning drop-shadow" />
              </div>
            </template>
          </MglMarker>
        </MglMap>
      </div>

      <!-- opcioni placeholder koji će se videti na server-side renderu -->
      <template #placeholder>
        <div class="h-full w-full grid place-items-center text-sm opacity-70">
          Učitavanje mape…
        </div>
      </template>
    </ClientOnly>

    <div class="pointer-events-none absolute left-2 top-2 z-10 flex gap-2">
      <span v-if="coords" class="badge pointer-events-auto">
        {{ Number(coords!.lng).toFixed(6) }}, {{ Number(coords!.lat).toFixed(6) }}
      </span>
    </div>
  </div>
</template>
