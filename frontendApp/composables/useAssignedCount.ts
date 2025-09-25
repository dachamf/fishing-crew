export function useAssignedCount() {
  const { $api } = useNuxtApp() as any;
  const total = ref<number>(0);
  const loading = ref<boolean>(false);
  const error = ref<string | null>(null);

  async function refresh() {
    loading.value = true;
    error.value = null;
    try {
      const r = await $api.get("/v1/sessions/assigned-count");
      total.value = Number(r?.data?.total_pending || 0);
    }
    catch (e: any) {
      error.value = e?.response?.data?.message || "Greška";
    }
    finally {
      loading.value = false;
    }
  }

  return { total, loading, error, refresh };
}
