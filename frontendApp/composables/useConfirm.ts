import { render } from "vue";

export function useConfirm() {
  resolveComponent("Dialog");
  const app = useNuxtApp();
  return (opts: {
    title?: string;
    message?: string;
    confirmText?: string;
    cancelText?: string;
    tone?: "default" | "danger";
  }) =>
    new Promise<boolean>((resolve) => {
      const state = reactive({
        open: true,
        ...{ confirmText: "Potvrdi", cancelText: "Otkaži", tone: "default" as const },
        ...opts,
      });

      const container = document.createElement("div");
      document.body.appendChild(container);

      const vnode = h({
        setup() {
          const onClose = () => {
            state.open = false;
            setTimeout(() => {
              render(null, container);
              container.remove();
            }, 0);
          };
          const choose = (val: boolean) => {
            resolve(val);
            onClose();
          };
          return () =>
            h(
              resolveComponent("Dialog"),
              {
                "modelValue": state.open,
                "onUpdate:modelValue": (v: boolean) => {
                  state.open = v;
                  if (!v)
                    choose(false);
                },
                "title": state.title,
                "size": "sm",
              },
              {
                default: () =>
                  h("p", { class: "opacity-80" }, state.message || "Da li si siguran?"),
                footer: () =>
                  h("div", { class: "flex gap-2" }, [
                    h("button", { class: "btn", onClick: () => choose(false) }, state.cancelText),
                    h(
                      "button",
                      {
                        class: ["btn", state.tone === "danger" ? "btn-error" : "btn-primary"],
                        onClick: () => choose(true),
                      },
                      state.confirmText,
                    ),
                  ]),
              },
            );
        },
      } as any);

      // @ts-expect-error -- pogrešan 3. parametar po tipu; koristimo ga kao appContext, ali Vue 3 render očekuje isSVG
      render(vnode, container, app.vueApp?.appContext);
    });
}
