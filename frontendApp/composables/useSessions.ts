type Session = {
  id:number; status:'open'|'closed'; start_at:string|null; end_at:string|null;
  group_id:number; event_id?:number|null; location_name?:string|null;
  latitude?:number|null; longitude?:number|null;
}

export function useMySessions() {
  const {$api} = useNuxtApp() as any
  const open = ref<Session|null>(null)
  const recent = ref<Session[]>([])
  const loading = ref(false)

  async function fetchAll() {
    loading.value = true
    try {
      // pretpostavka: /v1/sessions?mine=1
      const [a,b] = await Promise.all([
        $api.get('/v1/sessions', { params: { mine: 1, status: 'open', limit: 1 } }),
        $api.get('/v1/sessions', { params: { mine: 1, status: 'closed', limit: 10 } }),
      ])
      const arrOpen = a.data?.data ?? a.data ?? []
      open.value = arrOpen[0] ?? null
      recent.value = b.data?.data ?? b.data ?? []
    } finally { loading.value = false }
  }

  async function startNew(groupId:number, payload:Partial<Session>={}) {
    const res = await $api.post('/v1/sessions', { group_id: groupId, ...payload })
    await fetchAll()
    return res.data
  }

  async function closeSession(id:number, endAt?:string) {
    await $api.post(`/v1/sessions/${id}/close`, { end_at: endAt })
    await fetchAll()
  }

  onMounted(fetchAll)
  return { open, recent, loading, fetchAll, startNew, closeSession }
}
