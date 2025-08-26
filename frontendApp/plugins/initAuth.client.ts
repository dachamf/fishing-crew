export default defineNuxtPlugin(async () => {
  const { token, user, me } = useAuth();
  if (token.value && !user.value) {
    try {
      await me();
    }
    catch {
    }
  }
});
