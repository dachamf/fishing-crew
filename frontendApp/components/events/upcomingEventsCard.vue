<script setup lang="ts">
import type { HomeEvent, Rsvp } from "~/types/api";

const props = defineProps<{
  items?: HomeEvent[];
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
} = await useAsyncData<HomeEvent[]>(
  () => `events:${props.groupId ?? "none"}`,
  async () => {
    if (usePrefetched.value)
      return props.items ?? [];
    const { data } = await $api.get("/v1/events", {
      params: { from: "today", limit: 3, include: "my_rsvp", group_id: props.groupId },
    });
    return data ?? [];
  },
  { server: false, watch: [() => props.groupId] },
);

useSWR(() => refresh(), { intervalMs: 60000, enabled: () => !usePrefetched.value });

function rsvpStatusOf(e: Pick<HomeEvent, "my_rsvp">): Rsvp | undefined {
  const v = e?.my_rsvp as unknown;
  if (!v)
    return undefined;
  if (typeof v === "string")
    return v as Rsvp;
  if (typeof v === "object" && "status" in (v as Record<string, unknown>))
    return (v as { status?: Rsvp }).status;
  return undefined;
}
function rsvpBadgeClass(e: Pick<HomeEvent, "my_rsvp">) {
  const s = rsvpStatusOf(e);
  return {
    "badge-success text-success-content": s === "yes",
    "badge-warning text-warning-content": s === "undecided" || !s,
    "badge-error text-error-content": s === "no",
  };
}
const items = computed(() => (usePrefetched.value ? props.items || [] : internal.value || []));
</script>

<template>
  <UiSkeletonCard :loading="!usePrefetched && _pending">
    <div class="flex items-center justify-between gap-3">
      <h2 class="card-title">
        {{ title || 'Predstojeći događaji' }}
      </h2>
      <NuxtLink
        v-if="viewAllTo"
        :to="viewAllTo"
        class="link link-primary text-sm"
        aria-label="Vidi sve događaje"
      >
        Vidi sve
      </NuxtLink>
    </div>

    <ul v-if="(items?.length || 0) > 0" class="mt-2 space-y-3">
      <li
        v-for="e in items"
        :key="e.id"
        class="flex items-center justify-between"
      >
        <div class="min-w-0">
          <div class="font-medium truncate">
            {{ e.title || `Događaj #${e.id}` }}
          </div>
          <div class="text-xs opacity-70">
            {{ e.start_at ? new Date(e.start_at).toLocaleString('sr-RS') : '—' }}
          </div>
        </div>
        <span
          class="badge capitalize"
          :class="[rsvpBadgeClass(e)]"
          :aria-label="`RSVP: ${rsvpStatusOf(e) || 'undecided'}`"
        >
          {{ rsvpStatusOf(e) ?? 'undecided' }}
        </span>
      </li>
    </ul>

    <UiEmptyState
      v-else
      title="Nema predstojećih događaja"
      desc="Kreiraj događaj ili proveri kasnije."
      cta-text="Otvori događaje"
      to="/events"
      icon="tabler:calendar-event"
    />
  </UiSkeletonCard>
</template>
