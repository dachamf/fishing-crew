export type ToastType = "success" | "error" | "info" | "warning";
export type Toast = {
  id: number;
  type: ToastType;
  message: string;
  duration?: number;
};

type ToastPayload = { title: string; message: string; timeout?: number };
const DEFAULT_TIMEOUT_MS = 15000;
const MIN_TIMEOUT_MS = 1000;
const MAX_TIMEOUT_MS = 15000;

function titleFor(type: ToastType): string {
  return type === "success"
    ? "Uspeh"
    : type === "error"
      ? "Greska"
      : type === "warning"
        ? "Upozorenje"
        : "Info";
}

function normalizeTimeout(timeout?: number): number {
  if (typeof timeout !== "number" || !Number.isFinite(timeout)) {
    return DEFAULT_TIMEOUT_MS;
  }
  return Math.min(MAX_TIMEOUT_MS, Math.max(MIN_TIMEOUT_MS, timeout));
}

export function useToast() {
  const noop = () => {};
  if (import.meta.server) {
    return {
      toasts: computed(() => [] as Toast[]),
      push: noop,
      dismiss: noop,
      success: noop,
      error: noop,
      info: noop,
      warning: noop,
    };
  }

  const { $iziToast } = useNuxtApp() as any;
  const recent = useState<Record<string, number>>("toast_recent", () => ({}));

  function isDuplicate(type: ToastType, message: string): boolean {
    const now = Date.now();
    const key = `${type}:${message}`;
    const prev = recent.value[key] ?? 0;
    recent.value[key] = now;
    return now - prev < 2500;
  }

  function show(type: ToastType, payload: ToastPayload) {
    if (!$iziToast)
      return;
    if (isDuplicate(type, payload.message))
      return;

    const data = {
      title: payload.title,
      message: payload.message,
      timeout: normalizeTimeout(payload.timeout),
    };
    if (type === "success")
      $iziToast.success(data);
    else if (type === "error")
      $iziToast.error(data);
    else if (type === "warning")
      $iziToast.warning(data);
    else $iziToast.info(data);
  }

  function push(t: Omit<Toast, "id">) {
    show(t.type, {
      title: titleFor(t.type),
      message: t.message,
      timeout: t.duration,
    });
  }

  const success = (m: string, d?: number) =>
    show("success", { title: titleFor("success"), message: m, timeout: d });
  const error = (m: string, d?: number) =>
    show("error", { title: titleFor("error"), message: m, timeout: d });
  const info = (m: string, d?: number) =>
    show("info", { title: titleFor("info"), message: m, timeout: d });
  const warning = (m: string, d?: number) =>
    show("warning", { title: titleFor("warning"), message: m, timeout: d });

  return {
    toasts: computed(() => [] as Toast[]),
    push,
    dismiss: noop,
    success,
    error,
    info,
    warning,
  };
}
