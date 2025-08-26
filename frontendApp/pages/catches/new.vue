<script setup lang="ts">
import type {NewCatchPayload, Me, ID} from '~/types/api'
import { datetimeLocalToISO, toDatetimeLocal } from '~/utils/datetime'


defineOptions({name: 'CatchCreatePage'})

const {$api} = useNuxtApp() as any
const router = useRouter()

/** Me + default group */
const {data: me} = useAsyncData('me:new', async () => (await $api.get('/v1/me')).data, {server: false, immediate: true})

/** Session picker (otvorena ili nova) */
const { openFirst } = useMySessions()
const pickedSessionId = ref<ID | null>(openFirst.value?.id ?? null)
watch(openFirst, (v) => {
  if (!pickedSessionId.value && v?.id) pickedSessionId.value = v.id
})

/** Species */
const {q, items, loading: speciesLoading} = useSpeciesSearch()
const pickedSpecies = ref<{ id?: number; label?: string } | null>(null)

/** Files (max 3) */
const files = ref<File[]>([])

function onPick(e: Event) {
  const input = e.target as HTMLInputElement
  const chosen = Array.from(input.files || [])
  files.value = chosen.slice(0, 3)
  input.value = '' // reset
}

const objectURL = (f: File | Blob) =>
  (window.URL || (window as any).webkitURL).createObjectURL(f)

/** Form */
const form = reactive<NewCatchPayload>({
  group_id: undefined as unknown as ID, // postavi dole
  species: undefined,
  species_id: undefined,
  species_name: undefined,
  count: 1,
  total_weight_kg: undefined,
  biggest_single_kg: undefined,
  note: '',
  season_year: new Date().getFullYear(),
  session_id: null,
  event_id: null,
  caught_at: toDatetimeLocal(new Date())
})

watch(me, (v) => {
  if (!v) return
  if (!form.group_id && v.groups?.length) {
    form.group_id = v.groups[0].id
  }
}, {immediate: true})

watch(pickedSessionId, (id) => {
  form.session_id = id ?? null
})


/** Submit */
const saving = ref(false)

async function uploadPhotosForCatch(catchId: ID, sessionId: ID | null) {
  if (!files.value.length) return
  // pokušaj /catches/:id/photos
  for (const f of files.value) {
    const fd = new FormData()
    fd.append('file', f)
    try {
      await $api.post(`/v1/catches/${catchId}/photos`, fd, {
        headers: {'Content-Type': 'multipart/form-data'}
      })
    } catch (e: any) {
      // fallback: /sessions/:id/photos (ako BE tako radi i dalje)
      if (sessionId) {
        await $api.post(`/v1/sessions/${sessionId}/photos`, fd, {
          headers: {'Content-Type': 'multipart/form-data'}
        })
      } else {
        // nema sesije → preskoči
      }
    }
  }
}

async function onSubmit() {
  if (!form.group_id) return alert('Izaberi grupu')
  if (!pickedSessionId.value) return alert('Izaberi ili kreiraj sesiju')

  // species prioritet: id -> species string -> species_name
  if (pickedSpecies.value?.id) {
    form.species_id = pickedSpecies.value.id
    form.species = undefined
    form.species_name = undefined
  } else if (pickedSpecies.value?.label) {
    form.species = pickedSpecies.value.label
    form.species_id = undefined
    form.species_name = undefined
  }

  saving.value = true
  try {
    const payload: NewCatchPayload = {
      ...form,
      session_id: pickedSessionId.value,
      caught_at: datetimeLocalToISO(form.caught_at)
    }
    const res = await $api.post('/v1/catches', payload)
    const created = res.data
    await uploadPhotosForCatch(created.id, pickedSessionId.value)
    router.push(`/catches/${created.id}`)
  } catch (e: any) {
    console.error(e)
    alert(e?.response?.data?.message || 'Greška pri kreiranju ulova')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="container mx-auto p-4 space-y-4">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold">Novi ulov</h1>
      <NuxtLink to="/catches" class="btn btn-ghost btn-sm">← Nazad</NuxtLink>
    </div>

    <div class="card bg-base-100 shadow">
      <div class="card-body grid md:grid-cols-2 gap-6">

        <!-- Leva kolona -->
        <div class="space-y-4">
          <div>
            <label class="label">Grupa</label>
            <select v-model.number="form.group_id" class="select select-bordered w-full">
              <option v-for="g in me?.groups || []" :key="g.id" :value="g.id">
                {{ g.name }} ({{ g.season_year || '—' }})
              </option>
            </select>
          </div>
          <!--   Sessija   -->
          <SessionQuickAdd
            v-model:session-id="pickedSessionId"
            :group-id="form.group_id"
          />

          <div>
            <label class="label">Vrsta</label>
            <div class="join w-full">
              <input
                v-model="q"
                type="search"
                placeholder="Pretraži vrstu…"
                class="input input-bordered join-item w-full"
              />
              <button class="btn join-item" :disabled="speciesLoading">Traži</button>
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
              <div v-if="!items.length" class="p-3 opacity-70">Nema rezultata…</div>
            </div>
            <div v-if="pickedSpecies" class="mt-1 text-sm opacity-80">
              Izabrano: <span class="font-medium">{{ pickedSpecies.label || ('#' + pickedSpecies.id) }}</span>
              <button class="btn btn-xs btn-ghost" @click="pickedSpecies=null">x</button>
            </div>
          </div>

          <div class="grid grid-cols-3 gap-3">
            <div>
              <label class="label">Kom</label>
              <input v-model.number="form.count" type="number" min="1" class="input input-bordered w-full"/>
            </div>
            <div>
              <label class="label">Težina (kg)</label>
              <input
v-model.number="form.total_weight_kg" type="number" step="0.001"
                     class="input input-bordered w-full"/>
            </div>
            <div>
              <label class="label">Najveća (kg)</label>
              <input
v-model.number="form.biggest_single_kg" type="number" step="0.001"
                     class="input input-bordered w-full"/>
            </div>
          </div>

          <div>
            <label class="label">Napomena</label>
            <textarea v-model="form.note" rows="3" class="textarea textarea-bordered w-full"/>
          </div>
        </div>

        <!-- Desna kolona -->
        <div class="space-y-4">
          <div>
            <label class="label">Vreme ulova</label>
            <input v-model="form.caught_at" type="datetime-local" class="input input-bordered w-full"/>
          </div>

          <div>
            <label class="label">Fotografije (max 3)</label>
            <input
              type="file"
              accept="image/*"
              multiple class="file-input file-input-bordered w-full"
              @change="onPick"
            />
            <div class="mt-3 flex gap-2">
              <img 
                v-for="(f,i) in files" 
                :key="i" 
                :src="objectURL(f)"
                class="h-20 w-28 rounded-lg object-cover border border-base-300"/>
            </div>
          </div>

          <div class="pt-4">
            <button class="btn btn-primary" :class="{loading: saving}" @click="onSubmit">Sačuvaj ulov</button>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>
