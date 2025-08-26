import type { Me } from '~/types/api'

export const useMe = () => {
  const { $api } = useNuxtApp() as any
  return useAsyncData<Me>(
    'me',
    async () => (await $api.get('/v1/me')).data,
    { server: false, immediate: true }
  )
}
