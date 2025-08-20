import 'maplibre-gl/dist/maplibre-gl.css'
import MaplibreVue  from '@indoorequal/vue-maplibre-gl'

export default defineNuxtPlugin((nuxtApp) => {
  nuxtApp.vueApp.use(MaplibreVue)
})
