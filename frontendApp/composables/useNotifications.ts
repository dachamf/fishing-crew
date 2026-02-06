export function useNotifications() {
  const { isLoggedIn } = useAuth();
  const { $api } = useNuxtApp() as any;
  const unread = ref<number>(0);
  const loading = ref(false);

  async function fetchCount() {
    if (!isLoggedIn.value)
      return;
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

  useSWR(fetchCount, { intervalMs: 60_000, enabled: () => isLoggedIn.value });

  onMounted(() => {
    if (isLoggedIn.value)
      void fetchCount();
  });

  return { unread, loading, fetchCount };
}
