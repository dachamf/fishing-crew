<script setup lang="ts">
const { mode, resolved, setTheme, toggle } = useTheme();
const open = ref(false);
</script>

<template>
  <div class="dropdown dropdown-end">
    <button
      class="btn btn-ghost btn-circle"
      aria-label="Theme menu"
      @click="open = !open"
    >
      <!-- ikona prema resolved temi -->
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
        <path stroke-width="2" d="M12 2v2m0 16v2m10-10h-2M4 12H2m16.95 6.95-1.41-1.41M6.46 6.46 5.05 5.05m12.02-0 1.41 1.41M6.46 17.54 5.05 18.95" />
      </svg>
      <svg
        v-else-if="resolved === 'dark'"
        class="w-5 h-5"
        viewBox="0 0 24 24"
        fill="currentColor"
      >
        <path d="M21 12.79A9 9 0 1 1 11.21 3a7 7 0 0 0 9.79 9.79z" />
      </svg>
      <!-- auto -->
      <svg
        v-else
        class="w-5 h-5"
        viewBox="0 0 24 24"
        fill="none"
      >
        <path
          d="M12 2a5 5 0 0 1 0 10A5 5 0 0 0 12 2Z"
          stroke="currentColor"
          stroke-width="2"
        />
        <path
          d="M4 12a8 8 0 1 0 16 0"
          stroke="currentColor"
          stroke-width="2"
          fill="none"
        />
      </svg>
    </button>
    <ul v-show="open" class="menu menu-sm dropdown-content bg-base-100 rounded-box z-50 mt-3 w-40 p-2 shadow">
      <li>
        <button :class="{ active: mode === 'light' }" @click="setTheme('light')">
          Light
        </button>
      </li>
      <li>
        <button :class="{ active: mode === 'dark' }" @click="setTheme('dark')">
          Dark
        </button>
      </li>
      <li>
        <button :class="{ active: mode === 'system' }" @click="setTheme('system')">
          System
        </button>
      </li>
      <li class="mt-1">
        <button class="btn btn-ghost btn-xs" @click="toggle">
          Toggle
        </button>
      </li>
    </ul>
  </div>
</template>

<style scoped>
@reference "tailwindcss";
.active {
  color: oklch(var(--p));
}
</style>
