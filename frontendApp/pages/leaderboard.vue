<script lang="ts" setup>
import type { LeaderboardItem } from "~/types/api";

function displayName(u?: { display_name?: string; name?: string } | null) {
  return u?.display_name || u?.name || "—";
}
function avatarOf(u?: { avatar_url?: string | null } | null) {
  return u?.avatar_url || "/icons/icon-64.png";
}
const nf = new Intl.NumberFormat("sr-RS", { minimumFractionDigits: 3, maximumFractionDigits: 3 });
function kg(n?: number | null) {
  return `${nf.format(Number(n || 0))} kg`;
}

const { $api } = useNuxtApp() as any;

// ✅ Single-tenant: nema group_id u query-ju
const query = reactive({
  season_year: new Date().getFullYear(),
});

const key = computed(() => `leaderboard:season=${query.season_year}`);

const { data, pending, error, refresh } = await useAsyncData<{ items: LeaderboardItem[] }>(
  key,
  async () => {
    const r = await $api.get("/v1/leaderboard", {
      params: { season_year: query.season_year }, // ⬅️ bez group_id
    });
    const raw = r.data;
    const items = Array.isArray(raw?.items) ? raw.items : Array.isArray(raw) ? raw : [];
    return { items: items as LeaderboardItem[] };
  },
  { watch: [() => query.season_year], server: false },
);

const hydrated = ref(false);
onMounted(() => (hydrated.value = true));

const refreshing = ref(false);
async function doRefresh() {
  try {
    refreshing.value = true;
    await refresh();
  }
  finally {
    refreshing.value = false;
  }
}

const rows = computed<LeaderboardItem[]>(() =>
  Array.isArray(data.value?.items) ? data.value!.items : [],
);
const activityRows = computed(() =>
  [...rows.value].sort((a, b) => (b.sessions_total ?? 0) - (a.sessions_total ?? 0)),
);
const weightRows = computed(() =>
  [...rows.value].sort((a, b) => (b.weight_total ?? 0) - (a.weight_total ?? 0)),
);
const biggestRows = computed(() =>
  [...rows.value].sort((a, b) => (b.biggest ?? 0) - (a.biggest ?? 0)),
);

// Trophy helpers (gold/silver/bronze) za TOP 3
function rankRingClass(idx: number) {
  return [
    "ring-2",
    idx === 0 ? "ring-amber-400" : idx === 1 ? "ring-zinc-300" : "ring-amber-700",
  ].join(" ");
}
function rankIconClass(idx: number) {
  return idx === 0 ? "text-amber-400" : idx === 1 ? "text-zinc-300" : "text-amber-700";
}
function rankLabel(idx: number) {
  return idx === 0 ? "Zlato" : idx === 1 ? "Srebro" : "Bronza";
}
</script>

<template>
  <div class="container mx-auto p-4 space-y-4">
    <div class="flex items-center justify-between gap-3">
      <h1 class="text-2xl font-semibold">
        Rang lista
      </h1>
      <NuxtLink class="btn btn-ghost btn-sm" to="/catches">
        ← Ulov
      </NuxtLink>
    </div>

    <!-- Filteri (bez grupe) -->
    <div class="card bg-base-100 shadow">
      <div class="card-body grid gap-3 md:grid-cols-3">
        <div class="md:col-span-2">
          <label class="label">Sezona</label>
          <input
            v-model.number="query.season_year"
            class="input input-bordered w-full"
            type="number"
          >
        </div>
        <div class="flex items-end">
          <button
            class="btn btn-outline w-full"
            :class="{ loading: refreshing }"
            :disabled="refreshing"
            @click="doRefresh"
          >
            Osveži
          </button>
        </div>
      </div>
    </div>

    <!-- Loading / Error -->
    <div v-if="!hydrated || pending" class="grid md:grid-cols-3 gap-4">
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
              :key="r.user?.id ?? idx"
              class="card bg-base-100 shadow"
            >
              <div
                class="card-body items-center text-center rounded-box"
                :class="rankRingClass(idx)"
              >
                <div class="avatar">
                  <div
                    class="w-16 rounded-full border border-base-300 overflow-hidden"
                    :class="rankRingClass(idx)"
                  >
                    <img :src="avatarOf(r.user)" alt="">
                  </div>
                </div>

                <!-- Pehar + pozicija -->
                <div class="mt-2 flex items-center justify-center gap-2">
                  <Icon
                    name="tabler:trophy"
                    size="32"
                    :class="rankIconClass(idx)"
                    :title="rankLabel(idx)"
                  />
                  <div class="text-sm opacity-70">
                    #{{ idx + 1 }}
                  </div>
                </div>

                <div class="font-medium truncate mt-1">
                  {{ displayName(r.user) }}
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
              :key="r.user?.id ?? idx"
              class="card bg-base-100 shadow"
            >
              <div
                class="card-body items-center text-center rounded-box"
                :class="rankRingClass(idx)"
              >
                <div class="avatar">
                  <div
                    class="w-16 rounded-full border border-base-300 overflow-hidden"
                    :class="rankRingClass(idx)"
                  >
                    <img :src="avatarOf(r.user)" alt="">
                  </div>
                </div>

                <!-- Pehar + pozicija -->
                <div class="mt-2 flex items-center justify-center gap-2">
                  <Icon
                    name="tabler:trophy"
                    size="32"
                    :class="rankIconClass(idx)"
                    :title="rankLabel(idx)"
                  />
                  <div class="text-sm opacity-70">
                    #{{ idx + 1 }}
                  </div>
                </div>

                <div class="font-medium truncate mt-1">
                  {{ displayName(r.user) }}
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
              :key="r.user?.id ?? idx"
              class="card bg-base-100 shadow"
            >
              <div
                class="card-body items-center text-center rounded-box"
                :class="rankRingClass(idx)"
              >
                <div class="avatar">
                  <div
                    class="w-16 rounded-full border border-base-300 overflow-hidden"
                    :class="rankRingClass(idx)"
                  >
                    <img :src="avatarOf(r.user)" alt="">
                  </div>
                </div>

                <!-- Pehar + pozicija -->
                <div class="mt-2 flex items-center justify-center gap-2">
                  <Icon
                    name="tabler:trophy"
                    size="32"
                    :class="rankIconClass(idx)"
                    :title="rankLabel(idx)"
                  />
                  <div class="text-sm opacity-70">
                    #{{ idx + 1 }}
                  </div>
                </div>

                <div class="font-medium truncate mt-1">
                  {{ displayName(r.user) }}
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

      <div v-if="!rows.length" class="opacity-70">
        Nema podataka za odabranu sezonu.
      </div>
    </div>
  </div>
</template>
