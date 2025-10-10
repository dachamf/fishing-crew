<script lang="ts" setup>
import { unref } from "vue";

import type { RSVP, UserLite } from "~/types/api";

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
const meId = computed(() => auth.user.value?.id ?? null);
const isOwner = computed(() => {
  const me = meId.value;
  return !!me && (ev.value?.user_id === me || ev.value?.owner_id === me);
});

// --- RSVP OPEN/CLOSED ---
const isStarted = computed(() => {
  const d = ev.value?.start_at ? new Date(ev.value.start_at) : null;
  if (!d)
    return false;
  return d.getTime() <= Date.now();
});
const isRsvpOpen = computed(() => !isStarted.value);

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
  if (!attendeesRes.value) {
    attendeesRes.value = { data: [], counts: { yes: 0, undecided: 0, no: 0, total: 0 } };
  }
  const store = attendeesRes.value as any;
  store.data = Array.isArray(store.data) ? store.data : [];
  store.counts = store.counts ?? { yes: 0, undecided: 0, no: 0, total: 0 };

  const me = auth.user.value as UserLite | undefined;
  if (!me?.id)
    return;

  const arr = store.data as any[];
  const idx = arr.findIndex((x: any) => x.id === me.id);
  const prev: RSVP = idx !== -1 ? (arr[idx]?.pivot?.rsvp ?? arr[idx]?.rsvp ?? null) : null;

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
    if (idx !== -1) {
      arr.splice(idx, 1);
      store.counts.total = Math.max(0, (store.counts.total ?? 0) - 1);
      if (prev && (prev === "yes" || prev === "undecided" || prev === "no")) {
        store.counts[prev] = Math.max(0, (store.counts[prev] ?? 0) - 1);
      }
    }
  }
  else if (idx === -1) {
    arr.unshift({
      id: me.id,
      name: me.name || "",
      display_name: (me as any).display_name ?? me.name ?? "",
      avatar: (me as any).avatar_url ?? null,
      pivot: { rsvp: next },
      rsvp: next,
    });
    store.counts.total = (store.counts.total ?? 0) + 1;
    move(undefined, next);
  }
  else {
    if (arr[idx].pivot)
      arr[idx].pivot.rsvp = next;
    arr[idx].rsvp = next;
    move(prev, next);
  }

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

// --- UI handlers RSVP ---
async function toggleRsvp(next: Exclude<RSVP, null>) {
  if (!isRsvpOpen.value || rsvpLoading.value)
    return;

  const prev = rsvp.value;
  const newVal: RSVP = next;

  rsvpLoading.value = true;
  rsvp.value = newVal;
  applyLocalRsvp(newVal);

  let apiOk = true;
  try {
    await apiSetRsvp(newVal as Exclude<RSVP, null>, prev !== null);
  }
  catch (e: any) {
    apiOk = false;
    rsvp.value = prev;
    applyLocalRsvp(prev);
    console.error(e);
    toast.error(e?.response?.data?.message ?? "RSVP nije uspeo.");
  }
  finally {
    rsvpLoading.value = false;
  }

  if (apiOk) {
    void refresh();
    void refreshAttendees?.();
  }
}

