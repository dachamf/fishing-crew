import type { WeatherSummary } from "~/types/api";

export type Coords = { lat: number | null; lng: number | null };

export function useWeatherHint() {
  const { $api } = useNuxtApp() as any;

  const coords = ref<Coords>({ lat: null, lng: null });
  const data = ref<WeatherSummary | null>(null);
  const loading = ref(false);
  const error = ref<string | null>(null);
  const supported = ref(true); // backend endpoint exists?

  function setCoords(lat: number | null, lng: number | null) {
    coords.value = { lat, lng };
  }

  async function fetchWeather() {
    if (coords.value.lat == null || coords.value.lng == null)
      return;
    loading.value = true;
    error.value = null;
    try {
      const res = await $api.get("/v1/weather/summary", {
        params: { lat: coords.value.lat, lng: coords.value.lng },
        withCredentials: true,
      });
      data.value = (res?.data ?? null) as WeatherSummary | null;
      supported.value = true;
    }
    catch (e: any) {
      const status = e?.response?.status;
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

  const hasData = computed(
    () => !!data.value && (data.value.temp_c != null || data.value.wind_kph != null),
  );

  async function useBrowserLocation() {
    if (!("geolocation" in navigator))
      return false;
    return new Promise<boolean>((resolve) => {
      navigator.geolocation.getCurrentPosition(
        (pos) => {
          setCoords(pos.coords.latitude, pos.coords.longitude);
          resolve(true);
        },
        () => resolve(false),
        { enableHighAccuracy: false, maximumAge: 30_000, timeout: 5_000 },
      );
    });
  }

  return {
    coords,
    data,
    loading,
    error,
    hasData,
    supported,
    setCoords,
    fetchWeather,
    useBrowserLocation,
  };
}
