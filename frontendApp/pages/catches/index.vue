<script setup lang="ts">
import { watchDebounced } from '@vueuse/core'

defineOptions({name: 'CatchListPage'})

const {$api} = useNuxtApp() as any

const { label: speciesLabel } = useSpecies()

// 1) Učitamo user-a (radi grupa i user_id)
const {data: me} = await useAsyncData('me', async () => {
  const res = await $api.get('/v1/me')
  return res.data
}, {server: false, immediate: true})

// 2) Query stanje za LISTU SESIJA (ne ulova)
const queryParams = reactive({
  search: '',
  status: '',        // filtrira prikaz ulova u kartici (klijentski)
  group_id: '',      // default iz me.groups[0]
  season_year: '',
  page: 1,
})

watch(() => me.value, (val) => {
  if (!val) return
  if (!queryParams.group_id && Array.isArray(val.groups) && val.groups.length) {
    queryParams.group_id = val.groups[0].id
  }
}, {immediate: true})

// 3) useSessions – tražimo SAMO sesije korisnika (moje)
const sessionParams = computed(() => ({
  search: queryParams.search || undefined,
  group_id: queryParams.group_id || undefined,
  season_year: queryParams.season_year || undefined,
  user_id: me.value?.id || undefined, // moje sesije
  page: queryParams.page,
}))
const {data: sessionsData, pending, error, refresh} = useSessions(sessionParams)
const sessions = computed(() => sessionsData.value?.items ?? [])
const meta = computed(() => sessionsData.value?.meta)

// 4) Za svaku sesiju dohvatimo ulove (cache po ID-u)
const sessionCatches = reactive<Record<number, any[]>>({})

async function loadCatchesForSession(sessionId: number) {
  if (sessionCatches[sessionId]) return
  const res = await $api.get('/v1/catches', {
    params: {fishing_session_id: sessionId, per_page: 100}
  })
  sessionCatches[sessionId] = res.data?.data ?? res.data ?? []
}

// učitaj ulove za sesije na trenutnoj strani (sa blagim limiterom)
  watch(() => sessions.value.map((s: any) => s.id), async (ids) => {
  for (const id of ids) {
    try {
      await loadCatchesForSession(id)
    } catch (e) { /* no-op */
    }
  }
}, {immediate: true})

// 5) Debounce za search (ponovno učitavanje sesija)
watchDebounced(() => queryParams.search, () => {
  queryParams.page = 1;
  refresh()
}, {debounce: 350})

// 6) Ostali filteri -> refresh sesija
watch(() => [queryParams.group_id, queryParams.season_year], () => {
  queryParams.page = 1
  refresh()
})

// 7) Paginacija sesija
function goPage(p: number) {
  if (!meta.value) return
  queryParams.page = Math.min(Math.max(1, p), meta.value.last_page || 1)
}

// 8) Izračuni za zaglavlje kartice (agregati iz ulova)
function getSessionAgg(sessionId: number) {
  const rows = sessionCatches[sessionId] || []
  const filtered = (queryParams.status)
    ? rows.filter((r: any) => r.status === queryParams.status)
    : rows
  const totalCount = filtered.reduce((a: number, r: any) => a + (Number(r.count) || 0), 0)
  const totalWeight = filtered.reduce((a: number, r: any) => a + (Number(r.total_weight_kg) || 0), 0)
  const biggest = filtered.reduce((mx: number, r: any) => Math.max(mx, Number(r.biggest_single_kg) || 0), 0)
  return {rows: filtered, totalCount, totalWeight, biggest}
}
</script>
<template>
  <div class="container mx-auto p-4 space-y-4">

    <div class="flex flex-wrap items-center gap-3 justify-between">
      <h1 class="text-2xl font-semibold">Moje Fishing sesije</h1>
      <NuxtLink to="/catches/new" class="btn btn-primary btn-sm">
        + Novi ulov
      </NuxtLink>
    </div>

    <div class="card bg-base-100 shadow">
      <div class="card-body">
        <div class="grid gap-3 md:grid-cols-4">
          <input
v-model="queryParams.search" type="search" placeholder="Pretraga sesija…"
                 class="input input-bordered w-full"/>
          <select v-model="queryParams.status" class="select select-bordered w-full">
            <option value="">Status ulova (svi)</option>
            <option value="pending">Na čekanju</option>
            <option value="approved">Odobreno</option>
            <option value="rejected">Odbijeno</option>
          </select>
          <input
v-model.number="queryParams.season_year" type="number" placeholder="Sezona (npr 2025)"
                 class="input input-bordered w-full"/>
          <input
