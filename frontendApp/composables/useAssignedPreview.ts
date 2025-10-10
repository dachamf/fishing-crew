import { computed, onMounted, onUnmounted, ref } from "vue";

type AssignedItem = any; // ili uvezi tvoj tip: import type { FishingSession } from "~/types/api";

const items = ref<AssignedItem[]>([]);
const meta = ref<any>(null);
const loading = ref(false);
const _intervalId = ref<number | null>(null);
const _inflight = ref<Promise<any> | null>(null);
const _started = ref(false);

export function useAssignedPreview() {
  const { $api } = useNuxtApp() as any;
  const auth = useAuth();

  async function fetchOnce() {
    if (!auth.user.value) {
      items.value = [];
      meta.value = null;
      return;
    }
    if (_inflight.value)
      return _inflight.value; // dedupe

    loading.value = true;
    _inflight.value = $api
      .get("/v1/sessions/assigned-to-me", { params: { page: 1, per_page: 5 } })
      .then((r: any) => {
        const payload = r.data ?? {};
        items.value = (payload.data ?? payload.items ?? []) as AssignedItem[];
        meta.value = payload.meta ?? null;
      })
      .catch(() => {
        items.value = [];
        meta.value = null;
      })
      .finally(() => {
        loading.value = false;
        _inflight.value = null;
      });

    return _inflight.value;
  }

  function startPolling(ms = 60_000) {
    if (_started.value)
      return; // već aktivno
    _started.value = true;

    // odmah jedan fetch
    void fetchOnce();

    // polling samo kada je tab vidljiv
    const tick = () => {
      if (document.visibilityState === "visible")
        void fetchOnce();
    };

    const id = window.setInterval(tick, ms);
    _intervalId.value = id as any;

    // pauziraj/nastavi na visibility change
    const onVis = () => {
      if (document.visibilityState === "visible")
        void fetchOnce();
    };
    document.addEventListener("visibilitychange", onVis)

    // cleanup handler se čuva na window za stopPolling
    ;(window as any).__assigned_preview_onVis = onVis;
  }

  function stopPolling() {
    if (_intervalId.value != null) {
      clearInterval(_intervalId.value);
      _intervalId.value = null;
    }
    const onVis = (window as any).__assigned_preview_onVis;
    if (onVis) {
      document.removeEventListener("visibilitychange", onVis)
      ;(window as any).__assigned_preview_onVis = undefined;
    }
    _started.value = false;
  }

  // helperi za komponente
  const total = computed(() => meta.value?.total ?? items.value.length ?? 0);

  onMounted(() => {
    // ne automatski; komponente same pozivaju startPolling()
  });
  onUnmounted(() => {
    // nemoj ovde stopPolling — više komponenti deli isti polling
    // Komponenta koja “vodi” (npr. navbar) može start/stop po želji.
  });

  return {
    items,
    meta,
    total,
    loading,
    fetchOnce,
    startPolling,
    stopPolling,
  };
}
