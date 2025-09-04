<script setup lang="ts">
const props = defineProps<{ items?: any[] }>();
const usePrefetched = computed(() => Array.isArray(props.items));
const { $api } = useNuxtApp() as any;

const {
  data,
  pending: _pending,
  refresh,
} = await useAsyncData<any[]>(
  "achievements:me",
  async () => {
    if (usePrefetched.value)
      return props.items!;
    const { data } = await $api.get("/v1/achievements", { params: { scope: "me" } });
    return data ?? [];
  },
  { server: false },
);
useSWR(() => refresh(), { intervalMs: 120000, enabled: () => !usePrefetched.value });
const rows = computed(() => data.value ?? []);
</script>

<template>
  <UiSkeletonCard :loading="!usePrefetched && _pending">
    <h2 class="card-title">
      Bedževi
    </h2>

    <div v-if="rows.length" class="grid sm:grid-cols-3 gap-2">
      <div
        v-for="b in rows"
        :key="b.key"
        class="p-3 rounded-box border"
        :class="b.unlocked_at ? 'border-success' : 'border-base-300 opacity-70'"
        :aria-label="(b.title || 'Badge') + (b.unlocked_at ? ' otključan' : ' zaključan')"
      >
        <div class="font-medium">
          {{ b.title }}
        </div>
        <div class="text-sm opacity-80">
          {{ b.meta?.desc }}
        </div>
        <div v-if="b.meta?.value != null" class="mt-1 text-xs">
          Vrednost: <b>{{ b.meta.value }}</b>
        </div>
      </div>
    </div>

    <UiEmptyState
      v-else
      title="Još nema bedževa"
      desc="Ispuni uslove da otključaš prve bedževe."
      icon="tabler:award"
    />
  </UiSkeletonCard>
</template>
