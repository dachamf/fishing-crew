// Lightweight toast store (daisyUI)
export type ToastType = 'success' | 'error' | 'info' | 'warning'
export type Toast = {
  id: number;
  type: ToastType;
  message: string;
  duration?: number
}

export function useToast() {
  const toasts = useState<Toast[]>('toasts', () => [])
  function dismiss(id: number) { toasts.value = toasts.value.filter(t => t.id !== id) }
  function push(t: Omit<Toast, 'id'>) {
    const id = Date.now() + Math.random()
    toasts.value.push({ id, duration: 4000, ...t })
    const ms = t.duration ?? 4000
    if (ms > 0) setTimeout(() => dismiss(id), ms)
  }
  const success = (m: string, d?: number) => push({ type: 'success', message: m, duration: d })
  const error   = (m: string, d?: number) => push({ type: 'error',   message: m, duration: d })
  const info    = (m: string, d?: number) => push({ type: 'info',    message: m, duration: d })
  const warning = (m: string, d?: number) => push({ type: 'warning', message: m, duration: d })
  return {
    toasts,
    push,
    dismiss,
    success,
    error,
    info,
    warning
  }
}
