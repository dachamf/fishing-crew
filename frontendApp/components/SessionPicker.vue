<script setup lang="ts">
import type { LngLatLike } from 'maplibre-gl'
const props = defineProps<{ groupId: number }>()
const emit = defineEmits<{(e:'picked', v:{ id:number|null }):void}>()

const { open, recent, startNew, closeSession, loading } = useMySessions()
const picking = ref<'open'|'new'|'recent'|null>('open')
const pickedId = computed(() => open.value?.id ?? null)

function pickOpen() { if (open.value) emit('picked', { id: open.value.id }) }
async function newSession() {
  const s = await startNew(props.groupId, { start_at: new Date().toISOString() })
  emit('picked', { id: s.id })
}
</script>

<template>
  <div class="card bg-base-100 border">
    <div class="card-body gap-3">
      <h3 class="card-title text-base">Sesija</h3>

      <div v-if="loading">Učitavanje…</div>

      <div v-else class="flex flex-col gap-2">
        <button v-if="open" class="btn btn-sm btn-primary" @click="pickOpen">
          Nastavi otvorenu sesiju (ID: {{ open.id }})
        </button>

        <button class="btn btn-sm" @click="newSession">Započni novu sesiju</button>

        <details class="mt-2">
          <summary class="cursor-pointer opacity-70">Nedavno zatvorene</summary>
          <ul class="mt-2 space-y-1">
            <li v-for="s in recent" :key="s.id" class="text-sm opacity-80">
              #{{ s.id }} • {{ s.start_at }} – {{ s.end_at }}
            </li>
          </ul>
        </details>
      </div>
    </div>
  </div>
</template>
