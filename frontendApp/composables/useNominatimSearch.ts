import { useDebounceFn } from "@vueuse/core";
import { ref, shallowRef, watch } from "vue";

export type GeoSuggestion = {
  id: number | string;
  label: string;
  lat: number;
  lon: number;
  type?: string;
  cls?: string;
};

export function useNominatimSearch(opts?: { limit?: number; country?: string; minLen?: number }) {
  const q = ref<string>("");
  const items = ref<GeoSuggestion[]>([]);
  const loading = ref(false);
  const error = ref<string | null>(null);
  const controller = shallowRef<AbortController | null>(null);

  const limit = opts?.limit ?? 8;
  const country = opts?.country ?? "rs";
  const minLen = opts?.minLen ?? 3;

  function cancel() {
    controller.value?.abort();
    controller.value = null;
    loading.value = false;
  }

  async function searchNow() {
    const s = (q.value ?? "").trim();
    if (s.length < minLen) {
      items.value = [];
      cancel();
      return;
    }

    cancel();
    controller.value = new AbortController();
    loading.value = true;
    error.value = null;

    try {
      // tvoj endpoint već vraća { id,label,lat,lon,type }
      const res = await $fetch<GeoSuggestion[]>("/api-ssr/osm/search", {
        params: { q: s, limit, countrycodes: country },
        signal: controller.value.signal,
      });
      items.value = Array.isArray(res) ? res : [];
    }
    catch (e: any) {
      if (e?.name !== "AbortError")
        error.value = e?.message || "Greška pri pretrazi";
    }
    finally {
      loading.value = false;
    }
  }

  // 👇 debounce dok korisnik kuca (i dalje zadržavamo i dugme “Pretraži”)
  const debounced = useDebounceFn(searchNow, 350);
  watch(q, (val) => {
    const s = String(val ?? "").trim();
    if (s.length >= minLen) {
      debounced();
    }
    else {
      items.value = [];
      cancel();
    }
  });

  function clear() {
    items.value = [];
    error.value = null;
    // q.value NE diramo — da ostane label u inputu posle “Dodaj”
  }

  return { q, items, loading, error, minLen, searchNow, clear, cancel };
}
