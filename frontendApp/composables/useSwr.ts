import type { UseSWROptions } from "~/types/api";

export function useSWR(refreshFn: () => unknown | Promise<unknown>, opts: UseSWROptions = {}) {
  const getEnabled
    = typeof opts.enabled === "function"
      ? (opts.enabled as () => boolean)
      : () => opts.enabled ?? true;

  const interval = opts.intervalMs ?? 30_000;
  let timer: number | null = null;

  const run = async () => {
    if (!getEnabled())
      return;
    if (typeof document !== "undefined" && document.visibilityState !== "visible")
      return;
    if (typeof navigator !== "undefined" && "onLine" in navigator && !(navigator as any).onLine)
      return;
    try {
      await refreshFn();
    }
    catch {
      /* noop */
    }
  };

  const stop = () => {
    if (timer != null) {
      clearInterval(timer);
      timer = null;
    }
  };
  const start = () => {
    stop();
    void run(); // prvi tick odmah
    timer = window.setInterval(run, interval) as unknown as number;
  };

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

  if (getEnabled())
    start();

  document.addEventListener("visibilitychange", onVis);
  window.addEventListener("focus", onFocus);
  window.addEventListener("online", onOnline);

  onBeforeUnmount(() => {
    stop();
    document.removeEventListener("visibilitychange", onVis);
    window.removeEventListener("focus", onFocus);
    window.removeEventListener("online", onOnline);
  });

  return {
    start,
    stop,
    isActive: () => timer != null,
  };
}
