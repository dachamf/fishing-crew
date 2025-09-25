<script lang="ts" setup>
import type { ID } from "~/types/api";

import { useHome } from "~/composables/useHome";

defineOptions({ name: "HomePage" });

const { $api } = useNuxtApp() as any;
const router = useRouter();
const toast = useToast();

const currentYear = new Date().getFullYear();

// me za defaultGroupId (može i iz /v1/home.me, ali ovo već imaš)
const { data: meRaw } = await useAsyncData(
  "me:forGroup",
  async () => (await $api.get("/v1/me")).data,
  { server: false },
);
const defaultGroupId = computed<ID | null>(() => meRaw.value?.groups?.[0]?.id ?? null);

// JEDAN agregatni poziv
const {
  data: home,
  pending: homeLoading,
  error: homeError,
  refresh: refreshHome,
} = useHome({ groupId: defaultGroupId.value, year: currentYear });

// Derivati
const me = computed(() => home.value?.me);
const openSession = computed(() => home.value?.open_session ?? null);
const assigned = computed(() => home.value?.assigned ?? { items: [], meta: { total: 0 } });
const stats = computed(() => home.value?.season_stats ?? null);

// Start/Resume (i dalje koristiš postojeći composable zbog startNew)
const { startNew, loading: sessLoading } = useMySessions();

const hintLat = ref<number | null>(null);
const hintLng = ref<number | null>(null);
watch(
  openSession,
  (s) => {
    hintLat.value = (s?.latitude ?? null) as any;
    hintLng.value = (s?.longitude ?? null) as any;
  },
  { immediate: true },
);

const showClose = ref(false);

async function onStartOrResume() {
  if (openSession.value?.id) {
    await router.push(`/sessions/${openSession.value.id}`);
    return;
  }
  if (!defaultGroupId.value)
    return toast.info("Dodaj se u grupu da bi pokrenuo sesiju.");
  try {
    const s = await startNew(defaultGroupId.value);
    await router.push(`/sessions/${s.id}`);
  }
  catch (e: any) {
    toast.error(e?.response?.data?.message || "Greška pri pokretanju sesije");
  }
}
function onNewCatch() {
  router.push("/catches/new");
}
function onCloseSession() {
  if (openSession.value?.id)
    showClose.value = true;
}

function onClosedHome() {
  showClose.value = false;
  refreshHome(); // ili refreshAssigned() ako to želiš
}

useSWR(() => refreshHome(), {
  intervalMs: 45000,
  enabled: () => true,
});
</script>

