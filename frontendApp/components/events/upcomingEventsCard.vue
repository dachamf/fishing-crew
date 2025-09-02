<script setup lang="ts">
import type { HomeEvent, Rsvp } from "~/types/api"; // Rsvp = 'yes' | 'no' | 'undecided'

defineProps<{
  items?: HomeEvent[];
  groupId?: number;
  title?: string;
  viewAllTo?: string;
}>();

/** Vrati čist string status bez obzira da li my_rsvp dolazi kao string ili { status } */
function rsvpStatusOf(e: Pick<HomeEvent, "my_rsvp">): Rsvp | undefined {
  const v = e?.my_rsvp as unknown;
  if (!v)
    return undefined;
  if (typeof v === "string")
    return v as Rsvp;
  if (typeof v === "object" && v && "status" in (v as any)) {
    return (v as { status?: Rsvp }).status;
  }
  return undefined;
}

function rsvpBadgeClass(e: Pick<HomeEvent, "my_rsvp">) {
  const s = rsvpStatusOf(e);
  return {
    "badge-success": s === "yes",
    "badge-warning": s === "undecided" || !s,
    "badge-error": s === "no",
  };
}

function rsvpText(e: Pick<HomeEvent, "my_rsvp">) {
  return rsvpStatusOf(e) ?? "undecided";
}
</script>

<template>
  <div class="card bg-base-100 shadow">
    <div class="card-body">
      <div class="flex items-center justify-between gap-3">
        <h2 class="card-title">
          {{ title || 'Predstojeći događaji' }}
        </h2>
        <NuxtLink
          v-if="viewAllTo"
          :to="viewAllTo"
          class="link link-primary text-sm"
        >
          Vidi sve
        </NuxtLink>
      </div>

      <div v-if="items === undefined" class="space-y-2">
        <div class="skeleton h-5 w-full" />
        <div class="skeleton h-5 w-4/5" />
        <div class="skeleton h-5 w-3/5" />
      </div>

      <ul v-else-if="(items?.length || 0) > 0" class="mt-2 space-y-3">
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

          <span class="badge capitalize" :class="[rsvpBadgeClass(e)]">
            {{ rsvpText(e) }}
          </span>
        </li>
      </ul>

      <div v-else class="opacity-70 text-sm">
        Nema predstojećih događaja.
      </div>
    </div>
  </div>
</template>
