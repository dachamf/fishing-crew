import { useToast } from "#imports";
import axios from "axios";

import { toErrorMessage } from "~/utils/http";

export default defineNuxtPlugin((nuxtApp) => {
  const config = useRuntimeConfig();

  const api = axios.create({
    baseURL: config.public.apiBase,
    withCredentials: true,
  });

  api.interceptors.request.use((cfg) => {
    cfg.headers.Accept = "application/json";
    cfg.headers["X-Requested-With"] = "XMLHttpRequest";
    if (import.meta.server) {
      const { cookie, origin, referer } = useRequestHeaders(["cookie", "origin", "referer"]);
      if (cookie) {
        cfg.headers.cookie = cookie;
      }
      if (origin) {
        cfg.headers.origin = origin;
      }
      if (referer) {
        cfg.headers.referer = referer;
      }
    }
    return cfg;
  });

  api.interceptors.response.use(
    res => res,
    async (err) => {
      if (import.meta.client) {
        const { error } = useToast();
        error(toErrorMessage(err));
      }
      // ako BE nekad vrati 400 umesto 401, uhvati i to
      const status = err.response?.status;
      if (status === 401 || status === 419) {
        if (import.meta.server) {
          return Promise.reject(err);
        }
        const { ready } = useAuth();
        if (!ready.value) {
          return Promise.reject(err);
        }
        const { logout } = useAuth();
        logout();
        await nuxtApp.runWithContext(() =>
          navigateTo(`/login?next=${encodeURIComponent(useRoute().fullPath)}`),
        );
      }
      return Promise.reject(err);
    },
  );

  return { provide: { api } };
});
