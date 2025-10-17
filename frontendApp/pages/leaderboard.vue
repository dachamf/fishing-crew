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

// ✅ Single-tenant: bez group_id
const query = reactive({
  season_year: new Date().getFullYear(),
});

const key = computed(() => `leaderboard:season=${query.season_year}`);

const { data, pending, error, refresh } = await useAsyncData<{ items: LeaderboardItem[] }>(
  key,
  async () => {
    const r = await $api.get("/v1/leaderboard", {
      params: { season_year: query.season_year },
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
    // blagi sjaj samo za #1
    idx === 0 ? "shadow-lg shadow-amber-200/50" : "shadow",
  ].join(" ");
}
function rankIconClass(idx: number) {
  return [
    idx === 0 ? "text-amber-400" : idx === 1 ? "text-zinc-300" : "text-amber-700",
    // animacije pehara
    "trophy-shimmer",
    idx === 0 ? "trophy-breathe" : "trophy-float",
  ].join(" ");
}
function cardAnimClass(idx: number) {
  return [
    "rounded-box transition-transform duration-200",
    "hover:scale-[1.015]",
    idx === 0 ? "gold-glow" : idx === 1 ? "silver-float" : "bronze-float",
  ].join(" ");
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

    <!-- Filteri -->
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
              <div class="card-body items-center text-center" :class="cardAnimClass(idx)">
                <div class="avatar">
                  <div
                    class="w-16 rounded-full border border-base-300 overflow-hidden"
                    :class="rankRingClass(idx)"
                  >
                    <img :src="avatarOf(r.user)" alt="">
                  </div>
                </div>

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
              <div class="card-body items-center text-center" :class="cardAnimClass(idx)">
                <div class="avatar">
                  <div
                    class="w-16 rounded-full border border-base-300 overflow-hidden"
                    :class="rankRingClass(idx)"
                  >
                    <img :src="avatarOf(r.user)" alt="">
                  </div>
                </div>

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
              <div class="card-body items-center text-center" :class="cardAnimClass(idx)">
                <div class="avatar">
                  <div
                    class="w-16 rounded-full border border-base-300 overflow-hidden"
                    :class="rankRingClass(idx)"
                  >
                    <img :src="avatarOf(r.user)" alt="">
                  </div>
                </div>

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

<style scoped>
/* --- suptilne animacije za trofeje i top kartice --- */

/* zlatni “glow” puls oko kartice #1 */
@keyframes goldGlow {
  0% {
    box-shadow: 0 0 0 0 rgba(251, 191, 36, 0.28);
  }
  70% {
    box-shadow: 0 0 24px 6px rgba(251, 191, 36, 0.25);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(251, 191, 36, 0.2);
  }
}
.gold-glow {
  animation: goldGlow 2.2s ease-in-out infinite;
}

/* blag “float” za #2 i #3 */
@keyframes floatY {
  0% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-3px);
  }
  100% {
    transform: translateY(0);
  }
}
.silver-float {
  animation: floatY 3.2s ease-in-out infinite;
}
.bronze-float {
  animation: floatY 3.6s ease-in-out infinite;
}

/* shine/shimmer preko ikone pehara */
@keyframes shimmer {
  0% {
    filter: drop-shadow(0 0 0 rgba(255, 255, 255, 0));
    transform: scale(1);
  }
  50% {
    filter: drop-shadow(0 0 6px rgba(255, 255, 255, 0.35));
    transform: scale(1.04);
  }
  100% {
    filter: drop-shadow(0 0 0 rgba(255, 255, 255, 0));
    transform: scale(1);
  }
}
.trophy-shimmer {
  animation: shimmer 2.8s ease-in-out infinite;
}

/* #1 neka “diše”, #2/#3 plivaju */
@keyframes breathe {
  0% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.06);
  }
  100% {
    transform: scale(1);
  }
}
.trophy-breathe {
  animation: breathe 2.6s ease-in-out infinite;
}
.trophy-float {
  animation: floatY 3.4s ease-in-out infinite;
}
</style>
