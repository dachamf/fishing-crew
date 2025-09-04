<script setup lang="ts">
import type { HomeMiniLeaderboard, HomeMiniLeaderboardRow, LeaderboardItem } from "~/types/api";

type PrefetchedMini = { items: LeaderboardItem[] } | HomeMiniLeaderboard;

const props = defineProps<{
  data?: PrefetchedMini;
  groupId?: number;
  year?: number;
  limit?: number;
  title?: string;
  viewAllTo?: string;
}>();

const { $api } = useNuxtApp() as any;

const hasPrefetched = computed(() => !!props.data);

const key = computed(
  () =>
    `miniLB:${props.groupId ?? "none"}:${props.year ?? "curr"}:${hasPrefetched.value ? "pre" : "api"}`,
);

const {
  data,
  pending: _pending,
  refresh,
} = await useAsyncData<{
  items: Array<LeaderboardItem | HomeMiniLeaderboardRow>;
}>(
  key,
  async () => {
    const d = props.data as any;
    if (d) {
      if (Array.isArray(d.items))
        return { items: d.items as LeaderboardItem[] };
      // HomeMiniLeaderboard: spoj samo za fallback tabelu; prikaz koristi direktne top liste
      const merged = [...(d.weightTop ?? []), ...(d.biggestTop ?? [])] as HomeMiniLeaderboardRow[];
      return { items: merged };
    }
    if (!props.groupId)
      return { items: [] };
    const r = await $api.get("/v1/leaderboard", {
      params: {
        group_id: props.groupId,
        season_year: props.year,
        limit: props.limit ?? 5,
        include: "user",
      },
    });
    return { items: (r.data?.items ?? r.data ?? []) as LeaderboardItem[] };
  },
  { server: false, watch: [() => props.groupId, () => props.year] },
);

// SWR samo kad nemamo prefetched podatke
useSWR(() => refresh(), {
  intervalMs: 60000,
  enabled: () => !hasPrefetched.value && !!props.groupId,
});

const rows = computed(() => data.value?.items ?? []);
const pre = computed(() => (props.data as any) || {});

// type guards + helperi koji “ujednačavaju” nazive polja
function isHomeRow(r: any): r is HomeMiniLeaderboardRow {
  return "total_weight_kg" in (r || {}) || "biggest_single_kg" in (r || {});
}
function weightOf(r: LeaderboardItem | HomeMiniLeaderboardRow) {
  return Number(
    isHomeRow(r) ? (r.total_weight_kg ?? 0) : ((r as LeaderboardItem).weight_total ?? 0),
  );
}
function biggestOf(r: LeaderboardItem | HomeMiniLeaderboardRow) {
  return Number(isHomeRow(r) ? (r.biggest_single_kg ?? 0) : ((r as LeaderboardItem).biggest ?? 0));
}
function userOf(r: LeaderboardItem | HomeMiniLeaderboardRow) {
  return (r as any).user ?? null;
}
function displayName(u?: { display_name?: string; name?: string } | null) {
  return u?.display_name || u?.name || "—";
}
function avatarOf(u?: { avatar_url?: string | null } | null) {
  return u?.avatar_url || "/icons/icon-64.png";
}
function kg(n?: number | null) {
  return `${Number(n || 0).toFixed(2)} kg`;
}

// Preferiraj precomputed top liste sa Home (weightTop/biggestTop), inače sortiraj iz rows
const topByWeight = computed<Array<LeaderboardItem | HomeMiniLeaderboardRow>>(() => {
  if (Array.isArray(pre.value.weightTop))
    return pre.value.weightTop.slice(0, props.limit ?? 5);
  return [...rows.value].sort((a, b) => weightOf(b) - weightOf(a)).slice(0, props.limit ?? 5);
});
const topByBiggest = computed<Array<LeaderboardItem | HomeMiniLeaderboardRow>>(() => {
  if (Array.isArray(pre.value.biggestTop))
    return pre.value.biggestTop.slice(0, props.limit ?? 5);
  return [...rows.value].sort((a, b) => biggestOf(b) - biggestOf(a)).slice(0, props.limit ?? 5);
});
</script>

<template>
  <UiSkeletonCard :loading="!hasPrefetched && _pending">
    <div class="flex items-center justify-between">
      <h2 class="card-title">
        {{ title || 'Mini leaderboard' }}
      </h2>
      <NuxtLink
        v-if="viewAllTo"
        :to="viewAllTo"
        class="link link-primary text-sm"
        aria-label="Otvori leaderboard"
      >
        Vidi sve
      </NuxtLink>
    </div>

    <div v-if="topByWeight.length || topByBiggest.length" class="grid gap-4">
      <!-- Top ukupna težina -->
      <div v-if="topByWeight.length">
        <div class="font-semibold mb-2">
          Top ukupna težina
        </div>
        <ul class="space-y-2">
          <li
            v-for="(r, i) in topByWeight"
            :key="userOf(r)?.id ?? i"
            class="flex items-center justify-between"
          >
            <div class="flex items-center gap-2 min-w-0">
              <div class="avatar">
                <div class="w-7 rounded-full overflow-hidden border border-base-300">
                  <img :src="avatarOf(userOf(r))" alt="">
                </div>
              </div>
              <span class="truncate">{{ displayName(userOf(r)) }}</span>
            </div>
            <span class="badge">{{ kg(weightOf(r)) }}</span>
          </li>
        </ul>
      </div>

      <!-- Najveći primerak -->
      <div v-if="topByBiggest.length">
        <div class="font-semibold mb-2">
          Najveći primerak
        </div>
        <ul class="space-y-2">
          <li
            v-for="(r, i) in topByBiggest"
            :key="userOf(r)?.id ?? i"
            class="flex items-center justify-between"
          >
            <div class="flex items-center gap-2 min-w-0">
              <div class="avatar">
                <div class="w-7 rounded-full overflow-hidden border border-base-300">
                  <img :src="avatarOf(userOf(r))" alt="">
                </div>
              </div>
              <span class="truncate">{{ displayName(userOf(r)) }}</span>
            </div>
            <span class="badge">{{ kg(biggestOf(r)) }}</span>
          </li>
        </ul>
      </div>
    </div>

    <UiEmptyState
      v-else
      title="Nema poretka"
      desc="Još nema dovoljno podataka za rang listu."
      to="/catches/new"
      cta-text="+ Dodaj ulov"
      icon="tabler:trophy"
    />
  </UiSkeletonCard>
</template>
