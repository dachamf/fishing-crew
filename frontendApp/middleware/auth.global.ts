export default defineNuxtRouteMiddleware(async (to) => {
  // dozvoli javne stranice
  if (
    to.meta.public === true
    || to.path.startsWith("/login")
    || to.path.startsWith("/register")
    || to.path.startsWith("/verify")
    || to.path.startsWith("/forgotPassword")
    || to.path.startsWith("/resetPassword")
    || to.path.startsWith("/forgot-password")
    || to.path.startsWith("/reset-password")
  ) {
    return;
  }

  const { user, me, logout, isVerified } = useAuth();
  const cookieHeader = import.meta.server ? useRequestHeaders(["cookie"]).cookie : "";
  const hasAuthCookie = !!cookieHeader && cookieHeader.includes("auth_token=");

  // nemamo usera u memoriji -> probaj /v1/user (cookie se šalje automatski)
  if (!user.value) {
    try {
      await me();
    }
    catch (err: any) {
      const status = err?.response?.status;
      if (import.meta.server) {
        // SSR: if we have an auth cookie, avoid false redirects on transient errors
        // but still redirect on explicit auth failures.
        if (!hasAuthCookie || status === 401 || status === 419) {
          return navigateTo(`/login?next=${encodeURIComponent(to.fullPath)}`);
        }
        return;
      }
      logout();
      return navigateTo(`/login?next=${encodeURIComponent(to.fullPath)}`);
    }
  }
  if (!isVerified.value) {
    return navigateTo("/verify");
  }
});
