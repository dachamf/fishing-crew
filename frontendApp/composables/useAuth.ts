export function useAuth() {
  type User = { id:number; name:string; email:string; email_verified_at?: string | null }
  const token = useCookie<string | null>('token', {
    path: '/',
    sameSite: 'lax',
    secure: true,
  })
  const user  = useState<User|null>('user', () => null)

  function applyAuthHeader(t?: string | null) {
    if (t) $api.defaults.headers.common.Authorization = `Bearer ${t}`
    else delete $api.defaults.headers.common.Authorization
  }

  const { $api } = useNuxtApp() as any

  async function register(name: string, email: string, password: string, password_confirmation: string) {
    const { data } = await $api.post('/auth/register', {
      name, email, password, password_confirmation,
    })
    if (data?.token) {
      setToken(data.token, true)
      await me(true)
    }
    await me(true)
  }

  function setToken(value: string | null, remember = false) {
    // (Re)zapiši cookie sa odgovarajućim maxAge, ali i sinhronizuj naš ref
    const t = useCookie<string | null>('token', {
      path: '/',
      sameSite: 'lax',
      secure: true,
      maxAge: remember ? 60 * 60 * 24 * 30 : undefined, // 30 dana
    })
    t.value = value
    token.value = value
    applyAuthHeader(value)
  }

  async function login(email: string, password: string, remember = false) {
    const { data } = await $api.post('/auth/login', { email, password })
    setToken(data.token, remember)
    await me(true)
  }

  async function me(forceHeader = false) {
    const cfg = forceHeader && token.value
      ? { headers: { Authorization: `Bearer ${token.value}` } }
      : undefined

    // BITNO: koristi endpoint koji stvarno imaš. Ako imaš /auth/me – koristi njega.
    // Ako koristiš /user (kao u kodu koji si poslao), ostavi ovako:
    const { data } = await $api.get('/user', cfg)
    user.value = data
    return data
  }

  function logout() {
    setToken(null)
    user.value = null
    navigateTo('/login')
  }

  const isVerified = computed(() => !!user.value?.email_verified_at)

  async function changePassword(current_password: string, password: string, password_confirmation: string) {
    await $api.patch('/v1/profile/password', {
      current_password,
      password,
      password_confirmation
    });
  }

  async function deleteAccount(password: string) {
    await $api.delete('/account', {
      password,
    });
  }

  return {
    token,
    user,
    isVerified,
    register,
    login,
    me,
    logout,
    changePassword,
    deleteAccount,
  }
}
