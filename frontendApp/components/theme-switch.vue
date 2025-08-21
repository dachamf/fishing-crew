<script setup lang="ts">
const { theme, toggle } = useTheme()
const swapActive = computed(() => theme.value === 'dark')
</script>

<template>
  <button
class="btn btn-ghost btn-circle swap swap-rotate"
          :class="{ 'swap-active': swapActive }"
          aria-label="Toggle theme"
          @click="toggle">
    <!-- sun (light) -->
    <svg class="swap-off w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
      <circle cx="12" cy="12" r="4" stroke-width="2"/>
      <path stroke-width="2" d="M12 2v2m0 16v2m10-10h-2M4 12H2m16.95 6.95-1.41-1.41M6.46 6.46 5.05 5.05m12.02-0 1.41 1.41M6.46 17.54 5.05 18.95"/>
    </svg>
    <!-- moon (dark) -->
    <svg class="swap-on w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
      <path d="M21 12.79A9 9 0 1 1 11.21 3a7 7 0 0 0 9.79 9.79z"/>
    </svg>
  </button>
</template>
