export function useAuth() {
  type User = { id:number; name:string; email:string; email_verified_at?: string | null }
  const token = useCookie<string|undefined>('token');
  const user  = useState<User|null>('user', () => null)
  const { $api } = useNuxtApp() as any

  async function register(name: string, email: string, password: string, password_confirmation: string) {
    const { data } = await $api.post('/auth/register', {
      name, email, password, password_confirmation,
    })
    token.value = data.token
    await me(true)
  }

  function setToken(value: string | undefined, remember = false) {
    // Kad SETujemo cookie, možemo proći kroz useCookie sa opcijama
    const t = useCookie<string | undefined>('token', {
      path: '/',
      sameSite: 'lax',
      secure: true,
      // 30 dana ako je Remember me
      maxAge: remember ? 60 * 60 * 24 * 30 : undefined
    })
    t.value = value
  }

  async function login(email: string, password: string, remember = false) {
    const { data } = await $api.post('/auth/login', { email, password })
    setToken(data.token, remember)
    // odmah pozovi me() sa eksplicitnim headerom kao fallback
    await me(true)
  }

  async function me(forceHeader = false) {
    const headers = (forceHeader && token.value)
      ? { Authorization: `Bearer ${token.value}` }
      : undefined
    const { data } = await $api.get('/user', { headers })
    user.value = data
  }

async function logout() {
    setToken(undefined);
    user.value = null;

    navigateTo('/login');
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
