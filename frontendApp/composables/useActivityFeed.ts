import type { FeedItem } from "~/types/api";

export function useActivityFeed() {
  const items = ref<FeedItem[]>([]);
  const loading = ref(false);
  const { $api } = useNuxtApp() as any;

  async function fetchFeed(groupId?: number, limit = 10) {
    loading.value = true;
    try {
      const { data } = await $api.get("/v1/activity", {
        params: { group_id: groupId, limit },
        withCredentials: true,
      });
      items.value = data || [];
    }
    finally {
      loading.value = false;
    }
  }

  return { items, loading, fetchFeed };
}