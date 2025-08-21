export type Theme = 'light' | 'dark'

export type Profile = {
  id: number
  avatar_url?: string|null
  display_name?: string|null
  bio?: string|null
  birth_year?: number|null
  location?: string|null
  favorite_species?: string[]|null
  gear?: string|null
   settings?: {
    theme: Theme;
   },
}

type MeResponse = { user?: any; profile?: Profile } | Profile

export function useProfile() {
  const { $api } = useNuxtApp() as any;
  const me = useState<MeResponse | null>('meProfile', () => null);

  const profile = computed<Profile | null>(() => {
    const v = me.value as any
    if (!v) return null
    if (v.profile) return v.profile        // { user, profile }
    if (v.data) return v.data              // JsonResource wrap { data: … }
    return v as Profile                    // direktno telo
  })

  const avatarBuster = useState<number>('avatarBuster', () => 0);

  async function loadMe() {
    const { data } = await $api.get('/v1/profile/me')
    me.value = data
    return me.value
  }

  async function updateProfile(patch: Partial<Profile>) {
    const { data } = await $api.patch('/v1/profile', patch)
    // ako API vraća profile, ažuriraj lokalno
    if (profile.value) Object.assign(profile.value, data)
    else await loadMe()
    return data
  }

  async function uploadAvatar(file: File) {
    const fd = new FormData()
    fd.append('avatar', file)
    const { data } = await $api.post('/v1/profile/avatar', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    // očekujemo { avatar_url, path }
    if (profile.value && data?.avatar_url) {
      profile.value.avatar_url = data.avatar_url
    } else {
      await loadMe()
    }
    avatarBuster.value = Date.now()
    return data
  }

  async function deleteAvatar() {
    await $api.delete('/v1/profile/avatar')
    if (profile.value) profile.value.avatar_url = null
    avatarBuster.value = Date.now()
  }

  async function changePassword(current_password: string, password: string) {
    await $api.patch('/v1/profile/password', { current_password, password, password_confirmation: password })
  }

  return {
    me,
    profile,
    loadMe,
    updateProfile,
    uploadAvatar,
    deleteAvatar,
    changePassword,
    avatarBuster
  }
}
