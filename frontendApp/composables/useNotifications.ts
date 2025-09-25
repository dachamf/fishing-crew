export function useNotifications() {
  const { $api } = useNuxtApp() as any;
  const unread = ref<number>(0);
  const loading = ref(false);

  async function fetchCount() {
    loading.value = true;
    try {
      const { data } = await $api.get("/v1/notifications/unread-count", { withCredentials: true });
      unread.value = Number(data?.unread_count || 0);
    }
    catch {
      /* noop */
    }
    finally {
      loading.value = false;
    }
  }

  useSWR(fetchCount, { intervalMs: 60_000, enabled: true });

  onMounted(fetchCount);

  return { unread, loading, fetchCount };
}
