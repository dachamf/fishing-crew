import type { ApiList, FishingSession, SessionListParams } from "~/types/api";

export function useSessions(paramsRef?: Ref<Record<string, any>>) {
  const { $api } = useNuxtApp() as any;
  ref<SessionListParams>({});
  const p = (paramsRef ?? ref({})) as Ref<Record<string, any>>;

  const asyncData = useAsyncData<ApiList<FishingSession>>(
    computed(() => `sessions:${JSON.stringify(toRaw(p.value))}`),
    async () => {
      const res = await $api.get("/v1/sessions", {
        params: { ...toRaw(p.value), include: "catches.user,photos" },
      });
      return { items: res.data?.data ?? res.data ?? [], meta: res.data?.meta };
    },
    { watch: [p] },
  );

  const list = computed(() => asyncData.data.value?.items ?? []);
  return { ...asyncData, list };
}
