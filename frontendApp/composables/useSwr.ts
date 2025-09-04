import type { UseSWROptions } from "~/types/api";

export function useSWR(refreshFn: () => unknown | Promise<unknown>, opts: UseSWROptions = {}) {
  const isClient = typeof window !== "undefined";
  const interval = opts.intervalMs ?? 30_000;

  // Normalizuj enabled u computed getter
  const getEnabled = computed<boolean>(() => {
    const e = opts.enabled;
    return typeof e === "function" ? (e as () => boolean)() : (e ?? true);
  });

  let timer: ReturnType<typeof setInterval> | null = null;

  const run = async () => {
    // Guard 1: feature flag
    if (!getEnabled.value)
      return;

    // Guard 2: samo na klijentu
    if (!isClient)
      return;

    // Guard 3: ne radi u pozadini / offline
    if (document.visibilityState !== "visible")
      return;
    if ("onLine" in navigator && !navigator.onLine)
      return;

    try {
      await refreshFn();
    }
    catch {
      /* noop */
    }
  };

  const stop = () => {
    if (timer) {
      clearInterval(timer);
      timer = null;
    }
  };

  const start = () => {
    if (!isClient)
      return;
    stop();
    void run(); // odmah jedan tick
    timer = setInterval(run, interval);
  };

  // Event handler-i samo na klijentu
  if (isClient) {
    const onVis = () => {
      if (document.visibilityState === "visible")
        void run();
    };
    const onFocus = () => {
      void run();
    };
    const onOnline = () => {
      void run();
    };

    onMounted(() => {
      if (getEnabled.value)
        start();
      document.addEventListener("visibilitychange", onVis);
      window.addEventListener("focus", onFocus);
      window.addEventListener("online", onOnline);
    });

    onBeforeUnmount(() => {
      stop();
      document.removeEventListener("visibilitychange", onVis);
      window.removeEventListener("focus", onFocus);
      window.removeEventListener("online", onOnline);
    });

    // Reaguj kad se enabled menja
    watch(getEnabled, (en) => {
      en ? start() : stop();
    });
  }

  return {
    start,
    stop,
    isActive: () => timer != null,
    tick: () => run(),
  };
}
