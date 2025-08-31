<script lang="ts" setup>
import type { ID, NewCatchPayload } from "~/types/api";

import { datetimeLocalToISO, toDatetimeLocal } from "~/utils/datetime";

defineOptions({ name: "CatchCreatePage" });

const { $api } = useNuxtApp() as any;
const router = useRouter();
const { success, error, info } = useToast();

/** Me + default group */
const { data: me } = useAsyncData("me:new", async () => (await $api.get("/v1/me")).data, {
  server: false,
  immediate: true,
});

/** Session picker (jedan izvor istine) */
const { openFirst } = useMySessions();
const sessionId = ref<ID | null>(openFirst.value?.id ?? null);

/** Koordinate sesije (uvek prisutne u UI, autosave kad postoji sessionId) */
const sessionCoords = ref<{ lng: number | null; lat: number | null }>({ lng: null, lat: null });

watch(openFirst, (v) => {
  if (!sessionId.value && v?.id)
    sessionId.value = v.id;
});

// kad se promeni sessionId → učitaj postojeće koordinate (ako postoje)
watch(sessionId, async (id) => {
  if (!id)
    return;
  try {
    const { data } = await $api.get(`/v1/sessions/${id}`, { withCredentials: true });
    sessionCoords.value = {
      lng: data?.longitude ?? null,
      lat: data?.latitude ?? null,
    };
  }
  catch {}
});

/** Species */
const { q, items, loading: speciesLoading } = useSpeciesSearch();
const pickedSpecies = ref<{ id?: number; label?: string } | null>(null);

/** Files (max 3) */
const files = ref<File[]>([]);
function onPick(e: Event) {
  const input = e.target as HTMLInputElement;
  const chosen = Array.from(input.files || []);
  files.value = chosen.slice(0, 3);
  input.value = "";
}
function objectURL(f: File | Blob) {
  return (window.URL || (window as any).webkitURL).createObjectURL(f);
}

/** Forma */
const form = reactive<NewCatchPayload>({
  group_id: undefined as unknown as ID,
  species: undefined,
  species_id: undefined,
  species_name: undefined,
  count: 1,
  total_weight_kg: undefined,
  biggest_single_kg: undefined,
  note: "",
  season_year: new Date().getFullYear(),
  session_id: null,
  event_id: null,
  caught_at: toDatetimeLocal(new Date()),
});

watch(
  me,
  (v) => {
    if (!v)
      return;
    if (!form.group_id && v.groups?.length) {
      form.group_id = v.groups[0].id;
    }
  },
  { immediate: true },
);

watch(sessionId, (id) => {
  form.session_id = id ?? null;
});

/** Upload fotki */
async function uploadPhotosForCatch(catchId: ID, sid: ID | null) {
  if (!files.value.length)
    return;
  for (const f of files.value) {
    const fd = new FormData();
    fd.append("file", f);
    try {
      await $api.post(`/v1/catches/${catchId}/photos`, fd, {
        headers: { "Content-Type": "multipart/form-data" },
      });
    }
    catch (e: any) {
      error(toErrorMessage(e?.message));
      if (sid) {
        await $api.post(`/v1/sessions/${sid}/photos`, fd, {
          headers: { "Content-Type": "multipart/form-data" },
        });
      }
    }
  }
}

/** Submit */
const saving = ref(false);

async function onSubmit() {
  if (!form.group_id)
    return info("Izaberi grupu");
  if (!sessionId.value)
    return info("Izaberi ili kreiraj sesiju");

  // species: id -> label -> name
  if (pickedSpecies.value?.id) {
    form.species_id = pickedSpecies.value.id;
    form.species = undefined;
    form.species_name = undefined;
  }
  else if (pickedSpecies.value?.label) {
    form.species = pickedSpecies.value.label;
    form.species_id = undefined;
    form.species_name = undefined;
  }

  saving.value = true;
  try {
    const payload: NewCatchPayload = {
      ...form,
      session_id: sessionId.value,
      caught_at: datetimeLocalToISO(form.caught_at),
    };

    const res = await $api.post("/v1/catches", payload);
    const created = res.data;

    await uploadPhotosForCatch(created.id, sessionId.value);
    await router.push(`/catches/${created.id}`);
    success("Ulov uspešno kreiran. Dodajte fotografije (max 3) i kliknite na ulov za njega.");
  }
  catch (e: any) {
    console.error(e);
    error(toErrorMessage(e?.response?.data?.message) || "Greška pri kreiranju ulova");
  }
  finally {
    saving.value = false;
  }
}
</script>

