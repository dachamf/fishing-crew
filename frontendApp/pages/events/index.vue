<script setup lang="ts">
const groupId = 1
const store = useEventStore()
const toast = useToast()
useHead({ title: 'Događaji — Fishing Crew' })

onMounted(async () => {
  try { await store.fetchGroup(groupId) } catch { toast.error('Greška pri učitavanju događaja.') }
})
</script>

<template>
  <div class="max-w-4xl mx-auto p-6">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-2xl font-semibold">Događaji</h1>
      <NuxtLink class="btn btn-primary" to="/events/new">Novi</NuxtLink>
    </div>

    <div v-if="store.loading" class="skeleton h-20 mb-3" />
    <div v-else class="space-y-3">
      <div v-for="e in store.list" :key="e.id" class="card bg-base-200 p-4">
        <div class="flex items-center justify-between">
          <div>
            <NuxtLink :to="`/events/${e.id}`" class="font-medium hover:underline">{{ e.title }}</NuxtLink>
            <div class="text-sm opacity-70">
              {{ e.location_name || '—' }} · {{ new Date(e.start_at).toLocaleString() }}
            </div>
          </div>
          <div class="text-xs opacity-70">{{ e.status }}</div>
        </div>
      </div>
      <p v-if="!store.list.length" class="opacity-70">Nema događaja.</p>
    </div>
  </div>
</template>

