<script setup lang="ts">
import { nextTick } from "vue";

type Props = { title?: string; limit?: number };
const props = withDefaults(defineProps<Props>(), {
  title: "Mapa poslednjih sesija",
  limit: 10,
});

useHead({
  link: [{ rel: "preconnect", href: "https://tiles.openfreemap.org", crossorigin: "" }],
});

const mode = useColorMode();
const styleUrl = computed(() =>
  mode.value === "dark" ? "/styles/dark.json" : "https://tiles.openfreemap.org/styles/liberty",
);

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

  if ($mapgl?.createMap || $mapgl?.Map)
    return { lib: "maplibre", gl: $mapgl };
  if ($mapbox?.createMap || $mapbox?.Map)
    return { lib: "mapbox", gl: $mapbox };

  if ((window as any).maplibregl)
    return { lib: "maplibre", gl: (window as any).maplibregl };
  if ((window as any).mapboxgl)
    return { lib: "mapbox", gl: (window as any).mapboxgl };

  return { lib: null, gl: null };
}

function teardownMap() {
  try {
    clearMarkers();
    map?.remove?.();
  }
  catch {}
  map = null;
}

function mountMap(style: string) {
  const { lib, gl } = getMapFactories();
  if (!lib || !gl || !mapEl.value)
    return;

  // ako plugin expose-uje createMap preferiraj ga
  if (typeof gl.createMap === "function") {
    map = gl.createMap({
      container: mapEl.value,
      style,
      center: [20.4489, 44.7866], // BG default
      zoom: 4.8,
    });
  }
  else if (gl.Map) {
    map = new gl.Map({
      container: mapEl.value,
      style,
      center: [20.4489, 44.7866],
      zoom: 4.8,
    });
  }
}

function ensureMap() {
  if (!mapEl.value || map)
    return;
  mountMap(styleUrl.value);
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

  const BoundsCtor
    = gl.LngLatBounds
      || (gl as any).LngLatBounds
      || function (this: any) {
        this._sw = null;
        this._ne = null;
      };
  const bounds = new (BoundsCtor as any)();

  for (const s of items.value) {
    if (s.latitude == null || s.longitude == null)
      continue;

    const lngLat = [Number(s.longitude), Number(s.latitude)] as [number, number];

    // Marker
    const MarkerCtor = gl.Marker || (gl as any).Marker;
    const marker = new MarkerCtor().setLngLat(lngLat).addTo(map);
    markers.push(marker);

    // Popup (opciono)
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
      // ignoriši ako plugin ima custom fitBounds
    }
  }
}

// repaint kad se mapa inicijalizuje (neki plugini imaju on('load'))
function whenMapReady(cb: () => void) {
  if (!map)
    return;
  if (typeof map.on === "function") {
    // Maplibre/Mapbox emituju "load" kada je style i resources spremno
    map.on("load", cb);
  }
  else {
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

// crtaj kad stignu novi podaci
watch(items, () => {
  if (!map)
    return;
  drawMarkers();
});

// REAGUJ NA PROMENU TAME/STILA
watch(
  styleUrl,
  async (next, _prev) => {
    if (!mapEl.value)
      return;

    // ako mapa još nije mount-ovana, samo mount
    if (!map) {
      ensureMap();
      whenMapReady(() => drawMarkers());
      return;
    }

    // ako lib podržava setStyle → koristi to (bez remount-a)
    const canSetStyle = typeof map.setStyle === "function";

    if (canSetStyle) {
      try {
        // setStyle i sačekaj da style bude spreman pa vrati view/markere
        map.setStyle(next);
        const once = (ev: string, handler: any) => {
          if (typeof map.once === "function")
            map.once(ev, handler);
          else setTimeout(handler, 0);
        };
        once("styledata", () => {
          // ponovno crtanje markera i fit bounds nakon promene stila
          drawMarkers();
        });
        return;
      }
      catch {
        // fallback na remount ispod
      }
    }

    // fallback: remount (uvek radi)
    teardownMap();
    await nextTick();
    mountMap(next);
    whenMapReady(() => drawMarkers());
  },
  { immediate: false },
);
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
