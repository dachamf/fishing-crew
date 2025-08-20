<script setup lang="ts">
import { computed } from 'vue'
import { SwitchRoot, SwitchThumb } from 'radix-vue'

// koristi tvoj postojeći composable
const { theme, setTheme } = useTheme()

const isDark = computed({
  get: () => theme.value === 'dark',
  set: (v: boolean) => setTheme(v ? 'dark' : 'light'),
})
</script>

<template>
  <SwitchRoot
    v-model:checked="isDark"
    :aria-label="`Switch to ${isDark ? 'light' : 'dark'} theme`"
    class="inline-flex h-6 w-11 items-center rounded-full transition-colors
           bg-base-300 data-[state=checked]:bg-primary"
  >
    <SwitchThumb
      class="block h-5 w-5 translate-x-0.5 rounded-full bg-base-100 transition-transform
             data-[state=checked]:translate-x-5"
    />
  </SwitchRoot>
</template>
