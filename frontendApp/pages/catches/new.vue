<script setup lang="ts">
definePageMeta({ name: 'CatchNewPage' })

const { $api } = useNuxtApp() as any
const router = useRouter()
const { success, error: toastError } = useToast()

// ---------- State ----------
type Species = { id: number; name: string }
type Session = { id: number; started_at?: string | null; ended_at?: string | null; title?: string | null }

const loading = ref(false)
const species = ref<Species[]>([])
const openSessions = ref<Session[]>([])
const canUseSessions = ref(true) // ako BE za sesije ne postoji/vrati grešku, sakrićemo deo UI-ja

// forma
const form = reactive({
  species_id: null as number | null,
  count: 1 as number,
  total_weight_kg: '' as string | number,
  biggest_single_kg: '' as string | number,
  caught_at_local: '' as string, // input type="datetime-local"
  note: '' as string,
  group_id: 1 as number,

  // sesije
  use_session: true,            // “da li vezujemo za sesiju”
  session_id: null as number | null,
  create_new_session: false,    // inline kreiranje nove sesije
  new_session: {
    title: '' as string,
    started_at_local: '' as string, // datetime-local
  },
})

// slike (max 3)
const files = ref<File[]>([])
const previews = ref<string[]>([])

// ---------- Init ----------
onMounted(async () => {
  try {
    // vrste (moraš imati /v1/species koji vraća {data:[{id,name},...]})
    const s = await $api.get('/v1/species')
    species.value = s.data?.data ?? s.data ?? []

    // aktivne sesije (prilagodi endpoint/parametre po svojoj šemi)
    try {
      const ses = await $api.get('/v1/sessions', { params: { mine: 1, status: 'open' } })
      openSessions.value = ses.data?.data ?? ses.data ?? []
      if (openSessions.value.length) form.session_id = openSessions.value[0].id
    } catch {
      canUseSessions.value = false
      form.use_session = false
    }

    // default vremena
    const now = new Date()
    form.caught_at_local = toLocalInputValue(now)
    form.new_session.started_at_local = toLocalInputValue(now)
  } catch (e: any) {
    toastError('Neuspešno učitavanje podataka.')
  }
})

// ---------- Helpers ----------
function toLocalInputValue(d: Date) {
  // vrati "YYYY-MM-DDTHH:mm" za <input type="datetime-local">
  const pad = (n: number) => String(n).padStart(2, '0')
  const yyyy = d.getFullYear()
  const MM = pad(d.getMonth() + 1)
  const dd = pad(d.getDate())
  const hh = pad(d.getHours())
  const mm = pad(d.getMinutes())
  return `${yyyy}-${MM}-${dd}T${hh}:${mm}`
}
function toIsoZ(localVal: string) {
  if (!localVal) return null
  // input "YYYY-MM-DDTHH:mm" -> local time -> ISO (Z)
  return new Date(localVal).toISOString()
}

function onFilesSelected(evt: Event) {
  const input = evt.target as HTMLInputElement
  const chosen = Array.from(input.files || [])
  const freeSlots = Math.max(0, 3 - files.value.length)
  const add = chosen.slice(0, freeSlots)
  files.value.push(...add)

  // napravimo preview URL-ove
  add.forEach(f => previews.value.push(URL.createObjectURL(f)))

  // reset input da može isti fajl ponovo
  input.value = ''
}
function removeFile(idx: number) {
  files.value.splice(idx, 1)
  const url = previews.value.splice(idx, 1)[0]
  if (url) URL.revokeObjectURL(url)
}
onUnmounted(() => {
  previews.value.forEach(u => URL.revokeObjectURL(u))
})

// sitna pomoć: auto-predlog total_weight (ako nemaš vrednost)
watch([() => form.count, () => form.biggest_single_kg], () => {
  const cnt = Number(form.count || 0)
  const big = Number(form.biggest_single_kg || 0)
  if (!form.total_weight_kg && cnt > 0 && big > 0) {
    form.total_weight_kg = (cnt * big * 0.6).toFixed(3) // skroz “soft” sugestija
  }
})

// validacija
const errors = reactive<Record<string, string>>({})
const canSubmit = computed(() => {
  return !!form.species_id &&
    Number(form.count) >= 1 &&
    !!form.caught_at_local &&
    (!form.use_session || !form.create_new_session || !!form.new_session.started_at_local)
})

