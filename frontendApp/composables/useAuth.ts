export function useAuth() {
  type User = {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string | null;
  };
  const { $api } = useNuxtApp() as any;
  const user = useState<User | null>("user", () => null);
  const ready = useState<boolean>("auth_ready", () => false);
  const isLoggedIn = computed(() => !!user.value);

  async function register(
    name: string,
    email: string,
    password: string,
    password_confirmation: string,
  ) {
    const { data } = await $api.post("/auth/register", {
      name,
      email,
      password,
      password_confirmation,
    });
    user.value = data.user;
    await me();
  }

  async function login(email: string, password: string, _remember = false) {
    const { data } = await $api.post("/auth/login", { email, password });
    user.value = data.user;
    await me();
  }

  async function me() {
    try {
      const { data } = await $api.get("/v1/user");
      user.value = data;
      return data;
    }
    finally {
      ready.value = true;
    }
  }

  function logout() {
    user.value = null;
    ready.value = true;
  }

  async function logoutAndRedirect() {
    try {
      await $api.post("/auth/logout");
    }
    catch {
      // ignore - cookie will be cleared by backend
    }
    user.value = null;
    ready.value = true;
    return navigateTo("/login");
  }

  const isVerified = computed(() => !!user.value?.email_verified_at);

  async function changePassword(
    current_password: string,
    password: string,
    password_confirmation: string,
  ) {
    await $api.patch("/v1/profile/password", {
      current_password,
      password,
      password_confirmation,
    });
  }

  async function deleteAccount(password: string) {
    await $api.delete("/account", {
      password,
    });
  }

  return {
    user,
    ready,
    isLoggedIn,
    isVerified,
    register,
    login,
    me,
    logout,
    logoutAndRedirect,
    changePassword,
    deleteAccount,
  };
}
