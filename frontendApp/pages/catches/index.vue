<script setup lang="ts">
import { watchDebounced } from '@vueuse/core'
import type { FishingSession, FishingCatch, SessionListParams } from '~/types/api'

defineOptions({name: 'CatchListPage'})

const {$api} = useNuxtApp() as any

// 1) Učitaj /v1/me da bismo znali user_id i default group_id
const {data: me} = useAsyncData('me', async () => {
  const res = await $api.get('/v1/me')
  return res.data
}, {server: false, immediate: true})

const query = reactive<SessionListParams & { page: number; per_page: number }>({
  search: '',
  group_id: undefined,
  season_year: undefined,
  page: 1,
  per_page: 10
})

// default group iz /v1/me (ako postoji)
watch(() => me.value, (v) => {
  if (!v) return
  if (!query.group_id && Array.isArray(v.groups) && v.groups.length) {
    query.group_id = v.groups[0].id
  }
}, {immediate: true})

// 3) Parametri za sessions API (uvek tražimo catches.user + photos)
const paramsRef = computed<SessionListParams>(() => ({
  search: query.search || undefined,
  group_id: query.group_id,
  season_year: query.season_year,
  user_id: me.value?.id,                   // moje sesije
  has_catches: 'any',
  page: query.page,
  per_page: query.per_page,
  include: 'catches.user,photos'
}))

const myId = computed(() => me.value?.id);

// 4) Poziv ka useSessions (koji internо radi GET /v1/sessions)
const {data, pending, error, refresh, list: sessions} = useSessions(paramsRef)
const meta = computed(() => data.value?.meta)

// 5) Debounce za search + refresh za ostale filtere
watchDebounced(() => query.search, () => {
  query.page = 1;
  refresh()
}, {debounce: 350})
watch(() => [query.group_id, query.season_year], () => {
  query.page = 1;
  refresh()
})

const closeOpen = ref(false)
function onClosed() {
  // refresh liste/stranice
  refresh()
}

// 6) Paginacija
function goPage(p: number) {
  if (!meta.value) return
  query.page = Math.min(Math.max(1, p), meta.value.last_page || 1)
}

// 7) Agregati iz ulova u SESIJI (filtrira po statusu ulova ako je zadat)
type Agg = { rows: FishingCatch[]; totalCount: number; totalWeight: number; biggest: number }

function getAgg(s: FishingSession): Agg {
  const rowsAll = (s.catches || []) as FishingCatch[]
  const rows = (routeQueryStatus.value
    ? rowsAll.filter(r => r.status === routeQueryStatus.value)
    : rowsAll)

  const totalCount = rows.reduce((a, r) => a + (Number(r.count) || 0), 0)
  const totalWeight = rows.reduce((a, r) => a + (Number(r.total_weight_kg) || 0), 0)
  const biggest = rows.reduce((mx, r) => Math.max(mx, Number(r.biggest_single_kg) || 0), 0)
  return {rows, totalCount, totalWeight, biggest}
}

// (opciono) ako želiš da status ulova ide preko URL-a kao i ostali filteri:
const route = useRoute()
const routeQueryStatus = computed(() => {
  const s = route.query.status as string | undefined
  return (s === 'pending' || s === 'approved' || s === 'rejected') ? s : undefined
})
watch(() => routeQueryStatus.value, () => refresh())
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
        <div class="grid gap-3 md:grid-cols-3">
          <input
            v-model="query.search" type="search" placeholder="Pretraga sesija…"
            class="input input-bordered w-full"
          />
          <select v-model="query.status" class="select select-bordered w-full">
            <option value="">Status ulova (svi)</option>
            <option value="pending">Na čekanju</option>
            <option value="approved">Odobreno</option>
            <option value="rejected">Odbijeno</option>
          </select>
          <input
            v-model.number="query.season_year" type="number" placeholder="Sezona (npr 2025)"
            class="input input-bordered w-full"/>
<!--          <input-->
<!--            v-model.number="query.group_id" type="number" placeholder="ID grupe (opciono)"-->
<!--            class="input input-bordered w-full"/>-->
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
                <button
                  v-if="s.user?.id === myId && s.status === 'open'"
                  class="btn btn-sm btn-outline btn-error"
                  @click="closeOpen = true"
                >
                  Zatvori sesiju
                </button>
                <SessionCloseDialog
                  v-model="closeOpen"
                  :session-id="s.id"
                  :group-id="s.id"
                  @closed="onClosed"
                />
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
                <div class="stat-value text-primary">{{ getAgg(s).totalCount }}</div>
              </div>
              <div class="stat">
                <div class="stat-title">Težina</div>
                <div class="stat-value">{{ getAgg(s).totalWeight.toFixed(1) }} kg</div>
              </div>
              <div class="stat">
                <div class="stat-title">Najveća</div>
                <div class="stat-value">{{ getAgg(s).biggest.toFixed(1) }} kg</div>
              </div>
            </div>
          </div>

          <!-- fotke sesije (max 3) -->
          <div v-if="(s.photos?.length||0) > 0" class="mt-3 grid grid-cols-3 gap-2">
            <div
              v-for="(p, idx) in (s.photos ?? []).slice(0,3)"
              :key="idx"
              class="aspect-video rounded-xl overflow-hidden border border-base-300">
              <img :src="p.url" alt="" class="w-full h-full object-cover" loading="lazy" />
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
              <tr v-for="row in getAgg(s).rows" :key="row.id">
                <td>{{ row.species_label || row.species || row.species_name || '-' }}</td>
                <td class="text-right">{{ row.count }}</td>
                <td class="text-right">{{ Number(row.total_weight_kg || 0).toFixed(1) }}</td>
                <td class="text-right">{{ Number(row.biggest_single_kg || 0).toFixed(1) }}</td>
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
              <div class="stat-value text-primary">{{ getAgg(s).totalCount }}</div>
            </div>
            <div class="stat">
              <div class="stat-title">Težina</div>
              <div class="stat-value">{{ getAgg(s).totalWeight.toFixed(3) }} kg</div>
            </div>
            <div class="stat">
              <div class="stat-title">Najveća</div>
              <div class="stat-value">{{ getAgg(s).biggest.toFixed(3) }} kg</div>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- PAGINACIJA SESIJA -->
    <div v-if="meta" class="join mt-2">
      <button class="btn btn-sm join-item" :disabled="query.page<=1" @click="goPage(1)">«</button>
      <button class="btn btn-sm join-item" :disabled="query.page<=1" @click="goPage(query.page-1)">‹
      </button>
      <button class="btn btn-sm join-item pointer-events-none">
        Str. {{ meta.current_page }} / {{ meta.last_page }}
      </button>
      <button
        class="btn btn-sm join-item"
        :disabled="meta.current_page>=meta.last_page"
        @click="goPage(query.page+1)">›
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
