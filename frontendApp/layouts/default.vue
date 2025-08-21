<script setup lang="ts">
import UiToaster from "~/components/ui/ui-toaster.vue";
</script>
<template>
  <div>
    <AppMenu />
    <main class="min-h-[calc(100vh-4rem)]">
      <slot />
    </main>
    <UiToaster />
  </div>
</template>
