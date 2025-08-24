<script setup lang="ts">
const route = useRoute()
const id = Number(route.params.id)
const { getOne, closeSession } = useSessions()
const { data, pending, error, refresh } = await useAsyncData(
  () => `session:${id}`,
  () => getOne(id),
  { watch: [() => id] }
)

const s = computed(() => data.value)
async function onClose() { await closeSession(id); await refresh() }
</script>

<template>
  <div class="container mx-auto p-4">
    <div v-if="pending" class="loading loading-spinner"></div>
    <div v-else-if="error" class="alert alert-error">Greška pri učitavanju.</div>
    <div v-else-if="!s" class="alert">Nema podataka.</div>

    <div v-else class="grid lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 space-y-4">
        <div class="card bg-base-100 shadow">
          <div class="card-body">
            <div class="flex justify-between items-start">
              <div>
                <h1 class="card-title text-2xl">{{ s.title || 'Sesija' }}</h1>
                <div class="flex items-center gap-2">
                  <span class="badge">{{ s.status }}</span>
                  <span class="opacity-60 text-sm">{{ s.water_body || '—' }}</span>
                </div>
              </div>
              <button v-if="s.status==='open'" class="btn btn-sm btn-warning" @click="onClose">Završi sesiju</button>
            </div>

            <div class="text-xs opacity-60">
              Početak: {{ new Date(s.started_at).toLocaleString('sr-RS') }}
              <span v-if="s.ended_at"> • Kraj: {{ new Date(s.ended_at).toLocaleString('sr-RS') }}</span>
            </div>
          </div>
        </div>

        <div class="card bg-base-100 shadow">
          <div class="card-body">
            <h2 class="card-title mb-2">Brz unos ulova</h2>
            <SessionQuickAdd :session-id="id" />
          </div>
        </div>

        <div class="card bg-base-100 shadow">
          <div class="card-body">
            <h2 class="card-title mb-2">Ulov</h2>
            <div v-if="s.catches?.length" class="grid gap-2">
              <div v-for="c in s.catches" :key="c.id" class="flex justify-between items-center p-2 rounded-lg border">
                <div>
                  <div class="font-semibold">{{ c.species }}</div>
                  <div class="text-xs opacity-70">
                    Kom: {{ c.count }} • Težina: {{ c.total_weight_kg ?? 0 }} kg • Max: {{ c.biggest_single_kg ?? 0 }} kg
                  </div>
                </div>
                <div class="text-xs opacity-60">{{ c.caught_at ? new Date(c.caught_at).toLocaleString('sr-RS') : '' }}</div>
              </div>
            </div>
            <div v-else class="opacity-70 text-sm">Još nema ulova.</div>
          </div>
        </div>
      </div>

      <div class="lg:col-span-1">
        <div class="card bg-base-100 shadow">
          <div class="card-body">
            <h2 class="card-title">Lokacija</h2>
            <div class="rounded-xl overflow-hidden border border-base-300">
              <MapCoordPicker
                v-if="s.latitude!=null && s.longitude!=null"
                :coords="{ lng: Number(s.longitude), lat: Number(s.latitude) }"
                :editable="false"
              />
              <div v-else class="p-4 text-sm opacity-70">Lokacija nije setovana.</div>
            </div>
            <div v-if="s.latitude!=null && s.longitude!=null" class="mt-2 text-xs opacity-70">
              {{ Number(s.longitude).toFixed(6) }}, {{ Number(s.latitude).toFixed(6) }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
