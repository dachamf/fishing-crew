<script lang="ts" setup>
import type { ID, LeaderboardItem, Me } from "~/types/api";

// helperi
function displayName(u?: { display_name?: string; name?: string } | null) {
  return u?.display_name || u?.name || "—";
}
function avatarOf(u?: { avatar_url?: string | null } | null) {
  return u?.avatar_url || "/icons/icon-64.png";
}
function kg(n?: number | null) {
  return `${Number(n || 0).toFixed(3)} kg`;
}

// api
const { $api } = useNuxtApp() as any;

// me -> grupe za dropdown
const { data: me } = await useAsyncData<Me>(
  "me:leaderboard",
  async () => {
    const r = await $api.get("/v1/me");
    return r.data;
  },
  { server: false },
);

// query (default: prva grupa + tekuća sezona)
const query = reactive({
  group_id: (me.value?.groups?.[0]?.id ?? null) as ID | null,
  season_year: new Date().getFullYear(),
});

// fetch leaderboard (jedan endpoint, FE sortira u tri liste)
const key = computed(() => `leaderboard:${query.group_id}:${query.season_year}`);
const { data, pending, error, refresh } = await useAsyncData<{ items: LeaderboardItem[] }>(
  key,
  async () => {
    if (!query.group_id)
      return { items: [] };
    const r = await $api.get("/v1/leaderboard", {
      params: { group_id: query.group_id, season_year: query.season_year },
    });
    // očekujemo { items: LeaderboardItem[] } ili raw niz
    const arr = r.data?.items ?? r.data ?? [];
    return { items: arr as LeaderboardItem[] };
  },
  { watch: [() => query.group_id, () => query.season_year], server: false },
);

const rows = computed<LeaderboardItem[]>(() => data.value?.items ?? []);

// tri “pogleda”
const activityRows = computed(() =>
  [...rows.value].sort((a, b) => (b.sessions_total ?? 0) - (a.sessions_total ?? 0)),
);
const weightRows = computed(() =>
  [...rows.value].sort((a, b) => (b.weight_total ?? 0) - (a.weight_total ?? 0)),
);
const biggestRows = computed(() =>
  [...rows.value].sort((a, b) => (b.biggest ?? 0) - (a.biggest ?? 0)),
);
</script>

