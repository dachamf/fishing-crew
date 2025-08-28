import type { FeedItem } from "~/types/api";

export function useActivityFeed() {
  const items = ref<FeedItem[]>([]);
  const loading = ref(false);

  async function fetchFeed(groupId?: number, limit = 10) {
    loading.value = true;
    try {
      const { data } = await useFetch<FeedItem[]>("/api/v1/activity", {
        query: { group_id: groupId, limit },
        credentials: "include",
      });
      items.value = data.value || [];
    }
    finally {
      loading.value = false;
    }
  }

  return {
    items,
    loading,
    fetchFeed,
  };
}
