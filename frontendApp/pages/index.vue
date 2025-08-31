<script lang="ts" setup>
import type { ID, Me } from "~/types/api";

defineOptions({ name: "HomePage" });

const { $api } = useNuxtApp() as any;
const router = useRouter();
const toast = useToast();

// Me
const { data: me } = await useAsyncData<Me>(
  "me:home",
  async () => (await $api.get("/v1/me")).data,
  { server: false },
);

// Open session (koristi postojeći composable)
const { openFirst, startNew, loading: sessLoading } = useMySessions();

// Assigned-to-me (mini lista)
const { assignedToMe } = useSessionReview();
const {
  data: assigned,
  pending: assignedLoading,
  refresh: refreshAssigned,
} = await useAsyncData<any>(
  "assigned:home",
  async () => {
    const { items, meta } = await assignedToMe(1, 5);
    return { items: items ?? [], meta };
  },
  { server: false },
);

// Season snapshot
type SeasonStats = {
  sessions: number;
  catches: number;
  total_weight_kg: number;
  biggest_single_kg: number;
  catches_unapproved: number;
  total_weight_kg_unapproved: number;
  biggest_single_kg_unapproved: number;
};
const currentYear = new Date().getFullYear();
const defaultGroupId = computed<ID | null>(() => me.value?.groups?.[0]?.id ?? null);

const {
  data: stats,
  pending: statsLoading,
  error: statsError,
} = await useAsyncData<SeasonStats | null>(
  // 🔑 dinamički ključ — izbegava kolizije i forsira refetch kad se groupId promeni
  () => `season:home:${defaultGroupId.value ?? "none"}`,
  async () => {
    if (!defaultGroupId.value)
      return null;
    const res = await $api.get("/v1/stats/season", {
      params: { group_id: defaultGroupId.value, year: currentYear },
    });
    return res.data ?? null;
  },
  {
    server: false,
    // ✅ koristi getter — sigurnije nego prosleđivanje computed-a direktno
    watch: [() => defaultGroupId.value],
    // optional: immediate je ionako default true
  },
);

// Dialog za zatvaranje
const showClose = ref(false);

// CTA: start or resume
async function onStartOrResume() {
  if (openFirst.value?.id) {
    router.push(`/sessions/${openFirst.value.id}`);
    return;
  }
  if (!defaultGroupId.value) {
    toast.info("Dodaj se u grupu da bi pokrenuo sesiju.");
    return;
  }
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
  if (!openFirst.value?.id)
    return;
  showClose.value = true;
}
</script>

