import type { Biggest, Row } from "~/types/api";

export function useMiniLeaderboard() {
  const top = ref<Row[]>([]);
  const biggest = ref<Biggest | null>(null);
  const loading = ref(false);
  const { $api } = useNuxtApp() as any;

  async function fetchLB(groupId: number, year: number, limit = 5) {
    loading.value = true;
    try {
      const { data } = await $api.get("/v1/leaderboard", {
        query: { group_id: groupId, year, limit, include: "user" },
        credentials: "include",
      });
      top.value = data.value?.top || [];
      biggest.value = data.value?.biggest || null;
    }
    finally {
      loading.value = false;
    }
  }

  return { top, biggest, loading, fetchLB };
}