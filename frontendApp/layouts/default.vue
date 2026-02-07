<script setup lang="ts">
import Toaster from "~/components/ui/toaster.vue";
</script>

<template>
  <div>
    <AppMenu />
    <main class="min-h-[calc(100vh-4rem)] bg-base-200 text-base-content">
      <slot />
    </main>
    <AppFooter />
    <Toaster />
  </div>
</template>
