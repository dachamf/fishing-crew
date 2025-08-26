<script setup lang="ts">
import type { EventDTO } from "~/types/event";

const props = defineProps<{ groupId: number }>();
const emit = defineEmits<{ (e: "created", event: EventDTO): void }>();
const { create } = useEventStore();
const toast = useToast();

const form = reactive({
  title: "",
  location_name: "",
  latitude: null as number | null,
  longitude: null as number | null,
  start_at_local: "", // datetime-local
  description: "",
});

const busy = ref(false);
const errors = ref<Record<string, string>>({});

function toIso(dtLocal: string) {
  // Pretvori value iz <input type="datetime-local"> u ISO
  // (tretiramo kao lokalno vreme)
  if (!dtLocal)
    return "";
  const d = new Date(dtLocal);
  // ako želiš bez TZ: `${dtLocal}:00`
  return d.toISOString();
}

function validate() {
  errors.value = {};
  if (!form.title.trim())
    errors.value.title = "Obavezan naziv.";
  if (!form.start_at_local)
    errors.value.start_at_local = "Obavezan datum i vreme.";
  if (form.latitude != null && (form.latitude < -90 || form.latitude > 90))
    errors.value.latitude = "Lat mora biti između -90 i 90.";
  if (form.longitude != null && (form.longitude < -180 || form.longitude > 180))
    errors.value.longitude = "Lng mora biti između -180 i 180.";
  return Object.keys(errors.value).length === 0;
}

async function submit() {
  if (busy.value)
    return;
  if (!validate())
    return;
  busy.value = true;
  try {
    const payload = {
      title: form.title.trim(),
      location_name: form.location_name || null,
      latitude: form.latitude,
      longitude: form.longitude,
      start_at: toIso(form.start_at_local),
      description: form.description || null,
    };
    const ev = await create(props.groupId, payload);
    toast.success("Događaj je kreiran.");
    emit("created", ev);
  }
  catch (e: any) {
    const apiErr = e?.response?.data;
    if (apiErr?.errors) {
      // mapiraj Laravel errors
      for (const [k, arr] of Object.entries(apiErr.errors)) {
        errors.value[k] = toErrorMessage((arr as string[])[0]);
      }
    }
    toast.error(apiErr?.message || "Greška pri kreiranju događaja.");
  }
  finally {
    busy.value = false;
  }
}

// (Opcionalno) preuzmi koordinate sa mape:
function onPickFromMap(lat: number, lng: number) {
  form.latitude = Number(lat.toFixed(6));
  form.longitude = Number(lng.toFixed(6));
}
</script>

<template>
  <form
    class="space-y-4"
    :aria-busy="busy"
    @submit.prevent="submit"
  >
    <div>
      <label class="label"><span class="label-text">Naslov</span></label>
      <input
        v-model="form.title"
        class="input input-bordered w-full"
        required
      >
      <p v-if="errors.title" class="text-error text-sm mt-1">
        {{ errors.title }}
      </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
      <div>
        <label class="label"><span class="label-text">Lokacija (naziv)</span></label>
        <input
          v-model="form.location_name"
          class="input input-bordered w-full"
          placeholder="Srebrno jezero"
        >
      </div>
      <div>
        <label class="label"><span class="label-text">Datum & vreme</span></label>
        <input
          v-model="form.start_at_local"
          type="datetime-local"
          class="input input-bordered w-full"
          required
        >
        <p v-if="errors.start_at_local" class="text-error text-sm mt-1">
          {{ errors.start_at_local }}
        </p>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
      <div>
        <label class="label"><span class="label-text">Latitude</span></label>
        <input
          v-model.number="form.latitude"
          type="number"
          step="0.000001"
          min="-90"
          max="90"
          class="input input-bordered w-full"
        >
        <p v-if="errors.latitude" class="text-error text-sm mt-1">
          {{ errors.latitude }}
        </p>
      </div>
      <div>
        <label class="label"><span class="label-text">Longitude</span></label>
        <input
          v-model.number="form.longitude"
          type="number"
          step="0.000001"
          min="-180"
          max="180"
          class="input input-bordered w-full"
        >
        <p v-if="errors.longitude" class="text-error text-sm mt-1">
          {{ errors.longitude }}
        </p>
      </div>
    </div>

    <!-- (Opcionalno) map picker slot -->
    <ClientOnly>
      <LocationPicker class="mt-2" @pick="onPickFromMap" />
    </ClientOnly>

    <div>
      <label class="label"><span class="label-text">Opis (opciono)</span></label>
      <textarea
        v-model="form.description"
        class="textarea textarea-bordered w-full"
        rows="4"
      />
    </div>

    <button
      class="btn btn-primary"
      type="submit"
      :disabled="busy"
    >
      <span v-if="busy" class="loading loading-spinner loading-sm mr-2" />
      Sačuvaj
    </button>
  </form>
</template>
