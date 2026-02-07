<script setup lang="ts">
import { useWeatherHint } from "@/composables/useWeatherHint";

type Props = {
  title?: string;
  // opcioni hint iz app-a (npr. poslednja open sesija)
  hintLat?: number | null;
  hintLng?: number | null;
};
const props = withDefaults(defineProps<Props>(), {
  title: "Vreme (danas)",
  hintLat: null,
  hintLng: null,
});

const {
  coords,
  data,
  loading,
  error,
  geoError,
  hasData,
  supported,
  fetchWeatherAt,
  fetchWeather,
  useBrowserLocation,
} = useWeatherHint();

const locating = ref(false);

// Default: Beograd
const DEFAULT_LAT = 44.81;
const DEFAULT_LNG = 20.46;

const usingDefault = computed(
  () => coords.value.lat === DEFAULT_LAT && coords.value.lng === DEFAULT_LNG,
);

async function fetchFromHint(lat?: number | null, lng?: number | null): Promise<boolean> {
  if (lat == null || lng == null) {
    return false;
  }
  await fetchWeatherAt(lat, lng);
  return true;
}

onMounted(async () => {
  // 1) Ako imamo hint koordinate, odmah učitaj prognozu
  const usedHint = await fetchFromHint(props.hintLat, props.hintLng);
  if (usedHint) {
    return;
  }
  // 2) Pokušaj browser lokaciju
  const geo = await useBrowserLocation();
  if (geo) {
    await fetchWeatherAt(geo.lat, geo.lng);
    return;
  }
  // 3) Fallback na default lokaciju ako ništa nije uspelo
  await fetchWeatherAt(DEFAULT_LAT, DEFAULT_LNG);
});

watch(
  () => [props.hintLat, props.hintLng] as const,
  async ([lat, lng], [prevLat, prevLng]) => {
    if (lat == null || lng == null) {
      return;
    }
    if (lat === prevLat && lng === prevLng) {
      return;
    }
    await fetchFromHint(lat, lng);
  },
);

async function onUseMyLocation() {
  locating.value = true;
  try {
    const geo = await useBrowserLocation();
    if (geo) {
      await fetchWeatherAt(geo.lat, geo.lng);
    }
    else {
      // Geolokacija nije uspela — koristi default (Beograd)
      error.value = null;
      await fetchWeatherAt(DEFAULT_LAT, DEFAULT_LNG);
    }
  }
  finally {
    locating.value = false;
  }
}
</script>

<template>
  <div class="card bg-base-100 shadow-lg w-full">
    <div class="card-body space-y-3">
      <div class="flex items-center justify-between">
        <h2 class="card-title">
          {{ title }}
        </h2>
        <span v-if="usingDefault" class="badge badge-ghost"> Beograd (podrazumevano) </span>
        <span v-else-if="coords.lat != null && coords.lng != null" class="badge badge-ghost">
          {{ Number(coords.lat).toFixed(2) }}, {{ Number(coords.lng).toFixed(2) }}
        </span>
      </div>

      <!-- Skeleton -->
      <div v-if="loading" class="h-24 w-full rounded bg-base-300 animate-pulse" />

      <!-- Graceful fallback kad backend nije spreman -->
      <div v-else-if="!supported" class="text-sm opacity-70 space-y-2">
        <p>Prognoza će biti dostupna uskoro.</p>
        <div class="join">
          <button class="btn btn-sm join-item" @click="onUseMyLocation">
            <span
              v-if="locating"
              class="loading loading-spinner loading-xs"
              aria-hidden="true"
            />
            📍 Koristi moju lokaciju
          </button>
          <button
            class="btn btn-sm join-item"
            :disabled="coords.lat == null || coords.lng == null"
            @click="fetchWeather"
          >
            Pokušaj opet
          </button>
        </div>
      </div>

      <!-- Nema podataka (nema coords ili API vratio prazno) -->
      <div v-else-if="!hasData && !error" class="text-sm opacity-70 space-y-2">
        <p>Nema podataka za izabranu lokaciju.</p>
        <div class="join">
          <button class="btn btn-sm join-item" @click="onUseMyLocation">
            <span
              v-if="locating"
              class="loading loading-spinner loading-xs"
              aria-hidden="true"
            />
            📍 Koristi moju lokaciju
          </button>
          <button
            class="btn btn-sm join-item"
            :disabled="coords.lat == null || coords.lng == null"
            @click="fetchWeather"
          >
            Osveži
          </button>
        </div>
      </div>

      <!-- Greška -->
      <div v-else-if="error" class="alert alert-warning text-sm">
        {{ error }}
      </div>

      <!-- Prikaz -->
      <div v-else class="grid grid-cols-3 gap-3 items-center">
        <div class="text-4xl font-bold">
          {{ Math.round(data?.temp_c ?? 0) }}°C
        </div>
        <div class="space-y-1 text-sm">
          <div>
            <span class="opacity-70">Vetar:</span>
            <span class="font-medium">{{ Math.round(data?.wind_kph ?? 0) }} km/h</span>
            <span v-if="data?.wind_dir" class="opacity-70">({{ data.wind_dir }})</span>
          </div>
          <div v-if="data?.wind_gust_kph != null">
            <span class="opacity-70">Udari:</span>
            <span class="font-medium">{{ Math.round(data.wind_gust_kph) }} km/h</span>
          </div>
          <div v-if="data?.precip_mm != null">
            <span class="opacity-70">Padavine:</span>
            <span class="font-medium">{{ Number(data.precip_mm).toFixed(1) }} mm</span>
          </div>
        </div>
        <div class="justify-self-end">
          <img
            v-if="data?.icon_url"
            :src="data.icon_url"
            alt=""
            class="w-12 h-12"
          >
          <Icon
            v-else-if="data?.icon_name"
            :name="data.icon_name"
            size="48"
            class="opacity-90"
          />
          <div v-else class="badge badge-ghost">
            {{ data?.condition ?? '—' }}
          </div>
        </div>
      </div>

      <div v-if="usingDefault && geoError" class="text-xs opacity-60">
        {{ geoError }}
      </div>

      <div class="flex items-center gap-2">
        <button
          class="btn btn-xs btn-outline"
          :disabled="locating"
          @click="onUseMyLocation"
        >
          <span
            v-if="locating"
            class="loading loading-spinner loading-xs"
            aria-hidden="true"
          />
          📍 Lokacija
        </button>
        <button
          class="btn btn-xs"
          :disabled="coords.lat == null || coords.lng == null"
          @click="fetchWeather"
        >
          Osveži
        </button>
      </div>
    </div>
  </div>
</template>
