<script setup lang="ts">
import type { HomeAchievement } from "~/types/api";

const props = defineProps<{ items?: HomeAchievement[] }>();
const items = computed(() => props.items ?? []);
</script>

<template>
  <div class="card bg-base-100 shadow">
    <div class="card-body">
      <h2 class="card-title">
        Bedževi
      </h2>

      <div v-if="items === undefined" class="grid grid-cols-3 gap-3">
        <div class="skeleton h-20" />
        <div class="skeleton h-20" />
        <div class="skeleton h-20" />
      </div>

      <div
        v-else-if="(items?.length || 0) > 0"
        class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3"
      >
        <div
          v-for="b in items"
          :key="b.id || b.key"
          class="border border-base-300 rounded-xl p-3 flex items-center gap-3"
          :class="b.unlocked_at ? 'bg-base-100' : 'bg-base-200 opacity-80'"
          :title="b.title || b.key"
        >
          <Icon :name="b.unlocked_at ? 'tabler:award' : 'tabler:lock'" size="24" />
          <div class="min-w-0">
            <div class="font-medium truncate">
              {{ b.title || b.key || 'Bedž' }}
            </div>
            <div class="text-xs opacity-70">
              {{
                b.unlocked_at ? new Date(b.unlocked_at).toLocaleDateString('sr-RS') : 'Zaključano'
              }}
            </div>
          </div>
        </div>
      </div>

      <div v-else class="opacity-70 text-sm">
        Još uvek nema bedževa.
      </div>
    </div>
  </div>
</template>
