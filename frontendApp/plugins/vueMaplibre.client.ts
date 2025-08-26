import VueMaplibre from "@indoorequal/vue-maplibre-gl";

export default defineNuxtPlugin((nuxtApp) => {
  // globalno registruje <MglMap>, <MglMarker>, <MglPopup>, <MglNavigationControl>, ...
  nuxtApp.vueApp.use(VueMaplibre);
});
