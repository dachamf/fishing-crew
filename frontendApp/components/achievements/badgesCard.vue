<script setup lang="ts">
type Props = {
  title?: string;
};

const props = withDefaults(defineProps<Props>(), { title: "Bedževi" });

const { items, loading, fetchMy } = useAchievements();

onMounted(() => fetchMy());
</script>

<template>
  <div class="card bg-base-100 shadow-lg">
    <div class="card-body">
      <h2 class="card-title">
        {{ props.title }}
      </h2>

      <div v-if="loading" class="space-y-2">
        <div class="h-6 bg-base-300 animate-pulse rounded" />
        <div class="h-6 bg-base-300 animate-pulse rounded" />
        <div class="h-6 bg-base-300 animate-pulse rounded" />
      </div>

      <div v-else-if="!items.length" class="text-sm opacity-70">
        Još nema bedževa. Vežbaj i vrati se uskoro!
      </div>

      <ul v-else class="grid gap-3 sm:grid-cols-2">
        <li
          v-for="b in items"
          :key="b.code"
          class="flex items-center gap-3"
        >
          <div class="avatar placeholder">
            <div
              class="w-10 rounded-full"
              :class="[b.unlocked ? 'bg-success text-success-content' : 'bg-base-300']"
            >
              <span>{{ b.unlocked ? '★' : '☆' }}</span>
            </div>
          </div>
          <div class="min-w-0">
            <div class="font-medium">
              {{ b.title }}
            </div>
            <div class="text-xs opacity-70">
              {{ b.desc }}
            </div>
          </div>
          <div v-if="b.value != null" class="ml-auto text-xs opacity-80">
            {{ b.value }}
          </div>
        </li>
      </ul>
    </div>
  </div>
</template>
