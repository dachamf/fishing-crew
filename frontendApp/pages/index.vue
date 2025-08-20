<script setup lang="ts">
import EventCard from "~/components/event-card.vue";


const eventStore = useEventStore()

onMounted(async () => {
  await eventStore.fetchEvents(1)
})
const { events, pending } = storeToRefs(eventStore)
</script>
<template>
  <div class="p-6 space-y-3">
    <h1 class="text-2xl font-semibold mb-2">Sledeći događaji</h1>
    <div class="flex flex-wrap gap-2 justify-center">
      <div v-if="pending" class="loading loading-dots loading-xl justify-center"></div>
        <EventCard v-for="e in events.values()" :key="e.id" :event="e" />
    </div>
  </div>
</template>
