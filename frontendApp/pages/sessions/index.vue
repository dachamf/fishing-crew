<script setup lang="ts">
definePageMeta({ name: 'sessions' })
const params = ref<Record<string, any>>({ page: 1, per_page: 20 })
const { list, data, pending, error } = useSessions(params)
</script>

<template>
  <div class="container mx-auto p-4">
    <div v-if="pending" class="loading loading-spinner"></div>
    <div v-if="pending">Učitavanje…</div>
    <div v-else-if="error" class="alert alert-error">Greška</div>
    <div v-else>
      <div v-for="s in list" :key="s.id" class="card bg-base-100 shadow">
        <div class="card-body">
          <div class="flex justify-between">
            <div>
              <h2 class="card-title">{{ s.title || 'Bez naslova' }}</h2>
              <div class="badge badge-neutral">{{ s.status }}</div>
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
