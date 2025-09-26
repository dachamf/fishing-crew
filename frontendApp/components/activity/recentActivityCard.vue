<script setup lang="ts">
import type { HomeActivityItem } from "~/types/api";

const props = defineProps<{
  items?: HomeActivityItem[];
  groupId?: number;
  title?: string;
  viewAllTo?: string;
}>();
const usePrefetched = computed(() => Array.isArray(props.items));

const { $api } = useNuxtApp() as any;

const {
  data: internal,
  pending: _pending,
  refresh,
} = await useAsyncData<HomeActivityItem[]>(
  () => `activity:${props.groupId ?? "none"}`,
  async () => {
    if (usePrefetched.value)
      return props.items ?? [];
    const { data } = await $api.get("/v1/activity", {
      params: { group_id: props.groupId, limit: 10 },
    });
    return data ?? [];
  },
  { server: false, watch: [() => props.groupId] },
);

useSWR(() => refresh(), { intervalMs: 60000, enabled: () => !usePrefetched.value });

function icon(t: HomeActivityItem["type"] | string) {
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
const items = computed(() => (usePrefetched.value ? props.items || [] : internal.value || []));
const timeago = useRelativeTime();

function label(t: HomeActivityItem["type"] | string) {
  switch (t) {
    case "catch_added":
      return "Ulov dodat";
    case "session_opened":
      return "Sesija otvorena";
    case "session_approved":
      return "Sesija odobrena";
    case "session_rejected":
      return "Sesija odbijena";
    default:
      return "Aktivnost";
  }
}
</script>

<template>
  <UiSkeletonCard :loading="!usePrefetched && _pending">
    <div class="flex items-center justify-between gap-3">
      <h2 class="card-title">
        {{ title || 'Nedavna aktivnost' }}
      </h2>
      <NuxtLink
        v-if="viewAllTo"
        :to="viewAllTo"
        class="link link-primary text-sm"
        aria-label="Vidi sve aktivnosti"
      >
        Vidi sve
      </NuxtLink>
    </div>

    <ul v-if="(items?.length || 0) > 0" class="mt-2 space-y-2">
      <li
        v-for="a in items"
        :key="a.id"
        class="flex items-center justify-between gap-3"
      >
        <div class="flex items-center gap-2 min-w-0">
          <span class="emoji-bullet" :aria-label="a.type ?? 'aktivnost'">{{
            icon(a.type || '')
          }}</span>
          <div class="truncate">
            <div class="text-sm">
              {{ label(a.type || '') }}
              <span v-if="a.ref_id" class="opacity-70">#{{ a.ref_id }}</span>
            </div>
            <div class="text-xs opacity-70">
              {{ a.created_at ? timeago(a.created_at) : '—' }}
            </div>
          </div>
        </div>
        <NuxtLink
          v-if="a.meta?.url"
          :to="a.meta.url"
          class="btn btn-ghost btn-xs"
          aria-label="Otvori stavku"
        >
          Otvori
        </NuxtLink>
      </li>
    </ul>

    <UiEmptyState
      v-else
      title="Nema aktivnosti"
      desc="Zabeleži ulov ili započni sesiju."
      cta-text="+ Novi ulov"
      to="/catches/new"
      icon="tabler:activity"
    />
  </UiSkeletonCard>
</template>

<style scoped>
.emoji-bullet {
  font-family: "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji", sans-serif;
  font-size: 18px;
  line-height: 1;
}
</style>
