<script setup lang="ts">
defineOptions({ name:'CatchDetailPage' })
const route = useRoute()
const id = computed(() => Number(route.params.id))
const {$api} = useNuxtApp() as any
const auth = useAuth()

const { data, pending, error, refresh } = await useAsyncData(
  () => `catch:${id.value}`,
  async () => (await $api.get(`/v1/catches/${id.value}`)).data?.data ?? (await $api.get(`/v1/catches/${id.value}`)).data,
  { watch: [id] }
)
const item = computed(() => data.value)

const canConfirm = computed(() => {
  // BE treba da vrati nešto tipa: item.value.my_confirmation_status === 'pending'
  return item.value?.my_confirmation_status === 'pending'
})

async function act(status:'approved'|'rejected', note?:string) {
  await $api.post(`/v1/catches/${id.value}/confirmations`, { status, note })
  await refresh()
}
</script>

<template>
  <div class="container mx-auto p-4 max-w-3xl">
    <div v-if="pending">Učitavanje…</div>
    <div v-else-if="error" class="alert alert-error">Greška pri učitavanju.</div>
    <div v-else-if="!item" class="alert">Nije pronađen ulov.</div>

    <div v-else class="card bg-base-100 shadow">
      <div class="card-body space-y-3">
        <div class="flex items-center justify-between">
          <h1 class="card-title">{{ item.species?.name_sr || 'Vrsta' }}</h1>
          <span class="badge" :class="{'badge-success': item.status==='approved', 'badge-warning': item.status==='pending', 'badge-error': item.status==='rejected'}">
            {{ item.status }}
          </span>
        </div>

        <div class="opacity-80 text-sm">
          Komada: {{ item.count }}
          <span v-if="item.total_weight_kg"> • Ukupno: {{ item.total_weight_kg }} kg</span>
          <span v-if="item.biggest_single_kg"> • Najveća: {{ item.biggest_single_kg }} kg</span>
          <div v-if="item.caught_at">Vreme ulova: {{ item.caught_at }}</div>
          <div v-if="item.session_id">Sesija: #{{ item.session_id }}</div>
        </div>

        <p v-if="item.note" class="whitespace-pre-line">{{ item.note }}</p>

        <div v-if="(item.photos||[]).length" class="flex gap-2 flex-wrap">
          <img v-for="(p,i) in item.photos" :key="i" :src="p" class="h-28 rounded-xl object-cover" />
        </div>

        <div v-if="canConfirm" class="mt-3 flex gap-2">
          <button class="btn btn-success btn-sm" @click="act('approved')">Potvrdi</button>
          <button class="btn btn-error btn-sm" @click="act('rejected')">Odbij</button>
        </div>
      </div>
    </div>
  </div>
</template>
