export function useMySessions() {
  const { $api } = useNuxtApp() as any;

  // NEMA await ovde
  const { data: me, pending: mePending } = useAsyncData(
    "me",
    async () => {
      const res = await $api.get("/v1/me");
      return res.data;
    },
    {
      server: false,
      immediate: true,
    },
  );

  const paramsRef = computed<Record<string, any>>(() => ({
    user_id: me.value?.id,
    per_page: 50,
  }));

  // NEMA await ovde
  const { data, pending, refresh } = useSessions(paramsRef);

  const recent = computed<any[]>(() => data.value?.items ?? []);
  const open = computed<any[]>(() => recent.value.filter((s: any) => s.status === "open"));

  const openFirst = computed<any | null>(() => open.value[0] ?? null);

  async function startNew(payload: any) {
    const res = await $api.post("/v1/sessions", payload);
    await refresh();
    return res.data;
  }

  async function closeSession(id: number) {
    await $api.post(`/v1/sessions/${id}/close`);
    await refresh();
  }

  function stackCatch(sessionId: number, c: any) {
    const list = data.value?.items;
    if (!list)
      return;
    const s = list.find((x: any) => x.id === sessionId);
    if (s)
      (s.catches ||= []).unshift(c);
  }

  const loading = computed(() => mePending.value || pending.value);

  return {
    open,
    openFirst,
    recent,
    startNew,
    closeSession,
    stackCatch,
    loading,
  };
}