v-model.number="queryParams.group_id" type="number" placeholder="ID grupe (opciono)"
                 class="input input-bordered w-full"/>
          <!-- (Opc.) zameni ovo dropdown-om "Moje grupe" -->
        </div>
      </div>
    </div>

    <div v-if="pending" class="flex items-center gap-2">
      <span class="loading loading-spinner"></span> Učitavanje…
    </div>
    <div v-else-if="error" class="alert alert-error">
      Došlo je do greške pri učitavanju.
    </div>
    <div v-else-if="!sessions.length" class="opacity-70">
      Nema sesija za zadate filtere.
    </div>

    <div v-else class="grid gap-4">
      <div v-for="s in sessions" :key="s.id" class="card bg-base-100 shadow">
        <div class="card-body">
          <div class="flex items-start justify-between gap-3">
            <div class="space-y-1">
              <div class="flex items-center gap-2">
                <NuxtLink :to="`/sessions/${s.id}`" class="text-lg font-semibold hover:underline">
                  {{ s.title || 'Fishing sesija' }}
                </NuxtLink>
                <span v-if="s.status" class="badge badge-outline">{{ s.status }}</span>
              </div>
              <div class="flex flex-wrap items-center gap-2 opacity-75">
                <FishingCatchesTimeBadge :iso="s.started_at" :with-time="true"/>
                <span v-if="s.location_name" class="badge badge-ghost">
                  {{ s.location_name }}
                </span>
                <span v-if="s.group?.name" class="badge badge-ghost">
                  {{ s.group.name }}
                </span>
              </div>
            </div>

            <!-- agregati iz ulova u sesiji (posle filtera statusa) -->
            <div class="stats bg-base-200 shadow hidden md:grid">
              <div class="stat">
                <div class="stat-title">Komada</div>
                <div class="stat-value text-primary">{{ getSessionAgg(s.id).totalCount }}</div>
              </div>
              <div class="stat">
                <div class="stat-title">Težina</div>
                <div class="stat-value">{{ getSessionAgg(s.id).totalWeight.toFixed(3) }} kg</div>
              </div>
              <div class="stat">
                <div class="stat-title">Najveća</div>
                <div class="stat-value">{{ getSessionAgg(s.id).biggest.toFixed(3) }} kg</div>
              </div>
            </div>
          </div>

          <!-- fotke sesije (max 3) -->
          <div v-if="(s.photos?.length||0) > 0" class="mt-3 grid grid-cols-3 gap-2">
            <div
v-for="(p, idx) in s.photos.slice(0,3)" :key="idx"
                 class="aspect-video rounded-xl overflow-hidden border border-base-300">
              <img :src="p.url || p" alt="" class="w-full h-full object-cover"/>
            </div>
          </div>

          <div class="divider"></div>

          <!-- UL0VI U OKVIRU SESIJE -->
          <div class="overflow-x-auto">
            <table class="table">
              <thead>
              <tr>
                <th>Vrsta</th>
                <th class="text-right">Kom</th>
                <th class="text-right">Težina (kg)</th>
                <th class="text-right">Najveća (kg)</th>
                <th>Korisnik</th>
                <th>Status</th>
                <th></th>
              </tr>
              </thead>
              <tbody>
              <tr v-for="row in getSessionAgg(s.id).rows" :key="row.id">
                <td>{{ row.species_label || row.species || row.species_name || '-' }}</td>
                <td class="text-right">{{ row.count }}</td>
                <td class="text-right">{{ Number(row.total_weight_kg || 0).toFixed(3) }}</td>
                <td class="text-right">{{ Number(row.biggest_single_kg || 0).toFixed(3) }}</td>
                <td>
                  <div class="flex items-center gap-2">
                    <div class="avatar">
                      <div class="w-6 rounded-full overflow-hidden border border-base-300">
                        <img :src="row.user?.profile?.avatar_url || '/icons/icon-64.png'" alt="">
                      </div>
                    </div>
                    <span class="text-sm">{{ row.user?.display_name || row.user?.name }}</span>
                  </div>
                </td>
                <td>
                    <span
                      class="badge" :class="{
                      'badge-warning': row.status==='pending',
                      'badge-success': row.status==='approved',
                      'badge-error': row.status==='rejected',
                    }">
                      {{ row.status }}
                    </span>
                </td>
                <td class="text-right">
                  <NuxtLink :to="`/catches/${row.id}`" class="btn btn-ghost btn-xs">Detalji ulova</NuxtLink>
                </td>
              </tr>
              </tbody>
            </table>
          </div>

          <!-- mobilni stats -->
          <div class="stats bg-base-200 shadow md:hidden mt-3">
            <div class="stat">
              <div class="stat-title">Komada</div>
              <div class="stat-value text-primary">{{ getSessionAgg(s.id).totalCount }}</div>
            </div>
            <div class="stat">
              <div class="stat-title">Težina</div>
              <div class="stat-value">{{ getSessionAgg(s.id).totalWeight.toFixed(3) }} kg</div>
            </div>
            <div class="stat">
              <div class="stat-title">Najveća</div>
              <div class="stat-value">{{ getSessionAgg(s.id).biggest.toFixed(3) }} kg</div>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- PAGINACIJA SESIJA -->
    <div v-if="meta" class="join mt-2">
      <button class="btn btn-sm join-item" :disabled="queryParams.page<=1" @click="goPage(1)">«</button>
      <button class="btn btn-sm join-item" :disabled="queryParams.page<=1" @click="goPage(queryParams.page-1)">‹
      </button>
      <button class="btn btn-sm join-item pointer-events-none">
        Str. {{ meta.current_page }} / {{ meta.last_page }}
      </button>
      <button
        class="btn btn-sm join-item"
        :disabled="meta.current_page>=meta.last_page"
        @click="goPage(queryParams.page+1)">›
      </button>
      <button
        class="btn btn-sm join-item"
        :disabled="meta.current_page>=meta.last_page"
        @click="goPage(meta.last_page)"
      >»
      </button>
    </div>

  </div>
</template>
