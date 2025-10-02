<script lang="ts" setup>
import { unref } from "vue";

import type { RSVP } from "~/types/api";

defineOptions({ name: "EventDetailPage" });

const { $api } = useNuxtApp() as any;
const auth = useAuth();
const route = useRoute();
const toast = useToast();

const id = computed(() => Number(route.params.id));

// --- Učitavanje eventa ---
const { data, pending, error, refresh } = await useAsyncData(
  () => `event:${id.value}`,
  async () => {
    const res = await $api.get(`/v1/events/${id.value}`);
    return res?.data?.data ?? res?.data ?? res;
  },
  { watch: [id] },
);

const ev = computed(() => data.value ?? null);

// --- Datum ---
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

// --- Koordinate (read-only) ---
const coords = computed(() => {
  const lng = Number(ev.value?.longitude);
  const lat = Number(ev.value?.latitude);
  return {
    lng: Number.isFinite(lng) ? lng : null,
    lat: Number.isFinite(lat) ? lat : null,
  };
});
const hasCoords = computed(() => coords.value.lng !== null && coords.value.lat !== null);

// --- Dozvole ---
const isOwner = computed(() => {
  const meId = auth.user.value?.id;
  return !!meId && (ev.value?.user_id === meId || ev.value?.owner_id === meId);
});

// --- RSVP state ---
const rsvp = ref<RSVP>(null);
const rsvpLoading = ref(false);

// Sync-uj rsvp iz event payload-a (podržava i oblik { status })
watch(
  () => ev.value?.my_rsvp,
  (val) => {
    const next = val && typeof val === "object" && "status" in val ? (val as any).status : val;
    rsvp.value = (next ?? null) as RSVP;
  },
  { immediate: true },
);

// --- Attendees store (useEventAttendees) ---
const { data: attendeesRes, refresh: refreshAttendees } = await useEventAttendees(id.value);
const ar = computed<any>(() => unref(attendeesRes) ?? {});

const attendeesAll = computed(() => (Array.isArray(ar.value?.data) ? ar.value.data : []));
const attendeesYes = computed(() =>
  attendeesAll.value.filter((a: any) => (a?.pivot?.rsvp ?? a?.rsvp) === "yes"),
);
const declined = computed(() =>
  attendeesAll.value.filter((a: any) => (a?.pivot?.rsvp ?? a?.rsvp) === "no"),
);
const counts = computed(() => {
  if (ar.value?.counts)
    return ar.value.counts;
  return {
    yes: attendeesYes.value.length,
    undecided: attendeesAll.value.filter((a: any) => (a?.pivot?.rsvp ?? a?.rsvp) === "undecided")
      .length,
    no: declined.value.length,
    total: attendeesAll.value.length,
  };
});

// --- Lokalno (optimističko) ažuriranje attendees + counts ---
function applyLocalRsvp(next: RSVP) {
  // ako nemamo store, napravi minimalni oblik
  if (!attendeesRes.value) {
    attendeesRes.value = { data: [], counts: { yes: 0, undecided: 0, no: 0, total: 0 } };
  }
  const store = attendeesRes.value as any;
  store.data = Array.isArray(store.data) ? store.data : [];
  store.counts = store.counts ?? { yes: 0, undecided: 0, no: 0, total: 0 };

  const me = auth.user.value;
  if (!me?.id) {
    return;
  }
  const arr = store.data as any[];
  const idx = arr.findIndex((x: any) => x.id === me.id);
  const prev: RSVP = idx !== -1 ? (arr[idx]?.pivot?.rsvp ?? arr[idx]?.rsvp ?? null) : null;

  const inc = (key?: RSVP) => {
    if (!key)
      return;
    if (key === "yes" || key === "undecided" || key === "no") {
      store.counts[key] = (store.counts[key] ?? 0) + 1;
      store.counts.total = (store.counts.total ?? 0) + 1;
    }
  };

  const move = (from?: RSVP, to?: RSVP) => {
    if (from === to)
      return;
    if (from && (from === "yes" || from === "undecided" || from === "no")) {
      store.counts[from] = Math.max(0, (store.counts[from] ?? 0) - 1);
    }
    if (to && (to === "yes" || to === "undecided" || to === "no")) {
      store.counts[to] = (store.counts[to] ?? 0) + 1;
    }
  };

  if (next === null) {
    // ukloni me iz liste
    if (idx !== -1) {
      arr.splice(idx, 1);
      store.counts.total = Math.max(0, (store.counts.total ?? 0) - 1);
      if (prev && (prev === "yes" || prev === "undecided" || prev === "no")) {
        store.counts[prev] = Math.max(0, (store.counts[prev] ?? 0) - 1);
      }
    }
  }
  else if (idx === -1) {
    // dodaj me
    arr.unshift({
      id: me.id,
      // auth.user (User) nema profile/avatara po tipu → koristimo sigurna polja + fallback
      name: me.name || "",
      display_name: (me as any).display_name ?? me.name ?? "",
      avatar: (me as any).avatar_url ?? null,
      pivot: { rsvp: next },
      rsvp: next,
    });
    inc(next);
  }
  else {
    // izmeni postojeći
    if (arr[idx].pivot)
      arr[idx].pivot.rsvp = next;
    arr[idx].rsvp = next;
    move(prev, next);
  }

  // rebroadcast za reaktivnost
  attendeesRes.value = { ...store, data: [...arr], counts: { ...store.counts } };
}

