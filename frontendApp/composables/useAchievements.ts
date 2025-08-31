import type { Badge } from "~/types/api";

export function useAchievements() {
  const items = ref<Badge[]>([]);
  const loading = ref(false);
  const { $api } = useNuxtApp();

  async function fetchMy() {
    loading.value = true;
    try {
      const { data } = await $api.get<Badge[]>("/v1/achievements", {
        params: { scope: "me" },
        withCredentials: true,
      });
      items.value = data || [];
    }
    finally {
      loading.value = false;
    }
  }

  return { items, loading, fetchMy };
}
