// frontendApp/stores/event.ts
import { defineStore } from 'pinia'
import type { EventDTO } from '~/types/event'

export const useEventStore = defineStore('events', () => {
  const { $api } = useNuxtApp() as any
  const list = ref<EventDTO[]>([])
  const byId = ref<Record<number, EventDTO>>({})
  const loading = ref(false)

  async function fetchGroup(groupId: number) {
    loading.value = true
    try {
      const { data } = await $api.get(`/v1/groups/${groupId}/events`)
      list.value = data.data ?? data // (resource collection ili plain)
      for (const e of list.value) byId.value[e.id] = e
    } finally {
      loading.value = false
    }
  }

  async function getOne(id: number) {
    if (byId.value[id]) return byId.value[id]
    const { data } = await $api.get(`/v1/events/${id}`)
    byId.value[id] = data.data ?? data
    return byId.value[id]
  }

  async function create(groupId: number, payload: Partial<EventDTO>) {
    const { data } = await $api.post(`/v1/groups/${groupId}/events`, payload)
    const ev = data.data ?? data
    byId.value[ev.id] = ev
    list.value.unshift(ev)
    return ev as EventDTO
  }

  async function rsvp(eventId: number, choice: 'yes'|'no'|'undecided', reason?: string) {
    await $api.post(`/v1/events/${eventId}/rsvp`, {
      rsvp: choice,
      reason
    });
    // po želji refetch/show toast
  }

  async function checkin(eventId: number) {
    await $api.post(`/v1/events/${eventId}/checkin`)
  }

  return {
    list,
    byId,
    loading,
    fetchGroup,
    getOne,
    create,
    rsvp,
    checkin
  }
});