async function clearRsvp() {
  if (!isRsvpOpen.value || rsvpLoading.value || rsvp.value === null)
    return;

  const prev = rsvp.value;
  rsvpLoading.value = true;
  rsvp.value = null;
  applyLocalRsvp(null);

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

/* =========================
   FOTO SEKCIJA (upload/lista)
   ========================= */
type EventPhoto = {
  id: number;
  url: string;
  created_at?: string;
  user?: { id: number; name?: string; display_name?: string; avatar_url?: string | null };
};

const photos = ref<EventPhoto[]>([]);
const photosLoading = ref(false);
const uploading = ref(false);
const selectedFiles = ref<File[]>([]);

// učitaj fotke
async function loadPhotos() {
  photosLoading.value = true;
  try {
    const res = await $api.get(`/v1/events/${id.value}/photos`);
    photos.value = Array.isArray(res.data) ? res.data : (res.data?.items ?? []);
  }
  catch (e) {
    console.error(e);
    photos.value = [];
  }
  finally {
    photosLoading.value = false;
  }
}

onMounted(loadPhotos);

// broj mojih fotki i remaining
const myPhotosCount = computed(
  () => photos.value.filter(p => p.user?.id && p.user.id === meId.value).length,
);
const remainingUploads = computed(() => Math.max(0, 5 - myPhotosCount.value));

// može upload? — samo kada event traje ili je završen, i ako imam “slotova”
const canUpload = computed(() => isStarted.value && remainingUploads.value > 0);

// selekcija fajlova (klasični <input type="file" multiple>)
function onPickFiles(ev: Event) {
  const input = ev.target as HTMLInputElement;
  const files = input.files ? Array.from(input.files) : [];
  if (!files.length)
    return;

  // clamp da ne pređe limit
  const allowed = remainingUploads.value;
  selectedFiles.value = files.slice(0, allowed);
  if (files.length > allowed) {
    toast.warning(`Možeš dodati još ${allowed} fotografija (limit je 5 po učesniku).`);
  }
}

// upload
async function doUpload() {
  if (!canUpload.value || !selectedFiles.value.length)
    return;
  uploading.value = true;
  try {
    const fd = new FormData();
    // backend prihvata i 'photo' i 'photos[]' — koristimo 'photos[]'
    for (const f of selectedFiles.value) fd.append("photos[]", f);

    const res = await $api.post(`/v1/events/${id.value}/photos`, fd, {
      withCredentials: true,
    });

    const items: EventPhoto[] = res?.data?.items ?? [];
    if (Array.isArray(items) && items.length) {
      // dodaj na vrh liste
      photos.value = [...items, ...photos.value];
      toast.success(`Dodata ${items.length} ${items.length === 1 ? "fotografija" : "fotografije"}.`);
    }
    selectedFiles.value = [];
  }
  catch (e: any) {
    console.error(e);
    toast.error(e?.response?.data?.message ?? "Upload nije uspeo.");
  }
  finally {
    uploading.value = false;
  }
}

// delete (dozvoljeno: vlasnik fotke ili vlasnik eventa — policy na BE)
async function deletePhoto(p: EventPhoto) {
  try {
    await $api.delete(`/v1/events/${id.value}/photos/${p.id}`);
    photos.value = photos.value.filter(x => x.id !== p.id);
  }
  catch (e: any) {
    console.error(e);
    toast.error(e?.response?.data?.message ?? "Brisanje nije uspelo.");
  }
}

function canDelete(p: EventPhoto) {
  return p.user?.id === meId.value || isOwner.value;
}

// --- LIGHTBOX ---
const lbOpen = ref(false);
const lbIndex = ref<number>(-1);

function openLightbox(i: number) {
  if (!photos.value.length)
    return;
  lbIndex.value = i;
  lbOpen.value = true;
  // zaključa scroll u pozadini
  document?.body && (document.body.style.overflow = "hidden");
}
function closeLightbox() {
  lbOpen.value = false;
  lbIndex.value = -1;
  document?.body && (document.body.style.overflow = "");
}
function prevPhoto() {
  if (!photos.value.length)
    return;
  lbIndex.value = (lbIndex.value - 1 + photos.value.length) % photos.value.length;
}
function nextPhoto() {
  if (!photos.value.length)
    return;
  lbIndex.value = (lbIndex.value + 1) % photos.value.length;
}
const currentPhoto = computed(() => photos.value[lbIndex.value] ?? null);

// tastatura (← → Esc)
function onKeyDown(e: KeyboardEvent) {
  if (!lbOpen.value)
    return;
  if (e.key === "Escape")
    closeLightbox();
  else if (e.key === "ArrowLeft")
    prevPhoto();
  else if (e.key === "ArrowRight")
    nextPhoto();
}
onMounted(() => window.addEventListener("keydown", onKeyDown));
onUnmounted(() => window.removeEventListener("keydown", onKeyDown));
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
            <div class="flex items-center justify-between">
              <h2 class="card-title">
                Prisustvo
              </h2>
              <span
                v-if="!isRsvpOpen"
                class="badge badge-warning"
                title="RSVP je zatvoren jer je događaj počeo ili je završen"
              >
                RSVP zatvoren
              </span>
            </div>

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
                    :disabled="rsvpLoading || !isRsvpOpen"
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
                    :disabled="rsvpLoading || !isRsvpOpen"
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
                    :disabled="rsvpLoading || !isRsvpOpen"
                    @change="toggleRsvp('no')"
                  >
                  <span class="label-text">Ne dolazim</span>
                </div>
              </label>
            </div>

            <button
              class="btn btn-ghost btn-xs mt-2"
              :disabled="rsvpLoading || rsvp === null || !isRsvpOpen"
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
                      <img
                        :src="a.avatar || a.profile?.avatar_url || '/icons/icon-64.png'"
                        alt=""
                      >
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
                      <img
                        :src="a.avatar || a.profile?.avatar_url || '/icons/icon-64.png'"
                        alt=""
                      >
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

        <!-- FOTKE (upload/lista) -->
        <div class="card bg-base-100 shadow">
          <div class="card-body">
            <div class="flex items-center justify-between gap-3">
              <h2 class="card-title">
                Fotke sa događaja
              </h2>
              <span v-if="isStarted" class="badge badge-success">Otvoreno</span>
              <span v-else class="badge">Dostupno od početka događaja</span>
            </div>

            <!-- Upload kontrola -->
            <div class="rounded-lg border border-dashed border-base-300 p-3">
              <div class="flex flex-col md:flex-row md:items-center gap-3 justify-between">
                <div class="text-sm">
                  <div class="font-medium">
                    Tvoje fotografije: {{ myPhotosCount }} / 5
                  </div>
                  <div class="opacity-70">
                    Maks. 5MB po fajlu. Preostalo: {{ remainingUploads }}.
                  </div>
                </div>

                <div class="flex items-center gap-2">
                  <input
                    type="file"
                    class="file-input file-input-bordered file-input-sm"
                    :disabled="!canUpload || uploading"
                    accept="image/*"
                    multiple
                    @change="onPickFiles"
                  >
                  <button
                    class="btn btn-primary btn-sm"
                    :class="{ loading: uploading }"
                    :disabled="!canUpload || uploading || selectedFiles.length === 0"
                    @click="doUpload"
                  >
                    Dodaj {{ selectedFiles.length ? `(${selectedFiles.length})` : '' }}
                  </button>
                </div>
              </div>
              <div v-if="!isStarted" class="text-xs opacity-70 mt-1">
                Upload će biti omogućen kada događaj počne.
              </div>
            </div>

            <!-- Lista fotki -->
            <div class="mt-3">
              <div v-if="photosLoading" class="flex items-center gap-2">
                <span class="loading loading-spinner loading-sm" /> Učitavanje fotki…
              </div>

              <div v-else-if="photos.length === 0" class="opacity-70 text-sm">
                Još nema fotografija.
              </div>

              <div v-else class="grid grid-cols-2 md:grid-cols-3 gap-3">
                <div
                  v-for="(p, i) in photos"
                  :key="p.id"
                  class="group relative rounded-xl overflow-hidden border border-base-300"
                >
                  <button
                    class="block w-full"
                    :title="p.user?.display_name || p.user?.name"
                    @click="openLightbox(i)"
                  >
                    <img
                      :src="p.url"
                      class="w-full h-40 object-cover transition-transform group-hover:scale-[1.02]"
                      alt=""
                    >
                  </button>

                  <div
                    class="absolute bottom-0 left-0 right-0 bg-base-100/80 backdrop-blur p-2 text-xs flex items-center justify-between"
                  >
                    <div class="flex items-center gap-2 min-w-0">
                      <div class="avatar">
                        <div class="w-6 rounded-full border border-base-300 overflow-hidden">
                          <img :src="p.user?.avatar_url || '/icons/icon-64.png'" alt="">
                        </div>
                      </div>
                      <span class="truncate">{{
                        p.user?.display_name || p.user?.name || `#${p.user?.id}`
                      }}</span>
                    </div>
                    <button
                      v-if="canDelete(p)"
                      class="btn btn-ghost btn-xs opacity-70 hover:opacity-100"
                      title="Obriši"
                      @click="deletePhoto(p)"
                    >
                      <Icon name="tabler:trash" size="16" />
                    </button>
                  </div>
                </div>
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

    <!-- LIGHTBOX -->
    <transition name="fade">
      <div
        v-if="lbOpen && currentPhoto"
        class="fixed inset-0 z-[100] bg-black/80 backdrop-blur-sm flex items-center justify-center p-4"
        @click.self="closeLightbox"
      >
        <div class="relative max-w-[95vw] max-h-[90vh]">
          <!-- Close -->
          <button
            class="btn btn-sm btn-circle absolute -top-3 -right-3"
            aria-label="Zatvori"
            @click="closeLightbox"
          >
            ✕
          </button>

          <!-- Slika -->
          <img
            :src="currentPhoto.url"
            class="max-w-[95vw] max-h-[80vh] object-contain rounded-xl shadow-2xl"
            :alt="currentPhoto.user?.display_name || currentPhoto.user?.name || ''"
            draggable="false"
          >

          <!-- Navigacija -->
          <div class="absolute inset-y-0 left-0 flex items-center">
            <button
              class="btn btn-circle btn-ghost"
              aria-label="Prethodna"
              @click.stop="prevPhoto"
            >
              <Icon name="tabler:chevron-left" size="24" />
            </button>
          </div>
          <div class="absolute inset-y-0 right-0 flex items-center">
            <button
              class="btn btn-circle btn-ghost"
              aria-label="Sledeća"
              @click.stop="nextPhoto"
            >
              <Icon name="tabler:chevron-right" size="24" />
            </button>
          </div>

          <!-- Caption -->
          <div class="mt-2 text-center text-sm text-base-100">
            <div class="inline-flex items-center gap-2 bg-base-100/20 rounded-full px-3 py-1">
              <div class="avatar">
                <div class="w-6 rounded-full border border-white/30 overflow-hidden">
                  <img :src="currentPhoto.user?.avatar_url || '/icons/icon-64.png'" alt="">
                </div>
              </div>
              <span class="truncate max-w-[60vw]">
                {{
                  currentPhoto.user?.display_name
                    || currentPhoto.user?.name
                    || `#${currentPhoto.user?.id}`
                }}
              </span>
              <span v-if="currentPhoto.created_at" class="opacity-80">
                • {{ new Date(currentPhoto.created_at).toLocaleString('sr-RS') }}
              </span>
              <span class="opacity-80"> • {{ lbIndex + 1 }} / {{ photos.length }}</span>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
