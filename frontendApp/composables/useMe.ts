export type MeGroup = {
  id: number;
  name: string;
  season_year?: number;
  role?: string
}
export type Me = {
  id: number;
  name: string;
  email: string;
  groups: MeGroup[];
}

export const useMe = () => {
  const {$api} = useNuxtApp() as any
  return useAsyncData<Me>('me', async () => {
    const res = await $api.get('/v1/me')
    return res.data as Me
  }, {
    server: false,
    immediate: true,
  })
}
