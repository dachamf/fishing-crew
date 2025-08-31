import type { SpeciesRow } from "~/types/api";

export function useSpeciesTrends() {
  const items = ref<SpeciesRow[]>([]);
  const loading = ref(false);
  const { $api } = useNuxtApp();

  async function fetchTop(groupId: number | undefined, year: number, limit = 5) {
    loading.value = true;
    try {
      const { data } = await $api.get<SpeciesRow[]>("/v1/stats/species-top", {
        params: { group_id: groupId, year, scope: "me", limit },
        withCredentials: true,
      });
      items.value = data || [];
    }
    finally {
      loading.value = false;
    }
  }

  return {
    items,
    loading,
    fetchTop,
  };
}
