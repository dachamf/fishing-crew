<script setup lang="ts">
definePageMeta({ name: 'sessions' })
const { list } = useSessions()
const { data, pending, error, refresh } = await useAsyncData('sessions', () => list({}), { server: true })
</script>

<template>
  <div class="container mx-auto p-4">
    <div v-if="pending" class="loading loading-spinner"></div>
    <div v-else-if="error" class="alert alert-error">Greška pri učitavanju sesija.</div>

    <div v-else class="grid gap-4">
      <div v-for="s in data?.data" :key="s.id" class="card bg-base-100 shadow">
        <div class="card-body">
          <div class="flex justify-between">
            <div>
              <h2 class="card-title">{{ s.title || 'Bez naslova' }}</h2>
              <div class="badge badge-neutral">{{ s.status }}</div>
              <div class="opacity-70 text-sm">{{ s.water_body || 'N/A' }}</div>
            </div>
            <NuxtLink class="btn btn-sm btn-outline" :to="`/sessions/${s.id}`">Otvori</NuxtLink>
          </div>
          <div class="text-xs opacity-60">
            Počela: {{ new Date(s.started_at).toLocaleString('sr-RS') }}
            <span v-if="s.ended_at"> • Završenа: {{ new Date(s.ended_at).toLocaleString('sr-RS') }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
