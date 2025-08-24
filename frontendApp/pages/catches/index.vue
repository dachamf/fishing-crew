<script setup lang="ts">
definePageMeta({ name: 'CatchListPage' })

const { $api } = useNuxtApp() as any

// Ako već imaš useCatchList / useCatches – super; ovde je neutralan fetch:
const queryParams = reactive({
  page: 1,
  per_page: 20,
  include: 'fishing_session,user,images', // backend neka vrati relacije
  // po želji: group_id, season_year, status...
})

const key = computed(() => `catches:${JSON.stringify(toRaw(queryParams))}`)

const { data, pending, error, refresh } = await useAsyncData(
  key,
  async () => {
    // 2) skini Vue proxy da Axios ne dobije reaktivni objekat
    const params = { ...toRaw(queryParams) }
    const res = await $api.get('/v1/catches', { params })
    return res.data?.data ?? res.data ?? []
  },
  {
    // 3) gledaj sam reaktivni objekat (bez spreada)
    watch: [queryParams],
    // optional: server prefetch + immediate po defaultu
  }
)

type CatchItem = {
  id: number
  group_id: number
  user_id: number
  species?: string | null | string[] // defensive
  count?: number
  total_weight_kg?: number | string | null
  biggest_single_kg?: number | string | null
  status?: string
  caught_at?: string | null
  fishing_session?: null | {
    id: number
    started_at?: string | null
    ended_at?: string | null
    note?: string | null
    title?: string | null
  }
  user?: null | { id: number; name?: string | null; avatar_url?: string | null }
  images?: Array<{ id: number; url: string }>
}

const list = computed<CatchItem[]>(() => Array.isArray(data.value) ? data.value : [])

// helper: normalizuj species u set stringova
function extractSpeciesNames(item: CatchItem): string[] {
  const out = new Set<string>()
  if (Array.isArray(item.species)) {
    item.species.forEach((s: any) => out.add(typeof s === 'string' ? s : (s?.name ?? '')))
  } else if (typeof item.species === 'string') {
    out.add(item.species)
  }
  return Array.from(out).filter(Boolean)
}

// GRUPISANJE PO SESIJI.
// - ako ima fishing_session.id => bucket po tom ID-ju
// - ako nema, svaki ulov čini svoj “grupni” zapis (solo)
const grouped = computed(() => {
  const map = new Map<number | string, { session: CatchItem['fishing_session'], catches: CatchItem[] }>()

  for (const c of list.value) {
    const key = c.fishing_session?.id ?? `solo:${c.id}`
    const bucket = map.get(key) ?? { session: c.fishing_session ?? null, catches: [] }
    bucket.catches.push(c)
    map.set(key, bucket)
  }

  // napravi agregate i sortiraj po vremenu
  const groups = Array.from(map.values()).map((b) => {
    const totalCount = b.catches.reduce((s, x) => s + (Number(x.count ?? 1) || 0), 0)
    const totalWeight = b.catches.reduce((s, x) => s + (Number(x.total_weight_kg) || 0), 0)
    const biggest = b.catches.reduce((m, x) => Math.max(m, Number(x.biggest_single_kg) || 0), 0)
    const species = new Set<string>()
    b.catches.forEach((x) => extractSpeciesNames(x).forEach((n) => species.add(n)))

    // vreme za sortiranje (prioritet: session.started_at -> caught_at prvog)
    const sortAt =
      b.session?.started_at ??
      b.catches[0]?.caught_at ??
      null

    return {
      session: b.session,
      catches: b.catches,
      totalCount,
      totalWeight,
      biggest,
      species: Array.from(species),
      sortAt,
    }
  })

  return groups.sort((a, b) => {
    const ta = a.sortAt ? new Date(a.sortAt).getTime() : 0
    const tb = b.sortAt ? new Date(b.sortAt).getTime() : 0
    return tb - ta
  })
})

function speciesPreview(species: string[]) {
  if (!species.length) return '—'
  if (species.length <= 3) return species.join(', ')
  return species.slice(0, 3).join(', ') + ` +${species.length - 3}`
}
</script>

