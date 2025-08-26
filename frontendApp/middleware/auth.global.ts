export default defineNuxtRouteMiddleware(async (to) => {
  // dozvoli javne stranice
  if (to.meta.public === true || to.path.startsWith("/login") || to.path.startsWith("/register") || to.path.startsWith("/verify")) {
    return;
  }

  const {
    token,
    user,
    me,
    logout,
    isVerified,
  } = useAuth();

  // nema token -> na login sa "next" parametrom
  if (!token.value) {
    return navigateTo(`/login?next=${encodeURIComponent(to.fullPath)}`);
  }

  // imamo token ali nemamo usera u memoriji -> probaj /auth/me
  if (!user.value) {
    try {
      await me();
    }
    catch {
      await logout();
      return navigateTo(`/login?next=${encodeURIComponent(to.fullPath)}`);
    }
  }
  if (!isVerified.value) {
    return navigateTo("/verify");
  }
});
