export type ThemeMode = 'light' | 'dark'

export function useTheme() {
  const theme = useState<ThemeMode>('theme', () => 'light')
  const { $api } = useNuxtApp() as any
  const auth = useAuth()
  const { profile, loadMe } = useProfile()

  function apply(t: ThemeMode) {
    if (process.client) {
      document.documentElement.setAttribute('data-theme', t)
      localStorage.setItem('theme', t)
    }
  }

  // Uzmi preferencu: 1) profil.settings.theme 2) localStorage 3) sistem
  onMounted(async () => {
    // ako smo ulogovani, osiguraj profil
    if (auth.user.value && !profile.value) await loadMe()

    const serverTheme = (profile.value?.theme || profile.value?.settings?.theme) as ThemeMode | undefined
    const saved = (localStorage.getItem('theme') as ThemeMode | null)
    const sysDark = window.matchMedia?.('(prefers-color-scheme: dark)').matches

    const initial = serverTheme ?? saved ?? (sysDark ? 'dark' : 'light')
    theme.value = initial
    apply(initial)
  })

  // kad server profil kaže novu temu, primeni je
  watch(() => profile.value?.settings?.theme, (t) => {
    if (!t) return
    if (t !== theme.value) { theme.value = t as ThemeMode; apply(theme.value) }
  })

  async function setTheme(t: ThemeMode) {
    theme.value = t
    apply(t)
    // persist u BE ako smo ulogovani
    if (auth.user.value) {
      try { await $api.patch('/v1/profile', { theme: t }) } catch {}
    }
  }
  function toggle() { setTheme(theme.value === 'light' ? 'dark' : 'light') }

  return { theme, setTheme, toggle }
}