<template>
  <div class="container mx-auto p-4">
    <div class="mb-4 flex items-center justify-between gap-3">
      <h1 class="text-2xl font-semibold">Ulov</h1>
      <div class="flex items-center gap-2">
        <!-- po želji: <NuxtLink to="/sessions/new" class="btn btn-ghost btn-sm">Nova sesija</NuxtLink> -->
      </div>
    </div>
    <NuxtLink to="/catches/new" class="btn btn-primary btn-sm">+ Novi ulov</NuxtLink>
    <div v-if="pending" class="flex items-center gap-2 opacity-70">
      <span class="loading loading-spinner"></span> Učitavanje…
    </div>
    <div v-else-if="error" class="alert alert-error">
      Došlo je do greške pri učitavanju ulova.
    </div>

    <div v-else>
      <div v-if="!grouped.length" class="opacity-60">Nema ulova.</div>

      <div class="grid gap-4 md:gap-6">
        <div
          v-for="(g, idx) in grouped"
          :key="idx"
          class="card bg-base-100 shadow"
        >
          <div class="card-body">
            <!-- HEADER: naslov i vreme -->
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div class="flex items-center gap-2">
                <Icon name="tabler:fishing" class="opacity-70" />
                <h2 class="card-title">
                  {{ g.session ? (g.session.title || 'Ribolovna sesija') : 'Ulov (bez sesije)' }}
                </h2>
              </div>
              <div class="flex items-center gap-2">
                <FishingCatchesTimeBadge :iso="g.session?.started_at || g.catches[0]?.caught_at" />
                <span v-if="g.session?.ended_at" class="badge badge-ghost">kraj: <TimeBadge :iso="g.session.ended_at" /></span>
              </div>
            </div>

            <!-- SUMMARY: ukupno komada / težina / najveća -->
            <div class="mt-2 grid grid-cols-3 gap-2 text-sm">
              <div class="stats shadow w-full">
                <div class="stat">
                  <div class="stat-title">Komada</div>
                  <div class="stat-value text-base">{{ g.totalCount }}</div>
                </div>
              </div>
              <div class="stats shadow w-full">
                <div class="stat">
                  <div class="stat-title">Ukupno kg</div>
                  <div class="stat-value text-base">{{ g.totalWeight.toFixed(3) }}</div>
                </div>
              </div>
              <div class="stats shadow w-full">
                <div class="stat">
                  <div class="stat-title">Najveća (kg)</div>
                  <div class="stat-value text-base">{{ g.biggest.toFixed(3) }}</div>
                </div>
              </div>
            </div>

            <!-- Species chips -->
            <div class="mt-2">
              <div class="opacity-70 text-sm mb-1">Vrste</div>
              <div class="flex flex-wrap gap-2">
                <span v-if="!g.species.length" class="badge badge-ghost">—</span>
                <template v-else>
                  <span v-for="s in g.species.slice(0,6)" :key="s" class="badge badge-outline">{{ s }}</span>
                  <span v-if="g.species.length > 6" class="badge badge-ghost">
                    +{{ g.species.length - 6 }}
                  </span>
                </template>
              </div>
            </div>

            <!-- EXPAND: detalji pojedinačnih ulova -->
            <div class="collapse collapse-arrow mt-3 border border-base-300 rounded-lg">
              <input type="checkbox" />
              <div class="collapse-title text-sm font-medium">
                Detalji ({{ g.catches.length }} zapis{{ g.catches.length === 1 ? '' : 'a' }})
              </div>
              <div class="collapse-content">
                <ul class="divide-y divide-base-300">
                  <li
                    v-for="c in g.catches"
                    :key="c.id"
                    class="py-3 flex flex-col md:flex-row md:items-center md:justify-between gap-3"
                  >
                    <div class="flex items-center gap-3">
                      <div class="avatar">
                        <div class="w-8 rounded-full overflow-hidden ring ring-base-300 ring-offset-2">
                          <img :src="c.user?.avatar_url || '/icons/icon-64.png'" alt="" />
                        </div>
                      </div>
                      <div>
                        <div class="font-medium">{{ c.user?.name || 'Nepoznat' }}</div>
                        <div class="text-xs opacity-70">
                          <TimeBadge :iso="c.caught_at" />
                        </div>
                      </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                      <span class="badge">{{ extractSpeciesNames(c).join(', ') || '—' }}</span>
                      <span class="badge badge-outline">x{{ c.count ?? 1 }}</span>
                      <span class="badge badge-ghost">{{ Number(c.total_weight_kg || 0).toFixed(3) }} kg</span>
                      <span class="badge badge-ghost">max {{ Number(c.biggest_single_kg || 0).toFixed(3) }} kg</span>
                      <NuxtLink :to="`/catches/${c.id}`" class="btn btn-sm btn-ghost">Otvori</NuxtLink>
                    </div>
                  </li>
                </ul>
              </div>
            </div>

            <!-- Footer (po želji): link na sesiju -->
            <div v-if="g.session?.id" class="mt-3 flex justify-end">
              <NuxtLink :to="`/sessions/${g.session.id}`" class="link link-hover text-sm opacity-80">
                Otvori sesiju →
              </NuxtLink>
            </div>
          </div>
        </div>
      </div>

      <!-- paginacija po želji -->
    </div>
  </div>
</template>
