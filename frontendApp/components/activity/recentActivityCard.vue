<script setup lang="ts">
type Props = {
  title?: string;
  groupId?: number;
  limit?: number;
  viewAllTo?: string;
};
const props = withDefaults(defineProps<Props>(), {
  title: "Recent activity",
  limit: 10,
  viewAllTo: "/activity",
});

const { items, loading, fetchFeed } = useActivityFeed();

onMounted(() => {
  fetchFeed(props.groupId, props.limit);
});
watch(
  () => props.groupId,
  gid => fetchFeed(gid, props.limit),
);

function icon(t: string) {
  if (t === "catch_added")
    return "🎣";
  if (t === "session_opened")
    return "🧭";
  if (t === "session_approved")
    return "✅";
  if (t === "session_rejected")
    return "❌";
  return "•";
}

const hasData = computed(() => !loading.value && items.value.length > 0);
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

      <div v-if="loading" class="space-y-3">
        <div class="animate-pulse h-5 bg-base-300 rounded" />
        <div class="animate-pulse h-5 bg-base-300 rounded" />
        <div class="animate-pulse h-5 bg-base-300 rounded" />
      </div>

      <div v-else-if="!hasData" class="text-sm opacity-70">
        Još uvek nema aktivnosti.
      </div>

      <ul v-else class="timeline timeline-vertical">
        <li v-for="f in items" :key="`${f.type}:${f.id}`">
          <div class="timeline-start">
            {{ icon(f.type) }}
          </div>
          <div class="timeline-middle" />
          <div class="timeline-end timeline-box">
            <NuxtLink :to="f.url" class="link font-medium">
              {{ f.title }}
            </NuxtLink>
            <div class="text-xs opacity-60">
              {{ new Date(f.at).toLocaleString('sr-RS') }}
            </div>
          </div>
        </li>
      </ul>
    </div>
  </div>
</template>
