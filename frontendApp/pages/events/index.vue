<script lang="ts" setup>
type RSVP = "yes" | "no" | "undecided" | null;
type AttendeeLite = {
  user_id: number;
  rsvp?: string | null;
  pivot?: { rsvp?: string | null };
  user?: any;
};
type EventItem = {
  id: number;
  title: string;
  start_at?: string | null;
  location_name?: string | null;
  status?: string | null;
  attendees?: AttendeeLite[];
};

const route = useRoute();
const toast = useToast();
const store = useEventStore();
const { $api } = useNuxtApp() as any;

useHead({ title: "Događaji — Fishing Crew" });

// auto group: ?group_id=… -> prva grupa iz /v1/me
const groupId = ref<number | null>(null);

onMounted(async () => {
  try {
    const q = Number(route.query.group_id);
    if (Number.isFinite(q) && q > 0) {
      groupId.value = q;
    }
    else {
      const me = await $api
        .get("/v1/me")
        .then((r: any) => r.data)
        .catch(() => null);
      groupId.value = me?.groups?.[0]?.id ?? null;
    }
    await store.fetchGroup(groupId.value ?? 0);
  }
  catch {
    toast.error("Greška pri učitavanju događaja.");
  }
});

// helpers
const nowTs = Date.now();
const thisYear = new Date().getFullYear();

function toTs(iso?: string | null) {
  if (!iso)
    return null;
  const t = new Date(iso).getTime();
  return Number.isFinite(t) ? t : null;
}

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
function rsvpOf(a?: AttendeeLite) {
  return norm(a?.pivot?.rsvp ?? a?.rsvp ?? null);
}
function splitAttendees(e: EventItem) {
  const list = e.attendees ?? [];
  const yes = list.filter(a => rsvpOf(a) === "yes");
  const no = list.filter(a => rsvpOf(a) === "no");
  const und = list.filter(a => rsvpOf(a) === "undecided" || rsvpOf(a) === null);
  return { yes, no, und };
}
function fmt(dt?: string | null) {
  if (!dt)
    return "—";
  try {
    return new Date(dt).toLocaleString("sr-RS", { dateStyle: "medium", timeStyle: "short" });
  }
  catch {
    return dt;
  }
}

const items = computed<EventItem[]>(() => store.list || []);

// sekcije
const upcoming = computed(() =>
  [...items.value]
    .filter((e) => {
      const t = toTs(e.start_at);
      return t !== null && t >= nowTs;
    })
    .sort((a, b) => toTs(a.start_at)! - toTs(b.start_at)!),
);

const pastThisYear = computed(() =>
  [...items.value]
    .filter((e) => {
      const t = toTs(e.start_at);
      if (t === null || t >= nowTs)
        return false;
      return new Date(t).getFullYear() === thisYear;
    })
    .sort((a, b) => toTs(b.start_at)! - toTs(a.start_at)!),
);
</script>

<template>
  <div class="mx-auto max-w-5xl p-6 space-y-8">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold">
          Događaji
        </h1>
        <p class="text-sm opacity-70">
          Grupa: <span class="font-medium">{{ groupId ?? '—' }}</span>
        </p>
      </div>
      <NuxtLink class="btn btn-primary" to="/events/new">
        Novi
      </NuxtLink>
    </div>

    <div v-if="store.loading" class="space-y-3">
      <div class="skeleton h-24" />
      <div class="skeleton h-24" />
    </div>

    <div v-else class="space-y-10">
      <!-- PREDSTOJEĆI -->
      <section>
        <div class="flex items-center justify-between mb-3">
          <div class="flex items-center gap-2">
            <h2 class="text-lg font-semibold">
              Predstojeći
            </h2>
            <span class="badge badge-ghost">{{ upcoming.length }}</span>
          </div>
        </div>

        <div v-if="upcoming.length" class="grid gap-4 md:grid-cols-2">
          <article
            v-for="e in upcoming"
            :key="e.id"
            class="card bg-base-100 border border-base-300"
          >
            <div class="card-body">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <NuxtLink :to="`/events/${e.id}`" class="font-medium hover:underline truncate">
                    {{ e.title }}
                  </NuxtLink>
                  <div class="text-sm opacity-70">
                    {{ e.location_name || '—' }} · {{ fmt(e.start_at) }}
                  </div>
                </div>
                <span class="badge badge-success">upcoming</span>
              </div>

              <template v-if="e.attendees && e.attendees.length">
                <div class="mt-3 grid grid-cols-3 gap-3">
                  <EventsAttendeesGroup
                    title="Idem"
                    tone="success"
                    :list="splitAttendees(e).yes"
                  />
                  <EventsAttendeesGroup
                    title="Ne idem"
                    tone="ghost"
                    :list="splitAttendees(e).no"
                  />
                  <EventsAttendeesGroup
                    title="Nisam siguran"
                    tone="warning"
                    :list="splitAttendees(e).und"
                  />
                </div>
              </template>
              <template v-else>
                <div class="mt-3 text-sm opacity-70">
                  Nema prijavljenih.
                </div>
              </template>
            </div>
          </article>
        </div>
        <p v-else class="opacity-70">
          Nema predstojećih događaja.
        </p>
      </section>

      <div class="divider" />

      <!-- PROTEKLI (OVE GODINE) -->
      <section>
        <div class="flex items-center gap-2 mb-3">
          <h2 class="text-lg font-semibold">
            Protekli ({{ new Date().getFullYear() }})
          </h2>
          <span class="badge badge-ghost">{{ pastThisYear.length }}</span>
        </div>

        <div v-if="pastThisYear.length" class="grid gap-4 md:grid-cols-2">
          <article
            v-for="e in pastThisYear"
            :key="e.id"
            class="card bg-base-200"
          >
            <div class="card-body">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <NuxtLink :to="`/events/${e.id}`" class="font-medium hover:underline truncate">
                    {{ e.title }}
                  </NuxtLink>
                  <div class="text-sm opacity-70">
                    {{ e.location_name || '—' }} · {{ fmt(e.start_at) }}
                  </div>
                </div>
                <span class="badge badge-outline">finished</span>
              </div>

              <template v-if="e.attendees && e.attendees.length">
                <div class="mt-3">
                  <EventsAttendeesGroup
                    title="Bili prisutni"
                    tone="success"
                    :list="splitAttendees(e).yes"
                  />
                </div>
              </template>
              <template v-else>
                <div class="mt-3 text-sm opacity-70">
                  Bez zabeleženih prisutnih.
                </div>
              </template>
            </div>
          </article>
        </div>
        <p v-else class="opacity-70">
          Nema proteklih događaja za ovu godinu.
        </p>
      </section>

      <p v-if="!upcoming.length && !pastThisYear.length" class="opacity-70">
        Nema događaja.
      </p>
    </div>
  </div>
</template>
