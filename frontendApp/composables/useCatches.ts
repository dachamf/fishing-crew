import type { CatchItem } from "~/types/api";

export function useCatchList(params?: () => Record<string, any>) {
  const { $api } = useNuxtApp() as any;
  const page = ref(1);
  const items = ref<CatchItem[]>([]);
  const meta = ref<any>(null);
  const pending = ref(false);
  const error = ref<any>(null);

  async function load(reset = false) {
    pending.value = true;
    try {
      if (reset)
        page.value = 1;
      const res = await $api.get("/v1/catches", {
        params: { page: page.value, ...(params?.() || {}) },
      });
      const data = res.data?.data ?? res.data;
      const arr = Array.isArray(data) ? data : (data?.data ?? []);
      const newArr = page.value === 1 ? arr : [...items.value, ...arr];
      items.value = newArr;
      meta.value = res.data?.meta ?? null;
    }
    catch (e) {
      error.value = e;
    }
    finally {
      pending.value = false;
    }
  }

  function loadMore() {
    if (!meta.value?.next_page_url)
      return;
    page.value += 1;
    return load(false);
  }

  onMounted(() => load(true));
  return { items, meta, pending, error, load, loadMore };
}

export async function createCatch(fd: FormData) {
  const { $api } = useNuxtApp() as any;
  return (
    await $api.post("/v1/catches", fd, { headers: { "Content-Type": "multipart/form-data" } })
  ).data;
}
