export function useVisible<T extends HTMLElement = HTMLElement>() {
  const el = shallowRef<T | null>(null);
  const visible = ref(false);
  onMounted(() => {
    const io = new IntersectionObserver(([e]) => (visible.value = !!e?.isIntersecting), {
      rootMargin: "200px 0px",
      threshold: 0.01,
    });
    if (el.value) {
      io.observe(el.value);
    }
    onBeforeUnmount(() => io.disconnect());
  });
  return {
    el,
    visible,
  };
}
