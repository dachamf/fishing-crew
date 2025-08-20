export function useTheme() {
  type T = 'light' | 'dark'

  // SSR-safe persistence
  const cookie = useCookie<T>('theme', { default: () => 'light' })
  const theme = useState<T>('theme', () => cookie.value || 'light')

  const setTheme = (t: T) => {
    theme.value = t
    cookie.value = t
  }
  const toggle = () => setTheme(theme.value === 'light' ? 'dark' : 'light')

  // Set <html data-theme="..."> on both SSR and client
  useHead(() => ({
    htmlAttrs: { 'data-theme': theme.value }
  }))

  // Only touch DOM/localStorage in the browser
  if (process.client) {
    // read once from localStorage (if present)
    const saved = (localStorage.getItem('theme') as T | null)
    if (saved && saved !== theme.value) setTheme(saved)

    // keep DOM + localStorage in sync
    watch(theme, (t) => {
      document.documentElement.setAttribute('data-theme', t)
      localStorage.setItem('theme', t)
    }, { immediate: true })
  }

  return { theme, setTheme, toggle }
}