// --- API helpers (attendees rute) ---
async function apiSetRsvp(newVal: Exclude<RSVP, null>, hadPrev: boolean) {
  if (!hadPrev) {
    await $api.post(`/v1/events/${id.value}/attendees`, { rsvp: newVal }, { withCredentials: true });
  }
  else {
    await $api.patch(
      `/v1/events/${id.value}/attendees`,
      { rsvp: newVal },
      { withCredentials: true },
    );
  }
}
async function apiClearRsvp() {
  await $api.delete(`/v1/events/${id.value}/attendees`, { withCredentials: true });
}

// --- UI handlers ---
async function toggleRsvp(next: Exclude<RSVP, null>) {
  if (rsvpLoading.value)
    return;

  const prev = rsvp.value;
  const newVal: RSVP = next;

  rsvpLoading.value = true;
  rsvp.value = newVal; // optimistički za header
  applyLocalRsvp(newVal); // optimistički za liste + counts

  let apiOk = true;
  try {
    await apiSetRsvp(newVal as Exclude<RSVP, null>, prev !== null);
  }
  catch (e: any) {
    apiOk = false;
    // rollback oba state-a
    rsvp.value = prev;
    applyLocalRsvp(prev);
    console.error(e);
    toast.error(e?.response?.data?.message ?? "RSVP nije uspeo.");
  }
  finally {
    rsvpLoading.value = false;
  }

  if (apiOk) {
    void refresh(); // tihi sync sa serverom
    void refreshAttendees?.(); // tihi sync sa serverom
  }
}

async function clearRsvp() {
  if (rsvpLoading.value || rsvp.value === null)
    return;

  const prev = rsvp.value;

  rsvpLoading.value = true;
  rsvp.value = null; // optimistički
  applyLocalRsvp(null); // optimistički

  let apiOk = true;
  try {
    await apiClearRsvp();
  }
  catch (e: any) {
    apiOk = false;
    rsvp.value = prev;
    applyLocalRsvp(prev);
    console.error(e);
    toast.error(e?.response?.data?.message ?? "Poništavanje nije uspelo.");
  }
  finally {
    rsvpLoading.value = false;
  }

  if (apiOk) {
    void refresh();
    void refreshAttendees?.();
  }
}

// ako se menja id rute, osveži obe strane
watch(id, () => {
  void refresh();
  void refreshAttendees?.();
});
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
      <!-- LEVA KOLONA -->
      <div class="lg:col-span-2 space-y-2">
        <!-- info -->
        <div class="card bg-base-100 shadow">
          <div class="card-body">
            <h1 class="card-title text-2xl">
              {{ ev.title }}
              <span class="badge badge-outline">{{ ev.status }}</span>
            </h1>

            <div class="flex flex-wrap items-center gap-2 opacity-80">
              <span class="badge badge-neutral">{{ dateText }}</span>
              <span v-if="ev.group?.name" class="badge badge-ghost">{{ ev.group.name }}</span>
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
            </div>
          </div>
        </div>

        <!-- RSVP -->
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
                    type="radio"
                    name="rsvp"
                    class="radio"
                    :checked="rsvp === 'yes'"
                    :disabled="rsvpLoading"
                    @change="toggleRsvp('yes')"
                  >
                  <span class="label-text">Dolazim</span>
                </div>
              </label>

              <label class="form-control cursor-pointer">
                <div class="label justify-start gap-3">
                  <input
                    type="radio"
                    name="rsvp"
                    class="radio"
                    :checked="rsvp === 'undecided'"
                    :disabled="rsvpLoading"
                    @change="toggleRsvp('undecided')"
                  >
                  <span class="label-text">Možda</span>
                </div>
              </label>

              <label class="form-control cursor-pointer">
                <div class="label justify-start gap-3">
                  <input
                    type="radio"
                    name="rsvp"
                    class="radio"
                    :checked="rsvp === 'no'"
                    :disabled="rsvpLoading"
                    @change="toggleRsvp('no')"
                  >
                  <span class="label-text">Ne dolazim</span>
                </div>
              </label>
            </div>

            <button
              class="btn btn-ghost btn-xs mt-2"
              :disabled="rsvpLoading || rsvp === null"
              @click="clearRsvp"
            >
              Poništi
            </button>

            <div class="divider" />

            <!-- Potvrdili -->
            <div>
              <h3 class="font-semibold mb-2">
                Potvrdili dolazak: {{ counts.yes }} / {{ counts.total }}
              </h3>
              <div v-if="attendeesYes.length" class="flex flex-wrap gap-3">
                <div
                  v-for="a in attendeesYes"
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

            <!-- Ne dolaze -->
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

      <!-- DESNA KOLONA: mapa -->
      <div class="lg:col-span-2">
        <div class="card bg-base-100 shadow">
          <div class="card-body">
            <h2 class="card-title">
              Mapa
            </h2>
            <div class="rounded-xl overflow-hidden border border-base-300">
              <div
                class="w-full h-full min-h-[50svh] overflow-hidden rounded-2xl border border-base-300 relative"
                style="height: 420px"
              >
                <ClientOnly>
                  <MapCoordPicker
                    v-if="hasCoords"
                    :coords="coords"
                    :editable="false"
                  />
                  <div v-else class="p-4 text-sm opacity-70">
                    Lokacija za ovaj događaj nije postavljena.
                  </div>
                  <template #fallback>
                    <div class="p-4 text-sm opacity-70">
                      Mapa se učitava…
                    </div>
                  </template>
                </ClientOnly>
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
