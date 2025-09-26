export function useAssignedCount() {
  const { $api } = useNuxtApp() as any;
  const total = useState<number>("assignedTotal", () => 0);
  const loading = ref(false);

  async function refresh() {
    loading.value = true;
    try {
      const r = await $api.get("/v1/sessions/assigned-count");
      total.value = Number(r.data?.total_pending || 0);
    }
    catch {
      // swallow
    }
    finally {
      loading.value = false;
    }
  }

  return { total, loading, refresh };
}