<template>
  <div class="container mx-auto p-4 space-y-4">
    <div class="flex items-center justify-between gap-3">
      <h1 class="text-2xl font-semibold">
        Leaderboard
      </h1>
      <NuxtLink class="btn btn-ghost btn-sm" to="/catches">
        ← Ulov
      </NuxtLink>
    </div>

    <!-- Filteri -->
    <div class="card bg-base-100 shadow">
      <div class="card-body grid gap-3 md:grid-cols-3">
        <div>
          <label class="label">Grupa</label>
          <select v-model.number="query.group_id" class="select select-bordered w-full">
            <option
              v-for="g in me?.groups || []"
              :key="g.id"
              :value="g.id"
            >
              {{ g.name }} ({{ g.season_year || '—' }})
            </option>
          </select>
        </div>
        <div>
          <label class="label">Sezona</label>
          <input
            v-model.number="query.season_year"
            class="input input-bordered w-full"
            type="number"
          >
        </div>
        <div class="flex items-end">
          <button class="btn btn-outline w-full" @click="refresh()">
            Osveži
          </button>
        </div>
      </div>
    </div>

    <!-- Loading / Error -->
    <div v-if="pending" class="grid md:grid-cols-3 gap-4">
      <div class="card bg-base-100 shadow md:col-span-3">
        <div class="card-body">
          <div class="skeleton h-6 w-40 mb-2" />
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="skeleton h-32" />
            <div class="skeleton h-32" />
            <div class="skeleton h-32" />
          </div>
          <div class="mt-3 space-y-2">
            <div
              v-for="i in 6"
              :key="i"
              class="skeleton h-8"
            />
          </div>
        </div>
      </div>
    </div>
    <div v-else-if="error" class="alert alert-error">
      Greška pri učitavanju.
    </div>

    <!-- Sadržaj -->
    <div v-else>
      <LeaderboardTabs
        :activity="activityRows"
        :biggest="biggestRows"
        :weight="weightRows"
      >
        <!-- Aktivnost (po broju sesija) -->
        <template #activity="{ rows: rowsActivity }">
          <div class="grid md:grid-cols-3 gap-3">
            <div
              v-for="(r, idx) in rowsActivity.slice(0, 3)"
              :key="r.user?.id"
              class="card bg-base-100 shadow"
            >
              <div class="card-body items-center text-center">
                <div class="avatar">
                  <div class="w-16 rounded-full border border-base-300 overflow-hidden">
                    <img :src="avatarOf(r.user)" alt="">
                  </div>
                </div>
                <div class="text-sm opacity-70 mt-1">
                  #{{ idx + 1 }}
                </div>
                <div class="font-medium truncate">
                  {{ displayName(r.user) }}
                </div>
                <div class="opacity-80">
                  Sesije: <b>{{ r.sessions_total ?? 0 }}</b>
                </div>
              </div>
            </div>
          </div>

          <div class="overflow-x-auto mt-4">
            <table class="table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Korisnik</th>
                  <th class="text-right">
                    Sesije
                  </th>
                  <th class="text-right">
                    Ulov(a)
                  </th>
                  <th class="text-right">
                    Komada
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(r, idx) in rowsActivity" :key="r.user?.id ?? idx">
                  <td class="w-12">
                    {{ idx + 1 }}
                  </td>
                  <td>
                    <div class="flex items-center gap-2 min-w-[180px]">
                      <div class="avatar">
                        <div class="w-8 rounded-full border border-base-300 overflow-hidden">
                          <img :src="avatarOf(r.user)" alt="">
                        </div>
                      </div>
                      <span class="truncate">{{ displayName(r.user) }}</span>
                    </div>
                  </td>
                  <td class="text-right">
                    {{ r.sessions_total ?? 0 }}
                  </td>
                  <td class="text-right">
                    {{ r.catches_count ?? 0 }}
                  </td>
                  <td class="text-right">
                    {{ r.pieces_total ?? 0 }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>

        <!-- Ukupna težina -->
        <template #weight="{ rows: rowsWeight }">
          <div class="grid md:grid-cols-3 gap-3">
            <div
              v-for="(r, idx) in rowsWeight.slice(0, 3)"
              :key="r.user?.id"
              class="card bg-base-100 shadow"
            >
              <div class="card-body items-center text-center">
                <div class="avatar">
                  <div class="w-16 rounded-full border border-base-300 overflow-hidden">
                    <img :src="avatarOf(r.user)" alt="">
                  </div>
                </div>
                <div class="text-sm opacity-70 mt-1">
                  #{{ idx + 1 }}
                </div>
                <div class="font-medium truncate">
                  {{ displayName(r.user) }}
                </div>
                <div class="opacity-80">
                  Ukupno: <b>{{ kg(r.weight_total) }}</b>
                </div>
              </div>
            </div>
          </div>

          <div class="overflow-x-auto mt-4">
            <table class="table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Korisnik</th>
                  <th class="text-right">
                    Uk. težina
                  </th>
                  <th class="text-right">
                    Ulov(a)
                  </th>
                  <th class="text-right">
                    Najveća
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(r, idx) in rowsWeight" :key="r.user?.id ?? idx">
                  <td class="w-12">
                    {{ idx + 1 }}
                  </td>
                  <td>
                    <div class="flex items-center gap-2 min-w-[180px]">
                      <div class="avatar">
                        <div class="w-8 rounded-full border border-base-300 overflow-hidden">
                          <img :src="avatarOf(r.user)" alt="">
                        </div>
                      </div>
                      <span class="truncate">{{ displayName(r.user) }}</span>
                    </div>
                  </td>
                  <td class="text-right">
                    {{ kg(r.weight_total) }}
                  </td>
                  <td class="text-right">
                    {{ r.catches_count ?? 0 }}
                  </td>
                  <td class="text-right">
                    {{ kg(r.biggest) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>

        <!-- Najveći primerak -->
        <template #biggest="{ rows: rowsBiggest }">
          <div class="grid md:grid-cols-3 gap-3">
            <div
              v-for="(r, idx) in rowsBiggest.slice(0, 3)"
              :key="r.user?.id"
              class="card bg-base-100 shadow"
            >
              <div class="card-body items-center text-center">
                <div class="avatar">
                  <div class="w-16 rounded-full border border-base-300 overflow-hidden">
                    <img :src="avatarOf(r.user)" alt="">
                  </div>
                </div>
                <div class="text-sm opacity-70 mt-1">
                  #{{ idx + 1 }}
                </div>
                <div class="font-medium truncate">
                  {{ displayName(r.user) }}
                </div>
                <div class="opacity-80">
                  Najveća: <b>{{ kg(r.biggest) }}</b>
                </div>
              </div>
            </div>
          </div>

          <div class="overflow-x-auto mt-4">
            <table class="table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Korisnik</th>
                  <th class="text-right">
                    Najveća
                  </th>
                  <th class="text-right">
                    Uk. težina
                  </th>
                  <th class="text-right">
                    Ulov(a)
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(r, idx) in rowsBiggest" :key="r.user?.id ?? idx">
                  <td class="w-12">
                    {{ idx + 1 }}
                  </td>
                  <td>
                    <div class="flex items-center gap-2 min-w-[180px]">
                      <div class="avatar">
                        <div class="w-8 rounded-full border border-base-300 overflow-hidden">
                          <img :src="avatarOf(r.user)" alt="">
                        </div>
                      </div>
                      <span class="truncate">{{ displayName(r.user) }}</span>
                    </div>
                  </td>
                  <td class="text-right">
                    {{ kg(r.biggest) }}
                  </td>
                  <td class="text-right">
                    {{ kg(r.weight_total) }}
                  </td>
                  <td class="text-right">
                    {{ r.catches_count ?? 0 }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>
      </LeaderboardTabs>

      <!-- prazno stanje -->
      <div v-if="!rows.length" class="opacity-70">
        Nema podataka za odabranu grupu/sezonu.
      </div>
    </div>
  </div>
</template>
