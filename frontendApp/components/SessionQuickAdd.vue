<script setup lang="ts">
import type { ID } from '~/types/api'
import { toDatetimeLocal, datetimeLocalToISO } from '~/utils/datetime'

const props = defineProps<{
  groupId?: ID
  sessionId?: ID | null
}>()

const emit = defineEmits<{
  (e: 'update:sessionId', v: ID | null): void
  (e: 'created', s: any): void
}>()

// state
const { open, startNew, loading } = useMySessions()
const localSessionId = ref<ID | null>(props.sessionId ?? null)
watch(() => props.sessionId, v => localSessionId.value = v ?? null)
watch(localSessionId, v => emit('update:sessionId', v ?? null))

// create mode
const newMode = ref(false)
const newTitle = ref<string>('')
const newStartedAt = ref<string>(toDatetimeLocal(new Date()))
const creating = ref(false)

async function createSession() {
  if (!props.groupId) return alert('Izaberi grupu pre kreiranja sesije')
  creating.value = true
  try {
    const s = await startNew({
      group_id: props.groupId,
      title: newTitle.value || undefined,
      started_at: datetimeLocalToISO(newStartedAt.value)
    })
    emit('created', s)
    localSessionId.value = s.id
    // reset i izlazak iz moda
    newTitle.value = ''
    newStartedAt.value = toDatetimeLocal(new Date())
    newMode.value = false
  } catch (e: any) {
    console.error(e)
    alert(e?.response?.data?.message || 'Greška pri kreiranju sesije')
  } finally {
    creating.value = false
  }
}
</script>

<template>
  <div class="space-y-2">
    <label class="label">Sesija</label>

    <!-- Izbor postojeće -->
    <div v-if="!newMode" class="flex items-center gap-2">
      <select v-model.number="localSessionId" class="select select-bordered w-full" :disabled="loading">
        <option v-if="!open?.length" :value="null" disabled>— Nema otvorenih sesija —</option>
        <option v-for="s in open || []" :key="s.id" :value="s.id">
          #{{ s.id }} · {{ s.title || 'Sesija' }} · {{ new Date(s.started_at || '').toLocaleString('sr-RS') }}
        </option>
      </select>
      <button class="btn btn-outline btn-sm" @click="newMode = true">Nova</button>
    </div>

    <!-- Kreiranje nove (sa imenom) -->
    <div v-else class="rounded-xl border border-base-300 p-3 bg-base-200 space-y-2">
      <div class="grid md:grid-cols-2 gap-2">
        <div>
          <label class="label-text text-sm">Naziv sesije</label>
          <input
v-model.trim="newTitle" type="text" maxlength="100" placeholder="npr. Noćni izlazak"
                 class="input input-bordered w-full" />
        </div>
        <div>
          <label class="label-text text-sm">Početak</label>
          <input v-model="newStartedAt" type="datetime-local" class="input input-bordered w-full" />
        </div>
      </div>
      <div class="flex items-center gap-2 justify-end">
        <button class="btn btn-ghost btn-sm" @click="newMode = false">Otkaži</button>
        <button class="btn btn-primary btn-sm" :class="{ loading: creating }" @click="createSession">
          Sačuvaj sesiju
        </button>
      </div>
    </div>
  </div>
</template>
