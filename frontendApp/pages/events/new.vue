<script lang="ts" setup>
import type { GeoSuggestion } from "~/composables/useNominatimSearch";

import { useNominatimSearch } from "~/composables/useNominatimSearch";
import { toErrorMessage } from "~/utils/http";

useSeoMeta({ title: "Novi događaj" });

const { error, success, info } = useToast();
const { $api } = useNuxtApp() as any;
const router = useRouter();

const form = reactive({
  title: "",
  description: "",
  starts_at: "",
  location_name: "",
  latitude: null as number | null,
  longitude: null as number | null,
});

// Nominatim search
const geo = useNominatimSearch({ limit: 8 });

/** Sync iz map komponente u formu */
function onCoords(v: { lng: number | null; lat: number | null }) {
  form.longitude = v.lng;
  form.latitude = v.lat;
}

const locationQuery = computed<string>({
  get: () => geo.q.value ?? "",
  set: (v) => {
    geo.q.value = typeof v === "string" ? v : String(v ?? "");
  },
});

const results = computed<GeoSuggestion[]>(() => {
  const raw = geo.items?.value as any;
  return Array.isArray(raw) ? raw : [];
});

const showResults = computed(
  () =>
    locationQuery.value.trim().length >= geo.minLen
    && (results.value.length > 0 || geo.loading.value || !!geo.error.value),
);

function onAddSuggestion(s: GeoSuggestion) {
  form.longitude = s.lon as number;
  form.latitude = s.lat as number;
  if (!form.location_name?.trim())
    form.location_name = s.label || (s as any).display_name || "";
  locationQuery.value = s.label || (s as any).display_name || "";
  geo.clear();
}

const submitting = ref(false);
async function onSubmit() {
  // mala validacija
  if (!form.title.trim())
    return info("Unesi naziv događaja");

  if (!Number.isFinite(Number(form.longitude)) || !Number.isFinite(Number(form.latitude))) {
    return info("Odaberi lokaciju na mapi (dvoklik pa pomeri pin).");
  }

  try {
    submitting.value = true;
    await $api.post("/v1/groups/1/events", {
      title: form.title,
      description: form.description || null,
      start_at: form.starts_at || null,
      location_name: form.location_name || null,
      longitude: form.longitude,
      latitude: form.latitude,
    });
    await router.push("/events");
  }
  catch (e: any) {
    error(toErrorMessage(e?.message || "Greška pri kreiranju događaja."));
  }
  finally {
    success("Događaj uspešno kreiran.");
    submitting.value = false;
  }
}

const disabledSearch = computed<boolean>(() => !!geo.loading || !(results.value?.length ?? 0));
const disabledCreate = computed<boolean>(
  () => !!geo.loading || (locationQuery.value?.trim().length ?? 0) < geo.minLen,
);
const geoErrorText = computed<string>(() => geo.error?.value ?? "");
</script>

