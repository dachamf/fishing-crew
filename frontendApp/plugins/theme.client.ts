export default defineNuxtPlugin(() => {
  const cfg = useRuntimeConfig()
  const saved = process.client ? localStorage.getItem('theme') : null
  let theme = saved ?? (cfg.public as any).defaultTheme ?? 'system'
  if (theme === 'system') {
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
    theme = prefersDark ? 'dark' : 'light'
  }
  document.documentElement.setAttribute('data-theme', theme)
});
