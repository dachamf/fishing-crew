<script setup lang="ts">
import type { HomeActivityItem } from "~/types/api";

const props = defineProps<{
  items?: HomeActivityItem[];
  groupId?: number;
  title?: string;
  viewAllTo?: string;
}>();
const usePrefetched = computed(() => Array.isArray(props.items));

const { data: internal, pending: _pending } = useAsyncData(
  () => `activity:${props.groupId ?? "none"}`,
  async () => (usePrefetched.value ? props.items : []),
  { server: false, watch: [() => props.groupId] },
);

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
</script>

<template>
  <div class="card bg-base-100 shadow">
    <div class="card-body">
      <div class="flex items-center justify-between gap-3">
        <h2 class="card-title">
          {{ title || 'Nedavna aktivnost' }}
        </h2>
        <NuxtLink
          v-if="viewAllTo"
          :to="viewAllTo"
          class="link link-primary text-sm"
        >
          Vidi sve
        </NuxtLink>
      </div>

      <div v-if="!usePrefetched && _pending" class="space-y-2">
        <div class="skeleton h-5 w-full" />
        <div class="skeleton h-5 w-4/5" />
        <div class="skeleton h-5 w-3/5" />
      </div>

      <ul v-else-if="(items?.length || 0) > 0" class="mt-2 space-y-2">
        <li
          v-for="a in items"
          :key="a.id"
          class="flex items-center justify-between gap-3"
        >
          <div class="flex items-center gap-2 min-w-0">
            <span class="emoji-bullet" :aria-label="a.type ?? undefined">
              {{ icon(a.type || '') }}
            </span>
            <div class="truncate">
              <div class="text-sm">
                {{ a.type || 'aktivnost' }}
                <span v-if="a.ref_id" class="opacity-70">#{{ a.ref_id }}</span>
              </div>
              <div class="text-xs opacity-70">
                {{ a.created_at ? new Date(a.created_at).toLocaleString('sr-RS') : '—' }}
              </div>
            </div>
          </div>
          <NuxtLink
            v-if="a.meta?.url"
            :to="a.meta.url"
            class="btn btn-ghost btn-xs"
          >
            Otvori
          </NuxtLink>
        </li>
      </ul>

      <div v-else class="opacity-70 text-sm">
        Nema aktivnosti u skorije vreme.
      </div>
    </div>
  </div>
</template>

<style scoped>
.emoji-bullet {
  font-family: "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji", sans-serif;
  font-size: 18px;
  line-height: 1;
}
</style>
