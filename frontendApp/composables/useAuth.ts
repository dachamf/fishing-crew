export function useAuth() {
  type User = any
  const token = useCookie<string|undefined>('token', { sameSite: 'lax' })
  const user  = useState<User|null>('user', () => null)
  const { $api } = useNuxtApp() as any

  async function register(name: string, email: string, password: string, password_confirmation: string) {
    const { data } = await $api.post('/auth/register', {
      name, email, password, password_confirmation,
    })
    token.value = data.token
    await me(true)
  }

  async function login(email: string, password: string) {
    const { data } = await $api.post('/auth/login', { email, password })
    token.value = data.token

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
    token.value = undefined
    user.value = null

    navigateTo('/login');
  }

  return { token, user, register, login, me, logout }
}
