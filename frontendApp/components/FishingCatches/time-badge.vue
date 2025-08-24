<script setup lang="ts">
const props = defineProps<{
  iso?: string | null
  tz?: string
}>()

const text = computed(() => {
  if (!props.iso) return ''
  return new Intl.DateTimeFormat('sr-RS', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
    hour12: false,
    timeZone: props.tz || 'Europe/Belgrade',
  }).format(new Date(props.iso))
})
</script>

<template>
  <ClientOnly>
    <span class="badge badge-neutral">{{ text }}</span>
    <template #fallback>
      <span class="badge badge-neutral">--/--/---- --:--</span>
    </template>
  </ClientOnly>
</template>