<template>
  <div class="container mx-auto p-4 space-y-6">
    <!-- Greeting + CTA -->
    <div class="card bg-base-100 shadow">
      <div class="card-body">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
          <div>
            <div class="text-sm opacity-70">
              Dobrodošao nazad
            </div>
            <h1 class="text-2xl font-semibold">
              {{ me?.display_name || me?.name || '...' }}
            </h1>
          </div>
          <div class="join">
            <button class="btn join-item" @click="onNewCatch">
              + Novi ulov
            </button>
            <button
              :class="{ loading: sessLoading || homeLoading }"
              class="btn btn-primary join-item"
              @click="onStartOrResume"
            >
              {{ openSession?.id ? 'Nastavi sesiju' : 'Pokreni sesiju' }}
            </button>
            <button
              v-if="openSession?.id"
              class="btn btn-warning join-item"
              @click="onCloseSession"
            >
              Zatvori sesiju
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Grid: Active + Assigned + Snapshot -->
    <div class="grid md:grid-cols-3 gap-6">
      <!-- Active session -->
      <div class="md:col-span-2 card bg-base-100 shadow">
        <div class="card-body">
          <template v-if="homeLoading">
            <div class="skeleton h-6 w-48 mb-2" />
            <div class="grid grid-cols-3 gap-2">
              <div class="skeleton h-24" />
              <div class="skeleton h-24" />
              <div class="skeleton h-24" />
            </div>
          </template>
          <template v-else-if="openSession?.id">
            <h2 class="text-xl font-semibold">
              Aktivna sesija — {{ openSession.title || `#${openSession.id}` }}
            </h2>
            <div class="opacity-70 text-sm">
              Početak:
              {{
                openSession.started_at
                  ? new Date(openSession.started_at).toLocaleString('sr-RS')
                  : '—'
              }}
              • Ulova: {{ openSession.catches_count ?? 0 }}
            </div>
            <div v-if="(openSession.photos?.length || 0) > 0" class="mt-3 grid grid-cols-3 gap-2">
              <div
                v-for="(p, i) in openSession.photos!.slice(0, 3)"
                :key="i"
                class="aspect-video rounded-xl overflow-hidden border border-base-300"
              >
                <img
                  :src="p.url"
                  class="w-full h-full object-cover"
                  loading="lazy"
                >
              </div>
            </div>
            <div class="mt-3 join">
              <NuxtLink :to="`/sessions/${openSession.id}`" class="btn join-item">
                Otvori sesiju
              </NuxtLink>
              <button class="btn btn-primary join-item" @click="onNewCatch">
                + Dodaj ulov
              </button>
              <button class="btn btn-warning join-item" @click="onCloseSession">
                Zatvori
              </button>
            </div>
          </template>
          <template v-else>
            <h2 class="text-xl font-semibold">
              Nema aktivne sesije
            </h2>
            <div class="opacity-70 text-sm">
              Pokreni sesiju da bi beležio ulove u realnom vremenu.
            </div>
            <div class="mt-3">
              <button
                :class="{ loading: sessLoading || homeLoading }"
                class="btn btn-primary"
                @click="onStartOrResume"
              >
                Pokreni sesiju
              </button>
            </div>
          </template>
        </div>
      </div>

      <!-- Needs my decision -->
      <div class="card bg-base-100 shadow">
        <div class="card-body">
          <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold">
              Čeka na moju potvrdu
            </h2>
            <NuxtLink class="link link-primary text-sm" to="/sessions/assigned">
              Vidi sve
            </NuxtLink>
          </div>
          <template v-if="homeLoading">
            <div class="space-y-2">
              <div class="skeleton h-5 w-full" />
              <div class="skeleton h-5 w-4/5" />
              <div class="skeleton h-5 w-3/5" />
            </div>
          </template>
          <template v-else-if="(assigned.items?.length || 0) > 0">
            <ul class="mt-1 space-y-2">
              <li
                v-for="s in assigned.items"
                :key="s.id"
                class="flex items-start justify-between gap-3"
              >
                <div class="min-w-0">
                  <NuxtLink
                    :title="s.title || `Sesija #${s.id}`"
                    :to="`/sessions/${s.id}`"
                    class="font-medium text-sm hover:underline truncate block"
                  >
                    {{ s.title || `Sesija #${s.id}` }}
                  </NuxtLink>
                  <div class="text-xs opacity-70">
                    Počela:
                    {{ s.started_at ? new Date(s.started_at).toLocaleString('sr-RS') : '—' }} •
                    Ulova: {{ s.catches_count ?? '—' }}
                  </div>
                </div>
                <NuxtLink :to="`/sessions/${s.id}`" class="btn btn-ghost btn-xs">
                  Otvori
                </NuxtLink>
              </li>
            </ul>
          </template>
          <template v-else>
            <div class="opacity-70 text-sm">
              Nema sesija koje čekaju tvoju odluku.
            </div>
          </template>
        </div>
      </div>

      <!-- Snapshot -->
      <div class="card bg-base-100 shadow md:col-span-3">
        <div class="card-body">
          <h2 class="text-lg font-semibold">
            Moja sezona ({{ currentYear }})
          </h2>
          <template v-if="homeLoading">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
              <div class="skeleton h-16" />
              <div class="skeleton h-16" />
              <div class="skeleton h-16" />
              <div class="skeleton h-16" />
            </div>
          </template>
          <template v-else-if="homeError">
            <div class="alert alert-error">
              Greška pri učitavanju statistike.
            </div>
          </template>
          <template v-else>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
              <div class="stat bg-base-200 rounded-box">
                <div class="stat-title">
                  Izlasci (sesije)
                </div>
                <div class="stat-value text-primary">
                  {{ stats?.sessions ?? 0 }}
                </div>
              </div>
              <div v-if="(stats?.catches ?? 0) > 0" class="stat bg-base-200 rounded-box">
                <div class="stat-title">
                  Ulov potvrđen
                </div>
                <div class="stat-value text-primary">
                  {{ stats?.catches ?? 0 }}
                </div>
              </div>
              <div v-if="(stats?.total_weight_kg ?? 0) > 0" class="stat bg-base-200 rounded-box">
                <div class="stat-title">
                  Ukupno (kg)
                </div>
                <div class="stat-value text-primary">
                  {{ Number(stats?.total_weight_kg || 0).toFixed(2) }}
                </div>
              </div>
              <div v-if="(stats?.biggest_single_kg ?? 0) > 0" class="stat bg-base-200 rounded-box">
                <div class="stat-title">
                  Najveća (kg)
                </div>
                <div class="stat-value text-primary">
                  {{ Number(stats?.biggest_single_kg || 0).toFixed(2) }}
                </div>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>

    <!-- Close session dialog -->
    <LazySessionCloseDialog
      v-model="showClose"
      :group-id="defaultGroupId || undefined"
      :session-id="openSession?.id || 0"
      @closed="onClosedHome"
    />

    <!-- Phase 2/3/4 kartice — sada preloaded -->
    <div class="grid gap-6 md:grid-cols-2">
      <LazyHomeWeatherHintCard :hint-lat="hintLat" :hint-lng="hintLng" />
      <LazyHomeAdminMiniPanel
        :group-id="defaultGroupId || undefined"
        :can-manage-prefetched="home?.admin?.canManage"
        :shortcuts-prefetched="home?.admin?.shortcuts"
      />
      <LazyEventsUpcomingEventsCard
        :items="home?.events || []"
        :group-id="defaultGroupId || undefined"
        title="Predstojeći događaji"
        view-all-to="/events"
      />
      <LazyActivityRecentActivityCard
        :items="home?.activity || []"
        :group-id="defaultGroupId || undefined"
        title="Nedavna aktivnost"
        view-all-to="/activity"
      />
      <LazyLeaderboardMiniLeaderboardCard
        v-if="defaultGroupId"
        :data="home?.mini_leaderboard"
        :group-id="defaultGroupId"
        :year="currentYear"
        :limit="5"
        title="Mini leaderboard"
        view-all-to="/leaderboard"
      />
      <AchievementsBadgesCard :items="home?.achievements || []" />
      <LazyStatsSpeciesTrendsCard
        v-if="defaultGroupId"
        :trends="home?.species_trends || []"
        :group-id="defaultGroupId"
        :year="currentYear"
      />
    </div>

    <div class="grid gap-6 md:grid-cols-2">
      <LazyHomeLastSessionsMapCard
        class="md:col-span-2"
        :sessions="home?.map || []"
        :limit="10"
        :height="320"
      />
    </div>
  </div>
</template>
