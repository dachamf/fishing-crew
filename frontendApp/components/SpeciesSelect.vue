<script setup lang="ts">
import { useSpeciesSearch } from '~/composables/useSpecies'
defineProps<{ modelValue: number|null }>()
const emit = defineEmits<{(e:'update:modelValue', v:number|null):void}>()
const { q, items, loading } = useSpeciesSearch()
const select = (id:number|null) => emit('update:modelValue', id)
</script>

<template>
  <div class="form-control">
    <label class="label"><span class="label-text">Vrsta</span></label>
    <input v-model="q" type="text" placeholder="Pretraži vrstu…" class="input input-bordered" />
    <div v-if="!loading && items.length" class="mt-2 max-h-52 overflow-auto rounded-lg border bg-base-100">
      <button
        v-for="s in items" :key="s.id"
        type="button" class="w-full text-left px-3 py-2 hover:bg-base-200"
        @click="select(s.id)"
      >
        {{ s.name_sr }} <span v-if="s.name_latin" class="opacity-60">({{ s.name_latin }})</span>
      </button>
    </div>
    <div class="mt-2" v-else-if="loading">Učitavanje…</div>
  </div>
</template>
