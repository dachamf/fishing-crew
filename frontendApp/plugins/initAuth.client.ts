export default defineNuxtPlugin(async () => {
  const { user, me } = useAuth();
  if (!user.value) {
    try {
      await me();
    }
    catch {
      // not logged in or cookie expired
    }
  }
});
