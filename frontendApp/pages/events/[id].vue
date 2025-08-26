<script lang="ts" setup>
defineOptions({ name: "EventDetailPage" });

const { $api } = useNuxtApp() as any;
const auth = useAuth();
const route = useRoute();

const id = computed(() => Number(route.params.id));

// --- Učitavanje eventa (drži pending/error kako treba) ---
const { data, pending, error, refresh } = await useAsyncData(
  () => `event:${id.value}`,
  async () => {
    const res = await $api.get(`/v1/events/${id.value}`);
    return res?.data?.data ?? res?.data ?? res;
  },
  { watch: [id] },
);

const ev = computed(() => data.value ?? null);

const dateText = computed(() => {
  const iso = ev.value?.start_at;
  if (!iso)
    return "";
  return new Intl.DateTimeFormat("sr-RS", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
    hour12: false,
    timeZone: "Europe/Belgrade",
  }).format(new Date(iso));
});

// --- Koordinate za mapu (read-only) ---
const coords = computed(() => {
  const lng = Number(ev.value?.longitude);
  const lat = Number(ev.value?.latitude);
  return {
    lng: Number.isFinite(lng) ? lng : null,
    lat: Number.isFinite(lat) ? lat : null,
  };
});
const hasCoords = computed(() => coords?.value.lng !== null && coords?.value.lat !== null);

// --- Dozvole: vlasnik može da menja samo Title/Description (primer) ---
const isOwner = computed(() => {
  const me = auth.user.value?.id;
  return !!me && (ev.value?.user_id === me || ev.value?.owner_id === me);
});

// --- RSVP (čekiranja): 'going' | 'maybe' | 'not_going' | null ---
type Rsvp = "yes" | "undecided" | "no" | null;
const rsvp = ref<Rsvp>(null);
const rsvpLoading = ref(false);

// inicijalno stanje (ako backend vraća npr. ev.my_rsvp)
watchEffect(() => {
  rsvp.value = (ev.value?.my_rsvp ?? null) as Rsvp;
});

async function toggleRsvp(next: Exclude<Rsvp, null>) {
  try {
    rsvpLoading.value = true;
    // klik na već aktivno stanje -> skini RSVP (null)
    const newVal: Rsvp = rsvp.value === next ? null : next;
    rsvp.value = newVal;
    await $api.post(`/v1/events/${id.value}/rsvp`, { rsvp: newVal }); // prilagodi endpoint
    await refresh();
  }
  finally {
    rsvpLoading.value = false;
  }
}

const { data: attendeesRes } = await useEventAttendees(Number(route.params.id), "yes");
const attendees = computed(() => {
  const list = attendeesRes?.data;
  if (!Array.isArray(list))
    return [];
  return list.filter(a => (a?.rsvp?.choice ?? a?.pivot?.rsvp ?? a?.rsvp) === "yes");
});

const declined = computed(() => {
  const list = attendeesRes?.data;
  if (!Array.isArray(list))
    return [];
  return list.filter(a => (a?.rsvp?.choice ?? a?.pivot?.rsvp ?? a?.rsvp) === "no");
});
const counts = computed(() => attendeesRes.counts ?? { yes: 0, undecided: 0, no: 0, total: 0 });
</script>

