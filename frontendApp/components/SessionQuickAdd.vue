<script setup lang="ts">
const props = defineProps<{ sessionId: number }>()
const { stackCatch } = useSessions()

const form = reactive({
  species: '',
  count: 1,
  weight_kg: null as number|null,
  note: '',
})

const loading = ref(false)

async function addCatch() {
  if (!form.species) return
  loading.value = true
  try {
    await stackCatch(props.sessionId, {
      species: form.species,
      count: form.count || 1,
      weight_kg: form.weight_kg,
      note: form.note || undefined,
    })
    form.count = 1
    form.weight_kg = null
    form.note = ''
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="grid gap-2 md:grid-cols-4">
    <input v-model="form.species" class="input input-bordered" placeholder="Vrsta (npr. štuka)" />
    <input v-model.number="form.count" type="number" min="1" class="input input-bordered" placeholder="Kom" />
    <input v-model.number="form.weight_kg" type="number" step="0.001" class="input input-bordered" placeholder="Težina (kg) — opcionalno" />
    <button :disabled="!form.species || loading" class="btn btn-primary" @click="addCatch">
      + Dodaj
    </button>
    <textarea v-model="form.note" class="textarea textarea-bordered md:col-span-4" rows="2" placeholder="Napomena (opciono)" />
  </div>
</template>
