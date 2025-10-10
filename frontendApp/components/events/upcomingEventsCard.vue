<script setup lang="ts">
type RSVP = "yes" | "no" | "undecided" | null;
type AttendeeLite = { user_id: number; rsvp?: string | null; pivot?: { rsvp?: string | null } };
type EventLite = {
  id: number;
  title: string;
  start_at?: string | null;
  my_rsvp?: RSVP | { status?: string | null } | string | null;
  attendees?: AttendeeLite[];
};

const props = defineProps<{
  items: EventLite[];
  meId?: number;
  title?: string;
  viewAllTo?: string;
}>();

function norm(r?: string | null): RSVP {
  const v = (r ?? "").toString().trim().toLowerCase();
  switch (v) {
    case "yes":
    case "going":
    case "true":
    case "1":
      return "yes";
    case "no":
    case "declined":
    case "false":
    case "0":
      return "no";
    case "maybe":
    case "undecided":
      return "undecided";
    default:
      return null;
  }
}

function rawMyRsvp(e: EventLite): string | null {
  const v = (e as any)?.my_rsvp;
  if (v && typeof v === "object")
    return (v as any).status ?? null;
  if (typeof v === "string")
    return v;
  if (typeof v === "boolean")
    return v ? "yes" : "no";
  if (typeof v === "number")
    return v === 1 ? "yes" : v === 0 ? "no" : null;
  return null;
}

function myRsvpOf(e: EventLite): RSVP {
  const direct = rawMyRsvp(e);
  if (direct !== null) {
    return norm(direct);
  }
  const mine = e.attendees?.find(a => a.user_id === props.meId);
  return norm(mine?.pivot?.rsvp ?? mine?.rsvp ?? null);
}

const rows = computed(() =>
  (props.items || []).map(e => ({
    ...e,
    rsvp: myRsvpOf(e) as RSVP,
  })),
);

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
            v-for="e in rows"
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
            <EventsRsvpBadge :rsvp="e.rsvp" />
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
