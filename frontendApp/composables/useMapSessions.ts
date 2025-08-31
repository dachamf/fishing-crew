import type { MapSession } from "~/types/api";

export function useMapSessions() {
  const items = ref<MapSession[]>([]);
  const loading = ref(false);
  const { $api } = useNuxtApp();

  async function fetchLastWithCoords(limit = 10) {
    loading.value = true;
    try {
      const { data } = await $api.get<{ data: MapSession[] }>("/v1/sessions", {
        params: {
          user_id: "me",
          whereHasCatches: 1,
          only: "coords,title,id",
          limit,
        },
        withCredentials: true,
      });
      items.value = data?.data ?? [];
    }
    finally {
      loading.value = false;
    }
  }

  return {
    items,
    loading,
    fetchLastWithCoords,
  };
}