<template>
  <div class="container mx-auto p-4">
    <!-- STATUSI UČITAVANJA -->
    <div v-if="pending" class="flex items-center gap-2">
      <span class="loading loading-spinner" /> Učitavanje…
    </div>
    <div v-else-if="error" class="alert alert-error">
      Došlo je do greške pri učitavanju događaja.
    </div>
    <div v-else-if="!ev" class="alert">
      Nema podataka o događaju.
    </div>

    <div v-else class="grid gap-6 lg:grid-cols-4">
      <!-- LEVA KOLONA: glavna kartica (izgled kao pre) -->
      <div class="lg:col-span-2 space-y-2">
        <!-- KARTICA: osnovne info -->
        <div class="card bg-base-100 shadow">
          <div class="card-body">
            <h1 class="card-title text-2xl">
              {{ ev.title }}
              <span class="badge badge-outline">{{ ev.status }}</span>
            </h1>

            <div class="flex flex-wrap items-center gap-2 opacity-80">
              <span class="badge badge-neutral">
                {{ dateText }}
              </span>
              <span v-if="ev.group?.name" class="badge badge-ghost">
                {{ ev.group.name }}
              </span>
            </div>

            <div class="divider my-2" />

            <div class="flex items-center gap-2">
              <Icon name="tabler:map-pin" />
              <span class="font-medium">{{ ev.location_name || 'Lokacija nije uneta' }}</span>
            </div>

            <p class="mt-3 whitespace-pre-line">
              {{ ev.description }}
            </p>

            <div v-if="isOwner" class="alert alert-info mt-3">
              Ti si kreator događaja. Lokaciju više nije moguće menjati.
              (Dozvoli izmenu naslova/opisa ako želiš – kroz odvojenu formu ili modal.)
            </div>
          </div>
        </div>

        <!-- KARTICA: RSVP čekiranja (izgled “kao nekad”) -->
        <div class="card bg-base-100 shadow">
          <div class="card-body">
            <h2 class="card-title">
              Prisustvo
            </h2>
            <p class="opacity-70">
              Označi svoj status dolaska:
            </p>

            <div class="grid gap-3 md:grid-cols-3 mt-2">
              <label class="form-control cursor-pointer">
                <div class="label justify-start gap-3">
                  <input
                    :checked="rsvp === 'yes'"
                    :disabled="rsvpLoading"
                    class="checkbox"
                    type="checkbox"
                    @change="toggleRsvp('yes')"
                  >
                  <span class="label-text">Dolazim</span>
                </div>
              </label>

              <label class="form-control cursor-pointer">
                <div class="label justify-start gap-3">
                  <input
                    :checked="rsvp === 'undecided'"
                    :disabled="rsvpLoading"
                    class="checkbox"
                    type="checkbox"
                    @change="toggleRsvp('undecided')"
                  >
                  <span class="label-text">Možda</span>
                </div>
              </label>

              <label class="form-control cursor-pointer">
                <div class="label justify-start gap-3">
                  <input
                    :checked="rsvp === 'no'"
                    :disabled="rsvpLoading"
                    class="checkbox"
                    type="checkbox"
                    @change="toggleRsvp('no')"
                  >
                  <span class="label-text">Ne dolazim</span>
                </div>
              </label>
            </div>

            <div class="divider" />

            <!-- Spisak potvrđenih (avatar + ime) -->
            <div>
              <h3 class="font-semibold mb-2">
                Potvrdili dolazak: {{ counts.yes }} / {{ counts.total }}
              </h3>
              <div v-if="attendees.length" class="flex flex-wrap gap-3">
                <div
                  v-for="a in attendees"
                  :key="a.id"
                  class="flex items-center gap-2"
                >
                  <div class="avatar">
                    <div class="w-8 rounded-full ring ring-base-300 ring-offset-2 overflow-hidden">
                      <img :src="a.avatar || '/icons/icon-64.png'" alt="">
                    </div>
                  </div>
                  <span class="text-sm">{{ a.display_name || a.name }}</span>
                </div>
              </div>
              <div v-else class="opacity-70">
                Još uvek niko nije potvrdio.
              </div>
            </div>
            <div class="divider" />
            <div>
              <h3 class="font-semibold mb-2">
                Ne dolaze:
              </h3>
              <div v-if="declined.length" class="flex flex-wrap gap-3">
                <div
                  v-for="a in declined"
                  :key="a.id"
                  class="flex items-center gap-2"
                >
                  <div class="avatar">
                    <div class="w-8 rounded-full ring ring-base-300 ring-offset-2 overflow-hidden">
                      <img :src="a.avatar || '/icons/icon-64.png'" alt="">
                    </div>
                  </div>
                  <span class="text-sm">{{ a.display_name || a.name }}</span>
                </div>
              </div>
              <div v-else class="opacity-70">
                Još uvek niko nije otkazao.
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- DESNA KOLONA: mapa u kartici (read-only) -->
      <div class="lg:col-span-2">
        <div class="card bg-base-100 shadow">
          <div class="card-body">
            <h2 class="card-title">
              Mapa
            </h2>
            <div class="rounded-xl overflow-hidden border border-base-300">
              <div
                class="w-full h-full min-h-[50svh] overflow-hidden rounded-2xl border border-base-300 relative"
                style="height: 420px;"
              >
                <MapCoordPicker
                  v-if="hasCoords"
                  :coords="coords"
                  :editable="false"
                />

                <div v-else class="p-4 text-sm opacity-70">
                  Lokacija za ovaj događaj nije postavljena.
                </div>
              </div>
            </div>
            <div v-if="hasCoords" class="mt-2 text-xs opacity-70">
              {{ coords.lng?.toFixed(6) }}, {{ coords.lat?.toFixed(6) }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
