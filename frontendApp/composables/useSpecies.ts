import type { SpeciesItem } from '~/types/api'

export const useSpecies = () => {
  const { $api } = useNuxtApp() as any
  const { data: list, pending } = useAsyncData<SpeciesItem[]>('species', async () => {
    const res = await $api.get('/v1/species')
    return res.data?.data ?? res.data ?? []
  }, { server: false, immediate: true })

  const safeName = (s: SpeciesItem) => s.name_sr ?? s.label ?? ''

  const byId  = computed<Record<number, string>>(
    () => Object.fromEntries((list.value || [])
      .filter(s => s.id != null)
      .map(s => [s.id as number, safeName(s)]))
  )

  const byKey = computed<Record<string, string>>(
    () => Object.fromEntries((list.value || [])
      .map(s => [
        String(s.key ?? s.code ?? s.slug ?? s.name_sr ?? (s.id ?? '')),
        safeName(s)
      ]))
  )

  function label(row: any): string {
    if (!row) return '-'
    if (typeof row.species === 'string' && row.species) return row.species
    if (row.species?.name_sr) return String(row.species.name_sr)
    if (row.species_name)  return String(row.species_name)
    if (row.species_key && byKey.value[row.species_key]) return byKey.value[row.species_key] || ''
    if (row.species_id && byId.value[row.species_id])    return byId.value[row.species_id] || ''
    return '-'
  }

  return { list, byId, byKey, label, pending }
}

// Ovo traži SpeciesSelect.vue
export const useSpeciesSearch = () => {
  const { list, pending } = useSpecies()
  const q = ref('')

  const items = computed<SpeciesItem[]>(() =>
    (list.value || []).filter(
      s => (s.name_sr ?? s.label ?? '').toLowerCase().includes(q.value.toLowerCase())
    )
  )
  const loading = pending

  return { q, items, loading }
}
