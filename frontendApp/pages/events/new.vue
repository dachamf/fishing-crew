<script lang="ts" setup>
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

/** Sync iz map komponente u formu */
function onCoords(v: { lng: number | null; lat: number | null }) {
  form.longitude = v.lng;
  form.latitude = v.lat;
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

        <button
          :disabled="submitting"
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
          @update:coords="onCoords"
        />
      </div>
    </section>
  </div>
</template>
