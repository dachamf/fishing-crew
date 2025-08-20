<script setup lang="ts">
interface Props{ event: any }
const props = defineProps<Props>()
</script>

<template>
  <div class="card w-1/2 shadow-xl">
    <div class="card-body">
      <h2 class="card-title">
        <NuxtLink :to="`/events/${props.event.id}`" class="link link-hover">
          {{ props.event.title }}
        </NuxtLink>
      </h2>
      <p class="text-sm opacity-70">
        {{ props.event.location_name }} · {{ new Date(props.event.start_at).toLocaleString() }}
      </p>
      <div class="card-actions justify-end">
        <span class="badge">{{ props.event.status }}</span>
      </div>
    </div>
  </div>
</template>
