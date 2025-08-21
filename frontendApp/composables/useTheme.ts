import type { ThemeMode } from '~/types/api'

export function useTheme() {
  const theme = useState<ThemeMode>('theme', () => 'light')
  const auth = useAuth()
  const { profile, loadMe, updateProfile } = useProfile()

  function apply(t: ThemeMode) {
    if (process.client) {
      document.documentElement.setAttribute('data-theme', t)
      localStorage.setItem('theme', t)
    }
  }

  onMounted(async () => {
    if (auth.user.value && !profile.value) await loadMe()

    const serverTheme = (profile.value?.theme ??
      profile.value?.settings?.theme) as ThemeMode | undefined
    const saved = (localStorage.getItem('theme') as ThemeMode | null)
    const sysDark = window.matchMedia?.('(prefers-color-scheme: dark)').matches

    const initial = serverTheme ?? saved ?? (sysDark ? 'dark' : 'light')
    theme.value = initial
    apply(initial)
  })

  watch(() => profile.value?.theme, (t) => {
    if (!t) return
    if (t !== theme.value) { theme.value = t as ThemeMode; apply(theme.value) }
  })

  async function setTheme(t: ThemeMode) {
    theme.value = t
    apply(t)
    if (auth.user.value) {
      try {
        await updateProfile({ theme: t })
      } catch (e) {
        if (process.dev) console.warn('Failed to persist theme preference', e)
      }
    }
  }

  function toggle() { setTheme(theme.value === 'light' ? 'dark' : 'light') }

  return { theme, setTheme, toggle }
}
