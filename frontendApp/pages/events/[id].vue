<script setup lang="ts">

const route = useRoute()
const id = Number(route.params.id)
const store = useEventStore()
const toast = useToast()
const event = ref<any>(null)

useHead({title: 'Događaj — Fishing Crew'})

onMounted(async () => {
  try {
    event.value = await store.getOne(id)
  } catch {
    toast.error('Greška pri učitavanju.')
  }
})

async function rsvp(choice: 'yes' | 'no' | 'undecided') {
  try {
    await store.rsvp(id, choice)
    toast.success('Sačuvano.')
  } catch {
    toast.error('Greška pri RSVP.')
  }
}

async function checkin() {
  try {
    await store.checkin(id)
    toast.success('Check-in uspešan.')
  } catch {
    toast.error('Greška pri check-in.')
  }
}
</script>

<template>
  <div class="flex flex-col min-h-screen"> <!-- umesto h-full -->
    <div v-if="event" class="max-w-3xl mx-auto p-6">
      <h1 class="text-2xl font-semibold">{{ event.title }}</h1>
      <p class="opacity-70">
        {{ event.location_name || '—' }} · {{ new Date(event.start_at).toLocaleString() }}
      </p>

      <div class="mt-4 flex gap-2">
        <button class="btn btn-primary" @click="rsvp('yes')">Dolazim</button>
        <button class="btn" @click="rsvp('undecided')">Nisam siguran</button>
        <button class="btn btn-ghost" @click="rsvp('no')">Ne mogu</button>
        <button class="btn btn-accent ml-auto" @click="checkin()">Check-in</button>
      </div>

      <div v-if="event.description" class="prose prose-sm mt-6 max-w-none">
        <p>{{ event.description }}</p>
      </div>
    </div>

    <!-- wrapper koji raste i dozvoljava shrink (min-h-0 je ključ!) -->
    <section class="min-h-0 overflow-hidden">
<!--Prostor za mapu -->
    </section>
  </div>
</template>