function validate(): boolean {
  Object.keys(errors).forEach(k => delete errors[k])

  if (!form.species_id) errors.species_id = 'Obavezno'
  if (!form.count || Number(form.count) < 1) errors.count = 'Min 1'
  if (!form.caught_at_local) errors.caught_at_local = 'Obavezno'

  if (form.use_session && form.create_new_session) {
    if (!form.new_session.started_at_local) errors['new_session.started_at_local'] = 'Obavezno'
  }
  return Object.keys(errors).length === 0
}

// ---------- Submit ----------
async function submit() {
  if (!validate() || !canSubmit.value) return
  loading.value = true
  try {
    let sessionId: number | null = form.use_session ? (form.session_id ?? null) : null

    // Ako treba da kreiramo novu sesiju, uradimo to odmah
    if (form.use_session && form.create_new_session) {
      const payload = {
        title: form.new_session.title || null,
        started_at: toIsoZ(form.new_session.started_at_local),
      }
      // prilagodi URL: očekivano POST /v1/sessions
      const res = await $api.post('/v1/sessions', payload)
      const created = res.data?.data ?? res.data
      sessionId = created?.id ?? null
    }

    // FormData (da pošaljemo i slike)
    const fd = new FormData()
    fd.append('species_id', String(form.species_id))
    fd.append('count', String(form.count))
    if (form.total_weight_kg) fd.append('total_weight_kg', String(form.total_weight_kg))
    if (form.biggest_single_kg) fd.append('biggest_single_kg', String(form.biggest_single_kg))
    fd.append('caught_at', toIsoZ(form.caught_at_local) || '')
    if (form.note) fd.append('note', form.note)
    if (sessionId) fd.append('fishing_session_id', String(sessionId))
    if (form.group_id) fd.append('group_id', String(form.group_id))

    files.value.forEach((f, i) => fd.append('images[]', f, f.name))

    // prilagodi URL: očekivano POST /v1/catches (prima i slike)
    const catchRes = await $api.post('/v1/catches', fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    success('Ulov sačuvan.')
    // idi na detalj (ili na listu)
    const created = catchRes.data?.data ?? catchRes.data
    if (created?.id) {
      router.push(`/catches/${created.id}`)
    } else {
      router.push('/catches')
    }
  } catch (e: any) {
    toastError('Greška pri čuvanju ulova.')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="container mx-auto p-4">
    <div class="mb-4 flex items-center justify-between gap-3">
      <h1 class="text-2xl font-semibold">Novi ulov</h1>
      <NuxtLink to="/catches" class="btn btn-ghost btn-sm">← Nazad</NuxtLink>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
      <!-- Forma -->
      <div class="lg:col-span-2">
        <div class="card bg-base-100 shadow">
          <div class="card-body">
            <!-- Vrsta -->
            <label class="form-control w-full">
              <div class="label"><span class="label-text">Vrsta ribe</span></div>
              <select v-model="form.species_id" class="select select-bordered">
                <option :value="null" disabled>Izaberi vrstu…</option>
                <option v-for="s in species" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
              <div v-if="errors.species_id" class="text-error text-xs mt-1">{{ errors.species_id }}</div>
            </label>

            <!-- Count / Biggest / Total -->
            <div class="grid md:grid-cols-3 gap-3">
              <label class="form-control">
                <div class="label"><span class="label-text">Komada</span></div>
                <input v-model.number="form.count" type="number" min="1" class="input input-bordered" />
                <div v-if="errors.count" class="text-error text-xs mt-1">{{ errors.count }}</div>
              </label>

              <label class="form-control">
                <div class="label"><span class="label-text">Najveća (kg)</span></div>
                <input v-model="form.biggest_single_kg" type="number" step="0.001" min="0" class="input input-bordered" />
              </label>

              <label class="form-control">
                <div class="label"><span class="label-text">Ukupno (kg)</span></div>
                <input v-model="form.total_weight_kg" type="number" step="0.001" min="0" class="input input-bordered" />
              </label>
            </div>

            <!-- Vreme -->
            <label class="form-control mt-2">
              <div class="label"><span class="label-text">Vreme ulova</span></div>
              <input v-model="form.caught_at_local" type="datetime-local" class="input input-bordered" />
              <div v-if="errors.caught_at_local" class="text-error text-xs mt-1">{{ errors.caught_at_local }}</div>
            </label>

            <!-- Sesija -->
            <div class="mt-4">
              <div class="flex items-center gap-2">
                <input v-model="form.use_session" type="checkbox" class="toggle" :disabled="!canUseSessions" />
                <span class="font-medium">Vezati za sesiju</span>
                <span v-if="!canUseSessions" class="text-xs opacity-70">(trenutno nije dostupno)</span>
              </div>

              <div v-if="canUseSessions && form.use_session" class="mt-3 grid gap-3 md:grid-cols-2">
                <label class="form-control">
                  <div class="label"><span class="label-text">Aktivne sesije</span></div>
                  <select v-model="form.session_id" class="select select-bordered" :disabled="form.create_new_session || !openSessions.length">
                    <option v-if="!openSessions.length" :value="null" disabled>Nema aktivnih sesija</option>
                    <option v-for="s in openSessions" :key="s.id" :value="s.id">
                      #{{ s.id }} · {{ s.title || 'Bez lokacije' }} · start:
                      <ClientOnly>
                        {{ new Date(s.started_at || '').toLocaleString('sr-RS') }}
                        <template #fallback>—</template>
                      </ClientOnly>
                    </option>
                  </select>
                </label>

                <label class="form-control">
                  <div class="label"><span class="label-text">Kreiraj novu sesiju</span></div>
                  <input v-model="form.create_new_session" type="checkbox" class="toggle" :disabled="!canUseSessions" />
                </label>

                <div v-if="form.create_new_session" class="md:col-span-2 grid gap-3 md:grid-cols-2">
                  <label class="form-control">
                    <div class="label"><span class="label-text">Lokacija (opciono)</span></div>
                    <input v-model="form.new_session.title" type="text" class="input input-bordered" placeholder="npr. Ada, Ušće…" />
                  </label>
                  <label class="form-control">
                    <div class="label"><span class="label-text">Početak sesije</span></div>
                    <input v-model="form.new_session.started_at_local" type="datetime-local" class="input input-bordered" />
                    <div v-if="errors['new_session.started_at_local']" class="text-error text-xs mt-1">{{ errors['new_session.started_at_local'] }}</div>
                  </label>
                </div>
              </div>
            </div>

            <!-- Slike -->
            <div class="mt-4">
              <div class="label"><span class="label-text">Fotografije (max 3)</span></div>

              <div class="flex flex-wrap gap-3">
                <label class="btn btn-outline btn-sm">
                  + Dodaj
                  <input type="file" accept="image/*" class="hidden" multiple @change="onFilesSelected" />
                </label>

                <div v-for="(src, i) in previews" :key="i" class="relative">
                  <img :src="src" class="w-24 h-24 object-cover rounded-lg border" />
                  <button type="button" class="btn btn-xs btn-error absolute -top-2 -right-2" @click="removeFile(i)">✕</button>
                </div>
              </div>
              <div class="text-xs opacity-70 mt-1">Podržane slike; fajlovi se šalju prilikom snimanja.</div>
            </div>

            <!-- Napomena -->
            <label class="form-control mt-4">
              <div class="label"><span class="label-text">Napomena (opciono)</span></div>
              <textarea v-model="form.note" class="textarea textarea-bordered" rows="3" placeholder="Opis, mamac, uslovi…"></textarea>
            </label>

            <div class="mt-6 flex items-center justify-end gap-2">
              <NuxtLink to="/catches" class="btn btn-ghost">Otkaži</NuxtLink>
              <button class="btn btn-primary" :disabled="loading || !canSubmit" @click="submit">
                <span v-if="loading" class="loading loading-spinner"></span>
                Sačuvaj ulov
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Tips / Preview kartica (opciono) -->
      <div class="lg:col-span-1 space-y-4">
        <div class="card bg-base-100 shadow">
          <div class="card-body">
            <h2 class="card-title">Saveti</h2>
            <ul class="list-disc ml-4 text-sm opacity-80 space-y-1">
              <li>Vrste su uniformne – biraj iz liste.</li>
              <li>Fotografije su opcione (max 3).</li>
              <li>Možeš vezati ulov za aktivnu sesiju ili otvoriti novu.</li>
            </ul>
          </div>
        </div>

        <div class="card bg-base-100 shadow">
          <div class="card-body">
            <h2 class="card-title">Kratki pregled</h2>
            <div class="text-sm">
              <div>Komada: <b>{{ form.count || 0 }}</b></div>
              <div>Najveća: <b>{{ Number(form.biggest_single_kg || 0).toFixed(3) }}</b> kg</div>
              <div>Ukupno: <b>{{ Number(form.total_weight_kg || 0).toFixed(3) }}</b> kg</div>
              <div class="mt-1">Sesija: <b>{{ form.use_session ? (form.create_new_session ? 'nova' : (form.session_id ? `#${form.session_id}` : '—')) : 'bez sesije' }}</b></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
