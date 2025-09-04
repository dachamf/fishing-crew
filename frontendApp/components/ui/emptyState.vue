<script setup lang="ts">
defineProps<{
  title?: string;
  desc?: string;
  ctaText?: string;
  to?: string;
  icon?: string;
}>();
</script>

<template>
  <div class="text-center p-6 opacity-80">
    <Icon
      v-if="icon"
      :name="icon"
      size="32"
      class="mb-2"
      aria-hidden="true"
    />
    <div class="font-medium">
      {{ title || 'Nema podataka' }}
    </div>
    <div class="text-sm mb-3">
      {{ desc || 'Pokušaj kasnije ili promeni filter.' }}
    </div>
    <NuxtLink
      v-if="to"
      :to="to"
      class="btn btn-primary btn-sm"
      :aria-label="ctaText || 'Akcija'"
    >
      {{ ctaText || 'Akcija' }}
    </NuxtLink>
  </div>
</template>
