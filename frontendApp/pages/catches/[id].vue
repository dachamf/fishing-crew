<script setup lang="ts">
import type { FishingCatch, Me, ID } from '~/types/api'

defineOptions({ name: 'CatchDetailPage' })

const route = useRoute()
const id = Number(route.params.id)
const { $api } = useNuxtApp() as any
const { data: me } = useMe()

const { data, pending, error, refresh } = await useAsyncData<FishingCatch>(
  () => `catch:${id}`,
  async () => {
    const res = await $api.get(`/v1/catches/${id}`, {
      params: { include: 'user,group,session.user,session.group,session.photos,confirmations' }
    })
    const c = res.data as FishingCatch
    // fallback confirmations ako nisu stigle
    if (!Array.isArray(c.confirmations)) {
      try {
        const r2 = await $api.get(`/v1/catches/${id}/confirmations`)
        c.confirmations = r2.data?.data ?? r2.data ?? []
      } catch {}
    }
    return c
  }
)

const speciesText = computed(() =>
  data.value?.species_label || (typeof data.value?.species === 'string' ? data.value?.species : '') || data.value?.species_name || '-'
)

/** Confirmations */
const confirming = ref<'approved' | 'rejected' | null>(null)
const note = ref('')

async function approve() {
  confirming.value = 'approved'
  await sendConfirm('approved')
}
async function reject() {
  confirming.value = 'rejected'
  await sendConfirm('rejected')
}

async function sendConfirm(status: 'approved' | 'rejected') {
  try {
    await $api.post(`/v1/catches/${id}/confirmations`, { status, note: note.value || undefined })
    note.value = ''
    await refresh()
  } catch (e: any) {
    console.error(e)
    alert(e?.response?.data?.message || 'Greška pri potvrdi')
  } finally {
    confirming.value = null
  }
}

/** Request confirmations — minimal UI (lista ID-jeva zarezom) */
const requestIds = ref<string>('')

async function requestConfirmations() {
  const ids = requestIds.value.split(',').map(s => Number(s.trim())).filter(Boolean)
  if (!ids.length) return alert('Unesi bar jedan user ID')
  try {
    await $api.post(`/v1/catches/${id}/request-confirmation`, { user_ids: ids })
    requestIds.value = ''
    await refresh()
  } catch (e: any) {
    console.error(e)
    alert(e?.response?.data?.message || 'Greška pri slanju zahteva')
  }
}
</script>

<template>
  <div class="container mx-auto p-4 space-y-4">
    <div class="breadcrumbs text-sm">
      <ul>
        <li><NuxtLink to="/catches">Sesije</NuxtLink></li>
        <li>Ulov #{{ id }}</li>
      </ul>
    </div>

    <div v-if="pending" class="flex items-center gap-2">
      <span class="loading loading-spinner"></span> Učitavanje…
    </div>
    <div v-else-if="error" class="alert alert-error">Greška pri učitavanju.</div>

    <div v-else class="grid md:grid-cols-3 gap-4">
      <!-- Info -->
      <div class="md:col-span-2 card bg-base-100 shadow">
        <div class="card-body space-y-2">
          <h1 class="text-2xl font-semibold">Ulov — {{ speciesText }}</h1>
          <div class="opacity-75 flex flex-wrap gap-2">
            <span class="badge badge-ghost">Grupa: {{ data?.group?.name || '-' }}</span>
            <span class="badge badge-ghost">Korisnik: {{ data?.user?.display_name || data?.user?.name }}</span>
            <span class="badge badge-ghost">Količina: {{ data?.count }}</span>
            <span class="badge badge-ghost">Težina: {{ Number(data?.total_weight_kg||0).toFixed(3) }} kg</span>
            <span class="badge badge-ghost">Najveća: {{ Number(data?.biggest_single_kg||0).toFixed(3) }} kg</span>
            <span class="badge badge-outline">{{ data?.status }}</span>
          </div>

          <div class="mt-2">
            <div class="font-medium">Napomena</div>
            <div class="opacity-80">{{ data?.note || '—' }}</div>
          </div>

          <div v-if="(data?.session?.photos?.length||0) > 0" class="mt-3 grid grid-cols-3 gap-2">
            <div v-for="(p, idx) in (data?.session?.photos ?? []).slice(0,3)" :key="idx" class="aspect-video rounded-xl overflow-hidden border border-base-300">
              <img :src="p.url" class="w-full h-full object-cover" loading="lazy" />
            </div>
          </div>
        </div>
      </div>

      <!-- Confirmations panel -->
      <div class="card bg-base-100 shadow">
        <div class="card-body space-y-3">
          <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold">Potvrde</h2>
            <span class="badge">{{ (data?.confirmations?.length || 0) }}</span>
          </div>

          <ul class="space-y-2 max-h-64 overflow-auto">
            <li v-for="c in data?.confirmations || []" :key="c.id" class="flex items-start justify-between">
              <div>
                <div class="font-medium">{{ c.status }}</div>
                <div class="text-sm opacity-70">{{ c.note || '—' }}</div>
              </div>
              <div class="text-xs opacity-60">{{ c.created_at ? new Date(c.created_at).toLocaleString('sr-RS') : '' }}</div>
            </li>
          </ul>

          <div class="divider my-2"></div>

          <div class="form-control">
            <label class="label">Traži potvrde (user IDs, zarezom)</label>
            <div class="join">
              <input v-model="requestIds" type="text" class="input input-bordered join-item w-full" placeholder="12,14,16" />
              <button class="btn join-item" @click="requestConfirmations">Pošalji</button>
            </div>
          </div>

          <div class="divider my-2"></div>

          <div class="form-control">
            <label class="label">Moja odluka</label>
            <textarea v-model="note" rows="2" class="textarea textarea-bordered" placeholder="Opciona napomena"></textarea>
            <div class="mt-2 join">
              <button class="btn btn-success join-item" :class="{loading: confirming==='approved'}" @click="approve">Odobri</button>
              <button class="btn btn-error join-item"   :class="{loading: confirming==='rejected'}" @click="reject">Odbij</button>
            </div>
          </div>

        </div>
      </div>
    </div>

  </div>
</template>
