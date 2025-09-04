<script setup lang="ts">
type Props = { title?: string; limit?: number };
const props = withDefaults(defineProps<Props>(), {
  title: "Mapa poslednjih sesija",
  limit: 10,
});

useHead({
  link: [{ rel: "preconnect", href: "https://tiles.openfreemap.org", crossorigin: "" }],
});

// Axios-based composable koji već imamo
const { items, loading, fetchLastWithCoords } = useMapSessions();

const mapEl = ref<HTMLDivElement | null>(null);
let map: any = null; // maplibre-gl ili mapbox-gl instance
let markers: any[] = []; // čuvamo radi clear-a

const hasCoords = computed(() =>
  (items.value || []).some(s => s.latitude != null && s.longitude != null),
);

// Helper: dohvati fabričke objekte iz tvog plugina ili global-a
function getMapFactories() {
  const { $mapgl, $mapbox } = useNuxtApp() as any;

  // prefer tvoj plugin ako postoji
  if ($mapgl?.createMap || $mapgl?.Map)
    return { lib: "maplibre", gl: $mapgl };
  if ($mapbox?.createMap || $mapbox?.Map)
    return { lib: "mapbox", gl: $mapbox };

  // fallback na global window.* ako plugin ne expose-uje
  if ((window as any).maplibregl)
    return { lib: "maplibre", gl: (window as any).maplibregl };
  if ((window as any).mapboxgl)
    return { lib: "mapbox", gl: (window as any).mapboxgl };

  return { lib: null, gl: null };
}

function ensureMap() {
  if (!mapEl.value || map)
    return;

  const { lib, gl } = getMapFactories();
  if (!lib || !gl) {
    // nema map GL dostupnog – karta će prikazati empty state
    return;
  }

  // style: ako tvoj plugin ima default style/url, koristi ga; u suprotnom bezbedan fallback
  const style
    = (gl?.defaultStyle as string)
      || (lib === "maplibre"
        ? "https://demotiles.maplibre.org/style.json"
        : "mapbox://styles/mapbox/streets-v11");

  // ako plugin expose-uje createMap preferiraj ga (često podesi token/style/antialias itd.)
  if (typeof gl.createMap === "function") {
    map = gl.createMap({
      container: mapEl.value,
      style,
      center: [20.4489, 44.7866], // BG default
      zoom: 6.8,
    });
  }
  else if (gl.Map) {
    map = new gl.Map({
      container: mapEl.value,
      style,
      center: [20.4489, 44.7866],
      zoom: 6.8,
    });
  }

  // Mapbox GL traži accessToken; ako ga plugin već setuje – super.
  // Ovde ne radimo ništa jer oslanjamo se na tvoj plugin config.
}

function clearMarkers() {
  if (!markers?.length)
    return;
  for (const m of markers) {
    try {
      m.remove?.(); // i maplibre i mapbox marker imaju remove()
    }
    catch {}
  }
  markers = [];
}

function drawMarkers() {
  if (!map)
    return;

  const { lib, gl } = getMapFactories();
  if (!lib || !gl)
    return;

  clearMarkers();

  const bounds = new (gl.LngLatBounds
    || (gl as any).LngLatBounds
    || function (this: any) {
      // fallback minimalni bounds za slučaj da plugin meri drugačije — retko potrebno
      this._sw = null;
      this._ne = null;
    })();

  for (const s of items.value) {
    if (s.latitude == null || s.longitude == null)
      continue;

    const lngLat = [Number(s.longitude), Number(s.latitude)] as [number, number];

    // Marker
    // U oba lib-a radi new X.Marker().setLngLat().addTo(map)
    const MarkerCtor = gl.Marker || (gl as any).Marker;
    const marker = new MarkerCtor().setLngLat(lngLat).addTo(map);
    markers.push(marker);

    // Popup (opciono, ako je dostupno)
    if (gl.Popup) {
      const popup = new gl.Popup({ offset: 16 }).setHTML(
        `<div class="font-medium">${s.title ?? "Sesija"}</div>
         <a href="/sessions/${s.id}" class="link">Otvori</a>`,
      );
      marker.setPopup(popup);
    }

    // Bounds
    if (typeof bounds.extend === "function") {
      bounds.extend(lngLat as any);
    }
  }

  // Fit bounds kad ima bar 1 marker
  if (markers.length > 0 && typeof map.fitBounds === "function") {
    try {
      map.fitBounds(bounds, { padding: 32, maxZoom: 12 });
    }
    catch {
      // u slučaju da plugin ima custom fitBounds – ignoriši
    }
  }
}

// repaint kad se mapa inicijalizuje (neki plugini imaju on('load'))
function whenMapReady(cb: () => void) {
  if (!map)
    return;
  if (typeof map.on === "function") {
    map.on("load", cb);
  }
  else {
    // ako lib nema events API, samo probaj posle tick-a
    nextTick(cb);
  }
}

onMounted(async () => {
  await fetchLastWithCoords(props.limit);
  ensureMap();
  if (hasCoords.value && map) {
    whenMapReady(() => drawMarkers());
  }
});

watch(items, () => {
  if (!map)
    return;
  drawMarkers();
});
</script>

<template>
  <div class="card bg-base-100 shadow-lg">
    <div class="card-body">
      <h2 class="card-title">
        {{ title }}
      </h2>

      <div v-if="loading" class="h-64 w-full animate-pulse bg-base-300 rounded" />

      <div v-else-if="!hasCoords" class="text-sm opacity-70">
        Nema sesija sa koordinatama. Dodaj lokaciju u sesiji pa pokušaj ponovo.
      </div>

      <!-- ClientOnly osigurava da se map GL ne renderuje na SSR -->
      <ClientOnly>
        <div ref="mapEl" class="h-64 w-full rounded" />
      </ClientOnly>
    </div>
  </div>
</template>
