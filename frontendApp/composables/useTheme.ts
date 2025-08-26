import { computed, getCurrentInstance, onMounted, onUnmounted, watch } from "vue";

import { toErrorMessage } from "~/utils/http";

export type ThemeMode = "light" | "dark" | "system";
const KEY = "hfc:theme";

function readStored(): ThemeMode {
  if (import.meta.server)
    return "light";
  const k = (localStorage.getItem(KEY) as ThemeMode)
    || (document.documentElement.getAttribute("data-theme") as ThemeMode)
    || "light";
  return (k === "light" || k === "dark" || k === "system") ? k : "light";
}

export function useTheme() {
  // držimo stanje u Nuxt state-u (deljeno kroz app)
  const mode = useState<ThemeMode>("theme", () => readStored());
  const { error } = useToast();

  // efektivna boja kad je 'system'
  const resolved = computed<"light" | "dark">(() => {
    if (mode.value !== "system")
      return mode.value;
    if (import.meta.server)
      return "light";
    return window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
  });

  function apply(current: ThemeMode) {
    if (import.meta.client) {
      const real = current === "system" ? resolved.value : current;
      document.documentElement.setAttribute("data-theme", real);
      localStorage.setItem(KEY, current);
    }
  }

  function setTheme(t: ThemeMode) {
    mode.value = t;
    apply(t);
  }

  function toggle() {
    const order: ThemeMode[] = ["light", "dark", "system"];
    const i = order.indexOf(mode.value);
    const idx = ((i + 1) % order.length + order.length) % order.length;
    setTheme(order[idx] ?? "system");
  }

  // registruj hook-ove samo ako smo unutar komponente
  const inComponent = !!getCurrentInstance();
  if (inComponent) {
    onMounted(() => apply(mode.value));

    if (import.meta.client) {
      const mq = window.matchMedia("(prefers-color-scheme: dark)");
      const onChange = () => {
        if (mode.value === "system")
          apply("system");
      };
      mq.addEventListener("change", onChange);
      onUnmounted(() => mq.removeEventListener("change", onChange));
    }
  }
  else {
    // ako smo van komponente (npr. u store-u), samo odmah primeni stanje (bez hook-ova)
    if (import.meta.client)
      apply(mode.value);
  }

  // (opciono) slušaj profil ako postoji, ali bez force importe
  try {
    const { profile } = useProfile();
    watch(() => profile.value?.settings?.theme as ThemeMode | undefined, (t) => {
      if (t)
        setTheme(t);
    }, { immediate: true });
  }
  catch (e) {
    error(toErrorMessage(e));
  }

  return { mode, resolved, setTheme, toggle };
}
