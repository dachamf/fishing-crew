import type { LeaderboardResponse } from "~/types/api";

export function useLeaderboard(paramsRef: Ref<Record<string, any>>) {
  const { $api } = useNuxtApp() as any;
  const key = computed(() => `leaderboard:${JSON.stringify(toRaw(paramsRef.value))}`);

  const { data, pending, error, refresh } = useAsyncData<LeaderboardResponse>(
    key,
    async () => {
      const p = { include: "user", ...(toRaw(paramsRef.value) || {}) };
      const res = await $api.get("/v1/leaderboard", { params: p });
      const payload = res.data;
      // normalizer: uvek vrati { items: [] }
      return Array.isArray(payload) ? { items: payload } : (payload ?? { items: [] });
    },
    { watch: [paramsRef], server: false },
  );

  const items = computed(() => data.value?.items ?? []);
  const top3 = computed(() => items.value.slice(0, 3));
  const rest = computed(() => items.value.slice(3));

  return { data, items, top3, rest, pending, error, refresh };
}
