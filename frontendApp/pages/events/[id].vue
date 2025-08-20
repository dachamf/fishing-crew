<script setup lang="ts">
const route = useRoute()
const { $api } = useNuxtApp() as any
const { data: event, refresh } = await useAsyncData(`event-${route.params.id}`, async () => (await $api.get(`/events/${route.params.id}`)).data)
const open = ref(false)
async function rsvp(choice:'yes'|'no'|'undecided'){
  open.value = false
  await $api.post(`/events/${route.params.id}/rsvp`, { rsvp: choice })
  await refresh()
}
</script>
<template>
  <div class="p-6">
    <h1 class="text-2xl font-semibold">{{ event?.title }}</h1>
    <p class="text-sm opacity-70">{{ event?.location_name }} · {{ new Date(event?.start_at).toLocaleString() }}</p>
    <div class="mt-4 join">
      <button class="btn join-item" @click="open=true">RSVP</button>
    </div>
    <RSVPConfirm v-model="open" @confirm="rsvp('yes')" />
  </div>
</template>