<template>
  <div class="container mx-auto p-4 space-y-6">
    <!-- Greeting + quick actions -->
    <div class="card bg-base-100 shadow">
      <div class="card-body">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
          <div>
            <div class="text-sm opacity-70">
              Dobrodošao nazad
            </div>
            <h1 class="text-2xl font-semibold">
              {{ me?.profile?.display_name || me?.name || '...' }}
            </h1>
          </div>

          <div class="join">
            <button class="btn join-item" @click="onNewCatch">
              + Novi ulov
            </button>
            <button
              :class="{ loading: sessLoading }"
              class="btn btn-primary join-item"
              @click="onStartOrResume"
            >
              {{ openFirst?.id ? 'Nastavi sesiju' : 'Pokreni sesiju' }}
            </button>
            <button
              v-if="openFirst?.id"
              class="btn btn-warning join-item"
              @click="onCloseSession"
            >
              Zatvori sesiju
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Grid: Active session + Needs my decision + Snapshot -->
    <div class="grid md:grid-cols-3 gap-6">
      <!-- Active session -->
      <div class="md:col-span-2 card bg-base-100 shadow">
        <div class="card-body">
          <template v-if="sessLoading">
            <div class="skeleton h-6 w-48 mb-2" />
            <div class="grid grid-cols-3 gap-2">
              <div class="skeleton h-24" />
              <div class="skeleton h-24" />
              <div class="skeleton h-24" />
            </div>
          </template>

          <template v-else-if="openFirst?.id">
            <h2 class="text-xl font-semibold">
              Aktivna sesija — {{ openFirst.title || `#${openFirst.id}` }}
            </h2>
            <div class="opacity-70 text-sm">
              Početak:
              {{
                openFirst.started_at ? new Date(openFirst.started_at).toLocaleString('sr-RS') : '—'
              }}
              • Ulova: {{ openFirst.catches_count ?? 0 }}
            </div>

            <div v-if="(openFirst.photos?.length || 0) > 0" class="mt-3 grid grid-cols-3 gap-2">
              <div
                v-for="(p, i) in openFirst.photos.slice(0, 3)"
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
              <NuxtLink :to="`/sessions/${openFirst.id}`" class="btn join-item">
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
                :class="{ loading: sessLoading }"
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

          <template v-if="assignedLoading">
            <div class="space-y-2">
              <div class="skeleton h-5 w-full" />
              <div class="skeleton h-5 w-4/5" />
              <div class="skeleton h-5 w-3/5" />
            </div>
          </template>
          <template v-else-if="(assigned?.items?.length || 0) > 0">
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
                    {{ s.started_at ? new Date(s.started_at).toLocaleString('sr-RS') : '—' }}
                    • Ulova: {{ s.catches_count ?? '—' }}
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

          <template v-if="statsLoading">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
              <div class="skeleton h-16" />
              <div class="skeleton h-16" />
              <div class="skeleton h-16" />
              <div class="skeleton h-16" />
            </div>
          </template>

          <template v-else-if="statsError">
            <div class="alert alert-error">
              Greška pri učitavanju statistike.
            </div>
          </template>

          <template v-else>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
              <div class="stat bg-base-200 rounded-box">
                <div class="stat-title">
                  Ukupno izlazaka na vodu (sesija)
                </div>
                <div v-if="stats?.sessions" class="stat-value text-primary">
                  {{ stats?.sessions ?? 0 }}
                </div>
              </div>
              <div
                v-if="stats?.catches != null && stats.catches > 0"
                class="stat bg-base-200 rounded-box"
              >
                <div class="stat-title">
                  Ulov potvrđen
                </div>
                <div class="stat-value text-primary">
                  {{ stats?.catches ?? 0 }}
                </div>
              </div>
              <div
                v-if="stats?.total_weight_kg != null && stats.total_weight_kg > 0"
                class="stat bg-base-200 rounded-box"
              >
                <div class="stat-title">
                  Ukupno (kg)
                </div>
                <div class="stat-value text-primary">
                  {{ Number(stats?.total_weight_kg || 0).toFixed(2) }}
                </div>
              </div>
              <div
                v-if="stats?.biggest_single_kg != null && stats.biggest_single_kg > 0"
                class="stat bg-base-200 rounded-box"
              >
                <div class="stat-title">
                  Najveća (kg)
                </div>
                <div class="stat-value text-primary">
                  {{ Number(stats?.biggest_single_kg || 0).toFixed(2) }}
                </div>
              </div>

              <div
                v-if="stats?.catches_unapproved != null && stats.catches_unapproved > 0"
                class="stat bg-base-200 rounded-box"
              >
                <div class="stat-title text-warning">
                  Ulov nepotvrđen
                </div>
                <div class="stat-value text-primary">
                  {{ stats?.catches_unapproved ?? 0 }}
                </div>
              </div>
              <div
                v-if="
                  stats?.total_weight_kg_unapproved != null && stats.total_weight_kg_unapproved > 0
                "
                class="stat bg-base-200 rounded-box"
              >
                <div class="stat-title text-warning">
                  Ukupno (kg)
                </div>
                <div class="stat-value text-primary">
                  {{ Number(stats?.total_weight_kg_unapproved || 0).toFixed(2) }}
                </div>
              </div>
              <div
                v-if="
                  stats?.biggest_single_kg_unapproved != null
                    && stats.biggest_single_kg_unapproved > 0
                "
                class="stat bg-base-200 rounded-box"
              >
                <div class="stat-title text-warning">
                  Najveća (kg)
                </div>
                <div class="stat-value text-primary">
                  {{ Number(stats?.biggest_single_kg_unapproved || 0).toFixed(2) }}
                </div>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>

    <!-- Close session dialog -->
    <SessionCloseDialog
      v-model="showClose"
      :group-id="defaultGroupId || undefined"
      :session-id="openFirst?.id || 0"
      @closed="
        () => {
          showClose = false
          refreshAssigned()
        }
      "
    />

    <!-- Phase 2 blok -->
    <div class="grid gap-6 md:grid-cols-2">
      <Events-UpcomingEventsCard
        :group-id="defaultGroupId || undefined"
        title="Predstojeći događaji"
        view-all-to="/events"
      />
      <Activity-RecentActivityCard
        :group-id="defaultGroupId || undefined"
        title="Nedavna aktivnost"
        view-all-to="/activity"
      />
      <Leaderboard-MiniLeaderboardCard
        v-if="defaultGroupId"
        :group-id="defaultGroupId"
        :year="currentYear"
        :limit="5"
        title="Mini leaderboard"
        view-all-to="/leaderboard"
      />
    </div>

    <div class="grid gap-6 md:grid-cols-2">
      <HomeLastSessionsMapCard
        class="md:col-span-2"
        :limit="10"
        :height="320"
      />
    </div>
  </div>
</template>
