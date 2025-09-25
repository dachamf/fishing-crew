<script setup lang="ts">
import type { Coords } from "~/types/api";

const props = defineProps<{
  title?: string;
  sessionId?: number; // ako postoji -> PATCH
  modelValue: Coords; // v-model
  editable?: boolean; // default: true
  height?: string | number; // npr. 300 ili "300px"
  autoSave?: boolean; // NEW: default true
  debounceMs?: number; // NEW: default 500
}>();
const emit = defineEmits<{
  (e: "update:modelValue", v: Coords): void;
  (e: "saved", v: Coords): void;
}>();

const { $api } = useNuxtApp();
const saving = ref(false);
const savingName = ref(false);
const lastSaved = ref<Coords | null>(null);
const isEditable = computed(() => props.editable ?? true);
const pickerHeight = computed<string>(() =>
  typeof props.height === "number" ? `${props.height}px` : (props.height ?? "600px"),
);
const autoSave = computed(() => props.autoSave ?? true);
const debounceMs = computed(() => props.debounceMs ?? 500);

const coords = computed<Coords>({
  get: () => props.modelValue,
  set: v => emit("update:modelValue", v),
});

function eq(a: Coords | null, b: Coords | null) {
  if (!a || !b)
    return !a && !b;
  const fx = (n: number | null) => (n == null ? null : Number(n.toFixed(6)));
  return fx(a.lat) === fx(b.lat) && fx(a.lng) === fx(b.lng);
}

let t: any = null;
async function patchIfNeeded() {
  if (!props.sessionId)
    return; // nema sesije -> nema PATCH
  if (!coords.value || coords.value.lat == null || coords.value.lng == null)
    return;
  if (eq(coords.value, lastSaved.value))
    return;

  saving.value = true;
  try {
    await $api.patch(
      `/v1/sessions/${props.sessionId}`,
      {
        latitude: coords.value.lat,
        longitude: coords.value.lng,
      },
      { withCredentials: true },
    );
    lastSaved.value = { ...coords.value };
    emit("saved", coords.value);
    // (tiho) bez toast-a za autosave; za ručni save imamo dugme i toast
  }
  catch (e: any) {
    useToast().error(e?.response?.data?.message ?? "Greška pri čuvanju lokacije");
  }
  finally {
    saving.value = false;
  }
}

watch(
  () => ({ ...coords.value, sid: props.sessionId, as: autoSave.value }),
  () => {
    if (!autoSave.value)
      return;
    clearTimeout(t);
    t = setTimeout(patchIfNeeded, debounceMs.value);
  },
  { deep: true },
);

async function saveIfSession() {
  await patchIfNeeded();
  if (!autoSave.value)
    useToast().success("Lokacija sačuvana ✓");
}

async function fillLocationName() {
  if (!props.sessionId || coords.value.lat == null || coords.value.lng == null)
    return;
  savingName.value = true;
  try {
    const { data } = await $api.get("/v1/geocode/reverse", {
      params: { lat: coords.value.lat, lng: coords.value.lng, lang: "sr" },
      withCredentials: true,
    });
    const name = data?.display_name || null;
    if (name) {
      await $api.patch(
        `/v1/sessions/${props.sessionId}`,
        { location_name: name },
        { withCredentials: true },
      );
      useToast().success("Naziv lokacije sačuvan ✓");
      emit("saved", coords.value);
    }
    else {
      useToast().info("Nije pronađen naziv za ovu poziciju.");
    }
  }
  catch (e: any) {
    useToast().error(e?.response?.data?.message ?? "Greška pri geokodiranju");
  }
  finally {
    savingName.value = false;
  }
}
</script>

<template>
  <div class="card bg-base-100 shadow-lg w-full h-full">
    <div class="card-body space-y-4">
      <div class="flex items-center justify-between">
        <h2 class="card-title">
          {{ title ?? 'Lokacija sesije' }}
        </h2>
        <div class="flex items-center gap-2 text-sm opacity-80">
          <span v-if="modelValue?.lng != null && modelValue?.lat != null">
            {{ Number(modelValue.lng).toFixed(6) }}, {{ Number(modelValue.lat).toFixed(6) }}
          </span>
        </div>
      </div>

      <MapCoordPicker
        v-model:coords="coords"
        class="w-full"
        :editable="isEditable"
        :height="pickerHeight"
      />

      <div class="grid md:grid-cols-2 gap-3">
        <div class="form-control">
          <label class="label"><span class="label-text">Latitude</span></label>
          <input
            :value="coords.lat ?? ''"
            class="input input-bordered"
            disabled
          >
        </div>
        <div class="form-control">
          <label class="label"><span class="label-text">Longitude</span></label>
          <input
            :value="coords.lng ?? ''"
            class="input input-bordered"
            disabled
          >
        </div>
      </div>

      <div v-if="sessionId && !autoSave" class="pt-2">
        <div class="flex items-center gap-2">
          <button
            class="btn btn-ghost btn-sm"
            :disabled="saving || savingName"
            @click="saveIfSession"
          >
            {{ saving ? 'Čuvam…' : 'Sačuvaj lokaciju' }}
          </button>
          <button
            class="btn btn-outline btn-sm"
            :disabled="savingName"
            @click="fillLocationName"
          >
            {{ savingName ? 'Upisujem…' : '✨ Upiši naziv' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
