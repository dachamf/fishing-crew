export function useHydrated() {
  const hydrated = useState<boolean>("__hydrated__", () => false);
  onMounted(() => {
    hydrated.value = true;
  });
  return hydrated;
}