<template>
  <div class="container mx-auto p-4 space-y-4">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold">
        Novi ulov
      </h1>
      <NuxtLink class="btn btn-ghost btn-sm" to="/catches">
        ← Nazad
      </NuxtLink>
    </div>

    <div class="card bg-base-100 shadow">
      <div class="card-body grid md:grid-cols-2 gap-6">
        <!-- Levo -->
        <div class="space-y-4">
          <div>
            <label class="label">Grupa</label>
            <select v-model.number="form.group_id" class="select select-bordered w-full">
              <option
                v-for="g in me?.groups || []"
                :key="g.id"
                :value="g.id"
              >
                {{ g.name }} ({{ g.season_year || '—' }})
              </option>
            </select>
          </div>

          <!-- SessionQuickAdd je jedini izvor istine za kreiranje/odabir sesije -->
          <SessionQuickAdd v-model:session-id="sessionId" :group-id="form.group_id" />

          <div>
            <label class="label">Vrsta</label>
            <div class="join w-full">
              <input
                v-model="q"
                class="input input-bordered join-item w-full"
                placeholder="Pretraži vrstu…"
                type="search"
              >
              <button :disabled="speciesLoading" class="btn join-item">
                Traži
              </button>
            </div>
            <div class="mt-2 max-h-52 overflow-auto border border-base-300 rounded-lg">
              <button
                v-for="s in items"
                :key="s.id ?? s.slug ?? s.key"
                class="btn btn-ghost btn-sm w-full justify-start"
                @click="pickedSpecies = { id: s.id, label: s.name_sr ?? s.label }"
              >
                {{ s.name_sr ?? s.label }}
              </button>
              <div v-if="!items.length" class="p-3 opacity-70">
                Nema rezultata…
              </div>
            </div>
            <div v-if="pickedSpecies" class="mt-1 text-sm opacity-80">
              Izabrano:
              <span class="font-medium">{{ pickedSpecies.label || `#${pickedSpecies.id}` }}</span>
              <button class="btn btn-xs btn-ghost" @click="pickedSpecies = null">
                x
              </button>
            </div>
          </div>

          <div class="grid grid-cols-3 gap-3">
            <div>
              <label class="label">Kom</label>
              <input
                v-model.number="form.count"
                class="input input-bordered w-full"
                min="1"
                type="number"
              >
            </div>
            <div>
              <label class="label">Težina (kg)</label>
              <input
                v-model.number="form.total_weight_kg"
                class="input input-bordered w-full"
                step="0.001"
                type="number"
              >
            </div>
            <div>
              <label class="label">Najveća (kg)</label>
              <input
                v-model.number="form.biggest_single_kg"
                class="input input-bordered w-full"
                step="0.001"
                type="number"
              >
            </div>
          </div>

          <div>
            <label class="label">Napomena</label>
            <textarea
              v-model="form.note"
              class="textarea textarea-bordered w-full"
              rows="3"
            />
          </div>

          <div>
            <label class="label">Vreme ulova</label>
            <input
              v-model="form.caught_at"
              class="input input-bordered w-full"
              type="datetime-local"
            >
          </div>

          <div>
            <label class="label">Fotografije (max 3)</label>
            <input
              accept="image/*"
              class="file-input file-input-bordered w-full"
              multiple
              type="file"
              @change="onPick"
            >
            <div class="mt-3 flex gap-2">
              <img
                v-for="(f, i) in files"
                :key="i"
                :src="objectURL(f)"
                class="h-20 w-28 rounded-lg object-cover border border-base-300"
                alt=""
              >
            </div>
          </div>

          <div class="pt-4">
            <button
              :class="{ loading: saving }"
              class="btn btn-primary"
              @click="onSubmit"
            >
              Sačuvaj ulov
            </button>
          </div>
        </div>

        <!-- Desno: Lokacija sesije (uvek prisutna; autosave kada sessionId postoji) -->
        <div class="h-full">
          <SessionLocationCard
            v-model="sessionCoords"
            :session-id="sessionId || undefined"
            :editable="true"
            :auto-save="true"
            title="Lokacija sesije"
          />
        </div>
      </div>
    </div>
  </div>
</template>
