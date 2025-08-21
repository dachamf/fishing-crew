export default defineNuxtPlugin((nuxtApp) => {
  const indicator = useLoadingIndicator()
  const router = useRouter()
  router.beforeEach((to, from, next) => { if (to.fullPath !== from.fullPath) indicator.start(); next() })
  router.afterEach(() => indicator.finish())
  router.onError(() => indicator.finish())
  nuxtApp.hook('app:error', () => indicator.finish())
});
