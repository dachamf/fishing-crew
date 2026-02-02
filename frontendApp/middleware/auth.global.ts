export default defineNuxtRouteMiddleware(async (to) => {
  // dozvoli javne stranice
  if (
    to.meta.public === true
    || to.path.startsWith("/login")
    || to.path.startsWith("/register")
    || to.path.startsWith("/verify")
  ) {
    return;
  }

  const { user, me, logout, isVerified } = useAuth();

  // nemamo usera u memoriji -> probaj /v1/user (cookie se šalje automatski)
  if (!user.value) {
    try {
      await me();
    }
    catch {
      logout();
      return navigateTo(`/login?next=${encodeURIComponent(to.fullPath)}`);
    }
  }
  if (!isVerified.value) {
    return navigateTo("/verify");
  }
});
