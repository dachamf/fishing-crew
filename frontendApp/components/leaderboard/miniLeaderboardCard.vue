<script setup lang="ts">
type Props = {
  title?: string;
  groupId: number;
  year?: number;
  limit?: number;
  viewAllTo?: string;
};
const props = withDefaults(defineProps<Props>(), {
  title: "Leaderboard (Top 5)",
  year: new Date().getFullYear(),
  limit: 5,
  viewAllTo: "/leaderboard",
});

const { top, biggest, loading, fetchLB } = useMiniLeaderboard();

onMounted(() => {
  fetchLB(props.groupId, props.year, props.limit);
});
watch(
  () => [props.groupId, props.year, props.limit],
  () => fetchLB(props.groupId, props.year, props.limit),
);

const hasTop = computed(() => !loading.value && top.value.length > 0);

function displayName(r: any) {
  return r.user?.profile?.display_name || r.user?.name || `User #${r.user_id}`;
}
</script>

<template>
  <div class="card bg-base-100 shadow-lg">
    <div class="card-body">
      <div class="flex items-center justify-between">
        <h2 class="card-title">
          {{ title }}
        </h2>
        <NuxtLink :to="viewAllTo" class="link link-primary">
          Vidi sve
        </NuxtLink>
      </div>

      <div v-if="loading" class="space-y-2">
        <div class="animate-pulse h-6 bg-base-300 rounded" />
        <div class="animate-pulse h-6 bg-base-300 rounded" />
        <div class="animate-pulse h-6 bg-base-300 rounded" />
      </div>

      <div v-else-if="!hasTop" class="text-sm opacity-70">
        Nema podataka za ovu godinu.
      </div>

      <div v-else class="space-y-4">
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead>
              <tr>
                <th>#</th>
                <th>Korisnik</th>
                <th class="text-right">
                  Ukupno (kg)
                </th>
                <th class="text-right">
                  Ulovâ
                </th>
                <th class="text-right">
                  Najveći (kg)
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(r, i) in top" :key="r.user_id">
                <td>{{ i + 1 }}</td>
                <td class="truncate">
                  {{ displayName(r) }}
                </td>
                <td class="text-right">
                  {{ (r.total_weight_kg ?? 0).toFixed(2) }}
                </td>
                <td class="text-right">
                  {{ r.catches_count ?? 0 }}
                </td>
                <td class="text-right">
                  {{ (r.biggest_single_kg ?? 0).toFixed(2) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="biggest" class="alert">
          <span>
            Najveći pojedinačni ulov:
            <strong class="mx-1">{{ (biggest.weight_kg ?? 0).toFixed(2) }} kg</strong>
            — {{ displayName(biggest) }}
          </span>
          <NuxtLink :to="`/sessions/${biggest.session_id}`" class="link ml-2">
            Vidi sesiju
          </NuxtLink>
        </div>
      </div>
    </div>
  </div>
</template>
