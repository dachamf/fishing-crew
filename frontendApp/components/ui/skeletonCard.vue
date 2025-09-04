<script setup lang="ts">
defineProps<{ loading?: boolean }>();
</script>

<template>
  <div class="card bg-base-100 shadow">
    <div v-if="loading" class="card-body space-y-3">
      <div class="skeleton h-5 w-40" />
      <div class="skeleton h-4 w-3/5" />
      <div class="skeleton h-24 w-full" />
    </div>
    <div v-else class="card-body">
      <slot />
    </div>
  </div>
</template>