<template>
  <!-- Forma + Mapa -->
  <div class="grid min-h-dvh gap-6 p-4 lg:grid-cols-2 lg:p-6">
    <!-- Forma -->
    <section class="max-w-2xl w-full mx-auto">
      <h1 class="text-2xl font-semibold">
        Novi događaj
      </h1>
      <p class="opacity-70 mb-4">
        Popuni detalje i izaberi lokaciju na mapi.
      </p>

      <form class="space-y-4" @submit.prevent="onSubmit">
        <div class="form-control">
          <label class="label"><span class="label-text">Naziv</span></label>
          <input
            v-model="form.title"
            class="input input-bordered w-full"
            required
            type="text"
          >
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="form-control">
            <label class="label"><span class="label-text">Vreme početka</span></label>
            <input
              v-model="form.starts_at"
              class="input input-bordered w-full"
              type="datetime-local"
            >
          </div>

          <div class="form-control">
            <label class="label"><span class="label-text">Lokacija (opisno)</span></label>
            <input
              v-model="form.location_name"
              class="input input-bordered w-full"
              placeholder="npr. Ada Ciganlija"
              type="text"
            >
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="form-control">
            <label class="label"><span class="label-text">Longitude (lng)</span></label>
            <input
              v-model.number="form.longitude"
              class="input input-bordered w-full"
              disabled
              placeholder="Klikni dvaput na mapi"
              type="number"
            >
          </div>
          <div class="form-control">
            <label class="label"><span class="label-text">Latitude (lat)</span></label>
            <input
              v-model.number="form.latitude"
              class="input input-bordered w-full"
              disabled
              placeholder="Klikni dvaput na mapi"
              type="number"
            >
          </div>
        </div>

        <div class="form-control">
          <label class="label"><span class="label-text">Opis</span></label>
          <textarea
            v-model="form.description"
            class="textarea textarea-bordered w-full"
            rows="4"
          />
        </div>

        <!-- Pretraga lokacije (OSM/Nominatim) -->
        <div class="form-control">
          <label class="label"><span class="label-text">Pretraga lokacije (OSM/Nominatim)</span></label>

          <div class="join w-full">
            <input
              v-model="locationQuery"
              class="input input-bordered join-item w-full"
              type="search"
              placeholder="npr. Ada Ciganlija, Beograd"
              autocomplete="off"
              @keydown.enter.stop.prevent="geo.searchNow()"
            >

            <!-- Clear (X) — čisti samo rezultate, ne form koordinate -->
            <button
              type="button"
              class="btn join-item btn-ghost"
              :disabled="disabledSearch"
              aria-label="Očisti rezultate"
              title="Očisti rezultate"
              @click="geo.clear()"
            >
              ✕
            </button>

            <button
              type="button"
              class="btn join-item"
              :disabled="disabledCreate"
              @click="geo.searchNow()"
            >
              {{ geo.loading ? 'Tražim…' : 'Pretraži' }}
            </button>
          </div>

          <div v-if="showResults" class="mt-2 rounded-xl border border-base-300 bg-base-100 shadow">
            <div v-if="geoErrorText.length" class="alert alert-warning m-2">
              {{ geoErrorText }}
            </div>
            <ul v-else class="max-h-64 overflow-auto divide-y divide-base-300">
              <li v-if="geo.loading" class="p-3 text-sm opacity-70">
                Pretražujem…
              </li>

              <li
                v-for="(s, i) in results"
                :key="s.id ?? (s as any).place_id ?? i"
                class="flex items-start gap-3 p-3"
              >
                <div class="min-w-0 flex-1">
                  <!-- 👇 forsiraj vidljivu boju teksta -->
                  <div class="text-sm font-medium whitespace-normal break-words text-base-content">
                    {{ s.label || (s as any).display_name || '—' }}
                  </div>

                  <div class="text-xs opacity-70 text-base-content/80 mt-0.5">
                    {{
                      typeof s.lat === 'number'
                        ? s.lat.toFixed(5)
                        : s.lat
                          ? Number(s.lat).toFixed(5)
                          : '—'
                    }},
                    {{
                      typeof s.lon === 'number'
                        ? s.lon.toFixed(5)
                        : s.lon
                          ? Number(s.lon).toFixed(5)
                          : '—'
                    }}
                    <span v-if="s.type" class="ml-2 badge badge-ghost badge-xs">{{ s.type }}</span>
                  </div>
                </div>

                <button
                  type="button"
                  class="btn btn-sm btn-primary"
                  @click="onAddSuggestion(s)"
                >
                  Dodaj
                </button>
              </li>
            </ul>
          </div>
        </div>

        <button
          :disabled="disabledCreate"
          class="btn btn-primary"
          type="submit"
        >
          {{ submitting ? 'Kreiranje…' : 'Kreiraj događaj' }}
        </button>
      </form>
    </section>
    <section class="min-h-[60svh] lg:sticky lg:top-16">
      <div class="w-full h-full overflow-hidden rounded-2xl border border-base-300 relative">
        <MapCoordPicker
          :coords="{ lng: form.longitude, lat: form.latitude }"
          :editable="true"
          :recenter-on-change="true"
          @update:coords="onCoords"
        />
      </div>
    </section>
  </div>
</template>
