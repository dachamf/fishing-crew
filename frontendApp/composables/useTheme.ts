import { computed, getCurrentInstance, onMounted, onUnmounted, watch } from "vue";

import { toErrorMessage } from "~/utils/http";

export type ThemeMode = "light" | "dark" | "system";
const KEY = "hfc:theme";

function readStored(): ThemeMode {
  if (import.meta.server)
    return "system";
  const k
    = (localStorage.getItem(KEY) as ThemeMode)
      || (document.documentElement.getAttribute("data-theme") as ThemeMode)
      || "system";
  return k === "light" || k === "dark" || k === "system" ? k : "system";
}

export function useTheme() {
  const mode = useState<ThemeMode>("theme", () => readStored());
  const { error } = useToast();

  const resolved = computed<"light" | "dark">(() => {
    if (mode.value !== "system") {
      return mode.value;
    }
    if (import.meta.server) {
      return "light";
    }
    return window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
  });

  function apply(current: ThemeMode) {
    if (!import.meta.client)
      return;
    const real = current === "system" ? resolved.value : current;
    document.documentElement.setAttribute("data-theme", real);
    localStorage.setItem(KEY, current);
  }

  const setTheme = (t: ThemeMode) => {
    mode.value = t;
    apply(t);
  };
  const setLight = () => setTheme("light");
  const setDark = () => setTheme("dark");
  const setSystem = () => setTheme("system");
  const toggle = () => setTheme(resolved.value === "dark" ? "light" : "dark");

  const inComponent = !!getCurrentInstance();
  if (inComponent) {
    onMounted(() => {
      // ⬇️ posle hidracije, povuci stvarno stanje iz localStorage i primeni
      const stored = readStored();
      if (stored !== mode.value) {
        mode.value = stored;
      }
      apply(stored);
    });

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

  try {
    const { profile } = useProfile();
    watch(
      () => profile.value?.settings?.theme as ThemeMode | undefined,
      (t) => {
        if (t)
          setTheme(t);
      },
      { immediate: true },
    );
  }
  catch (e) {
    error(toErrorMessage(e));
  }

  onMounted(() => {
    const saved = readStored();
    mode.value = saved;
    apply(saved);
  });

  return { mode, resolved, setTheme, setLight, setDark, setSystem, toggle };
}
