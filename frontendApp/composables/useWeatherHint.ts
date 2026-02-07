import type { WeatherSummary } from "~/types/api";

export type Coords = { lat: number | null; lng: number | null };

export function useWeatherHint() {
  const { $api } = useNuxtApp() as any;

  const coords = ref<Coords>({ lat: null, lng: null });
  const data = ref<WeatherSummary | null>(null);
  const loading = ref(false);
  const error = ref<string | null>(null);
  const geoError = ref<string | null>(null);
  const supported = ref(true); // backend endpoint exists?

  function setCoords(lat: number | null, lng: number | null) {
    coords.value = { lat, lng };
  }

  async function fetchWeatherAt(lat: number | null, lng: number | null) {
    if (lat == null || lng == null)
      return;
    setCoords(lat, lng);
    loading.value = true;
    error.value = null;
    try {
      console.info("[weather] Fetching weather for:", lat, lng);
      const res = await $api.get("/v1/weather/summary", {
        params: { lat, lng },
        withCredentials: true,
      });
      data.value = (res?.data ?? null) as WeatherSummary | null;
      supported.value = true;
      console.info("[weather] Got data:", data.value?.temp_c, data.value?.condition);
    }
    catch (e: any) {
      const status = e?.response?.status;
      console.warn("[weather] API error:", status, e?.message);
      // 404/501 → backend nije spreman; tretiraj kao graceful fallback
      if (status === 404 || status === 501) {
        supported.value = false;
        data.value = null;
      }
      else {
        error.value = e?.response?.data?.message ?? "Greška pri učitavanju prognoze";
      }
    }
    finally {
      loading.value = false;
    }
  }

  async function fetchWeather() {
    await fetchWeatherAt(coords.value.lat, coords.value.lng);
  }

  const hasData = computed(
    () => !!data.value && (data.value.temp_c != null || data.value.wind_kph != null),
  );

  async function useBrowserLocation(): Promise<Coords | null> {
    if (import.meta.server)
      return null;

    if (typeof navigator === "undefined" || !("geolocation" in navigator)) {
      console.warn("[weather] Geolocation API not available");
      geoError.value = "Browser ne podrzava geolokaciju.";
      return null;
    }

    geoError.value = null;
    return new Promise<Coords | null>((resolve) => {
      navigator.geolocation.getCurrentPosition(
        (pos) => {
          const next = { lat: pos.coords.latitude, lng: pos.coords.longitude };
          console.info("[weather] Geolocation success:", next.lat, next.lng);
          setCoords(next.lat, next.lng);
          geoError.value = null;
          resolve(next);
        },
        (geoErr) => {
          console.warn("[weather] Geolocation error:", geoErr.code, geoErr.message);
          if (geoErr.code === geoErr.PERMISSION_DENIED) {
            geoError.value = "Lokacija je blokirana. Dozvoli pristup lokaciji u browseru.";
          }
          else if (geoErr.code === geoErr.TIMEOUT) {
            geoError.value = "Isteklo je vreme za dobijanje lokacije. Pokusaj ponovo.";
          }
          else {
            geoError.value
              = "Uredjaj ne moze da odredi lokaciju. Proveri Location Services u podesavanjima sistema.";
          }
          resolve(null);
        },
        { enableHighAccuracy: false, maximumAge: 60_000, timeout: 15_000 },
      );
    });
  }

  return {
    coords,
    data,
    loading,
    error,
    geoError,
    hasData,
    supported,
    setCoords,
    fetchWeatherAt,
    fetchWeather,
    useBrowserLocation,
  };
}
