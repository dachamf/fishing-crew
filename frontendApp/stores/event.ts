// stores/event.ts
import { defineStore } from 'pinia'

export type Event = {
  id: number
  group_id: number
  title: string
  start_at: string
  location_name: string | null
  latitude: number | null
  longitude: number | null
  description: string | null
  status: string
  created_at: string
}

export const useEventStore = defineStore('useEventStore', () => {
  const { $api } = useNuxtApp() as any

  const events  = ref<Event[]>([])
  const pending = ref(false)
  const error   = ref<any>(null)

  async function fetchEvents(groupId: number) {
    pending.value = true
    error.value = null
    try {
      const res = await $api.get(`/v1/groups/${groupId}/events`)
      events.value = Array.isArray(res.data) ? res.data : res.data.data
    } catch (e) {
      error.value = e
    } finally {
      pending.value = false
    }
  }

  return { events, pending, error, fetchEvents }
})
