export function useSpeciesSearch() {
  const q = ref('')
  const loading = ref(false)
  const items = ref<Array<{ id: number; name_sr: string; name_latin?: string; slug: string }>>([])

  async function search(term = q.value) {
    loading.value = true
    try {
      const {$api} = useNuxtApp() as any
      const res = await $api.get('/v1/species', {params: {search: term || undefined}})
      items.value = Array.isArray(res.data?.data) ? res.data.data : res.data
    } finally {
      loading.value = false
    }
  }

  watchDebounced(q, () => search(), {debounce: 250})
  onMounted(() => search(''))

  return {q, items, loading, search}
}
