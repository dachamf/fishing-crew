<script setup lang="ts">
type RSVP = "yes" | "no" | "undecided" | null;
type AttendeeLite = { user_id: number; rsvp?: string | null; pivot?: { rsvp?: string | null } };
type EventLite = {
  id: number;
  title: string;
  start_at?: string | null;
  my_rsvp?: RSVP | string | null;
  attendees?: AttendeeLite[];
};

const props = defineProps<{
  items: EventLite[];
  meId?: number;
  title?: string;
  viewAllTo?: string;
}>();

function norm(r?: string | null): RSVP {
  switch ((r ?? "").toLowerCase()) {
    case "yes":
    case "going":
      return "yes";
    case "no":
    case "declined":
      return "no";
    case "maybe":
    case "undecided":
      return "undecided";
    default:
      return null;
  }
}

function myRsvpOf(e: EventLite): RSVP {
  if (e.my_rsvp !== undefined)
    return norm(e.my_rsvp as any);
  const mine = e.attendees?.find(a => a.user_id === props.meId);
  return norm(mine?.pivot?.rsvp ?? mine?.rsvp ?? null);
}

function rsvpBadge(r: RSVP) {
  switch (r) {
    case "yes":
      return { text: "idem", cls: "badge-success" };
    case "no":
      return { text: "ne idem", cls: "badge-ghost" };
    case "undecided":
      return { text: "neodlučan", cls: "badge-warning" };
    default:
      return { text: "—", cls: "badge-ghost" };
  }
}

function fmt(dt?: string | null) {
  if (!dt)
    return "—";
  try {
    return new Date(dt).toLocaleString("sr-RS", { dateStyle: "medium", timeStyle: "medium" });
  }
  catch {
    return dt;
  }
}
</script>

<template>
  <div class="card bg-base-100 shadow">
    <div class="card-body">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold">
          {{ title || 'Predstojeći događaji' }}
        </h2>
        <NuxtLink
          v-if="viewAllTo"
          class="link link-primary text-sm"
          :to="viewAllTo"
        >
          Vidi sve
        </NuxtLink>
      </div>

      <template v-if="(items?.length || 0) > 0">
        <ul class="mt-1 space-y-3">
          <li
            v-for="e in items"
            :key="e.id"
            class="flex items-start justify-between gap-3"
          >
            <div class="min-w-0">
              <div class="font-medium text-sm truncate">
                {{ e.title || `Event #${e.id}` }}
              </div>
              <div class="text-xs opacity-70">
                {{ fmt(e.start_at) }}
              </div>
            </div>
            <span class="badge" :class="rsvpBadge(myRsvpOf(e)).cls">
              {{ rsvpBadge(myRsvpOf(e)).text }}
            </span>
          </li>
        </ul>
      </template>

      <template v-else>
        <div class="opacity-70 text-sm">
          Nema predstojećih događaja.
        </div>
      </template>
    </div>
  </div>
</template>
