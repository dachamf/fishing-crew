<!-- frontendApp/components/events/location-picker.client.vue -->
<script setup lang="ts">
// Ako koristiš @indoorequal/vue-maplibre-gl:
import 'maplibre-gl/dist/maplibre-gl.css'
import {CENTER_SERBIA} from "~/lib/constants";
const emit = defineEmits<{ (e:'pick', lat:number, lng:number): void }>()

let map: any
const el = ref<HTMLElement | null>(null)

onMounted(async () => {
  const maplibregl = (await import('maplibre-gl')).default
  map = new maplibregl.Map({
    container: el.value!,
    style: 'https://demotiles.maplibre.org/style.json',
    center: CENTER_SERBIA,
    zoom: 6
  })
  map.on('click', (e:any) => {
    const { lat, lng } = e.lngLat
    emit('pick', lat, lng)
  })
})

onBeforeUnmount(() => { try { map?.remove() } catch {} })
</script>

<template>
  <div class="rounded-lg overflow-hidden border">
    <div ref="el" style="height: 300px; width: 100%"></div>
  </div>
</template>
