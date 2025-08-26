<script setup lang="ts">
const emit = defineEmits<{ (e: "select", id: number | null): void }>();
const { q, items, loading } = useSpeciesSearch();
function select(id: number | null) {
  emit("select", id);
}
</script>

<template>
  <div class="form-control">
    <label class="label"><span class="label-text">Vrsta</span></label>
    <input
      v-model="q"
      type="text"
      placeholder="Pretraži vrstu…"
      class="input input-bordered"
    >
    <div v-if="!loading && items.length" class="mt-2 max-h-52 overflow-auto rounded-lg border bg-base-100">
      <ul>
        <li v-for="s in items" :key="s.id ?? s.slug ?? s.key">
          <button
            class="btn btn-ghost btn-sm w-full justify-start"
            @click="select(s.id ?? null)"
          >
            {{ s.name_sr ?? s.label }}
          </button>
        </li>
      </ul>
    </div>
    <div v-else-if="loading" class="mt-2">
      Učitavanje…
    </div>
  </div>
</template>
