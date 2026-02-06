export function useAssignedCount() {
  const { $api } = useNuxtApp() as any;
  const total = useState<number>("assignedTotal", () => 0);
  const loading = ref(false);

  async function refresh() {
    loading.value = true;
    try {
      const r = await $api.get("/v1/review/assigned", { params: { page: 1, per_page: 1 } });
      const payload = r.data ?? {};
      total.value = Number(payload?.meta?.total ?? payload?.total ?? 0);
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
