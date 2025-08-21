import 'maplibre-gl/dist/maplibre-gl.css'
import MaplibreVue from "@indoorequal/vue-maplibre-gl"
import 'maplibre-gl/dist/maplibre-gl.css'

export default defineNuxtPlugin((nuxtApp) => {
  nuxtApp.vueApp.use(MaplibreVue)
})
