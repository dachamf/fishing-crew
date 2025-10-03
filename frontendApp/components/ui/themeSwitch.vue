<script setup lang="ts">
const { resolved, setLight, setDark, toggle } = useTheme();

const isDark = computed({
  get: () => resolved.value === "dark",
  set: (val: boolean) => (val ? setDark() : setLight()),
});
</script>

<template>
  <div class="flex items-center gap-2">
    <ClientOnly>
      <!-- Desktop: icon button -->
      <button
        class="btn btn-ghost btn-circle hidden sm:inline-flex"
        aria-label="Promeni temu"
        title="Promeni temu"
        @click="toggle()"
      >
        <!-- Sun (light) -->
        <svg
          v-if="resolved === 'light'"
          class="w-5 h-5"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
        >
          <circle
            cx="12"
            cy="12"
            r="4"
            stroke-width="2"
          />
          <path
            stroke-width="2"
            d="M12 2v2m0 16v2m10-10h-2M4 12H2m16.95 6.95-1.41-1.41M6.46 6.46 5.05 5.05m12.02-0 1.41 1.41M6.46 17.54 5.05 18.95"
          />
        </svg>
        <!-- Moon (dark) -->
        <svg
          v-else
          class="w-5 h-5"
          viewBox="0 0 24 24"
          fill="currentColor"
        >
          <path d="M21 12.79A9 9 0 1 1 11.21 3a7 7 0 0 0 9.79 9.79z" />
        </svg>
      </button>

      <!-- Mobile: switch sa suncem/Me(s)ec -->
      <label class="sm:hidden inline-flex items-center gap-2 cursor-pointer">
        <span class="text-sm">🌞</span>
        <input
          type="checkbox"
          class="toggle"
          :checked="isDark"
          aria-label="Toggle theme"
          @change="isDark = ($event.target as HTMLInputElement).checked"
        >
        <span class="text-sm">🌙</span>
      </label>
    </ClientOnly>
  </div>
</template>
