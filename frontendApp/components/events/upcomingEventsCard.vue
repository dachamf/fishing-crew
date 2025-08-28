<script setup lang="ts">
import type { Rsvp } from "~/types/api";

type Props = {
  title?: string;
  groupId?: number;
  limit?: number;
  viewAllTo?: string;
};
const props = withDefaults(defineProps<Props>(), {
  title: "Upcoming events",
  limit: 3,
  viewAllTo: "/events",
});

const { items, loading, fetchUpcoming, rsvp } = useEvents();

onMounted(() => {
  fetchUpcoming(props.limit, props.groupId);
});

watch(
  () => props.groupId,
  (gid) => {
    fetchUpcoming(props.limit, gid);
  },
);

function onRsvp(id: number, status: Rsvp) {
  rsvp(id, status);
}

const hasData = computed(() => !loading.value && items.value.length > 0);
</script>

<template>
  <div class="card bg-base-100 shadow-lg">
    <div class="card-body">
      <div class="flex items-center justify-between">
        <h2 class="card-title">
          {{ title }}
        </h2>
        <NuxtLink :to="viewAllTo" class="link link-primary">
          Vidi sve
        </NuxtLink>
      </div>

      <!-- Skeleton -->
      <div v-if="loading" class="space-y-4">
        <div class="animate-pulse h-6 bg-base-300 rounded" />
        <div class="animate-pulse h-6 bg-base-300 rounded" />
        <div class="animate-pulse h-6 bg-base-300 rounded" />
      </div>

      <!-- Empty -->
      <div v-else-if="!hasData" class="text-sm opacity-70">
        Nema skorih događaja. <NuxtLink :to="viewAllTo" class="link">
          Pogledaj sve →
        </NuxtLink>
      </div>

      <!-- List -->
      <ul v-else class="menu gap-3">
        <li
          v-for="e in items"
          :key="e.id"
          class="p-0"
        >
          <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
              <div class="font-medium truncate">
                {{ e.title }}
              </div>
              <div class="text-xs opacity-70">
                {{ toDatetimeLocal(e.start_at) }}
              </div>
            </div>
            <div class="btn-group">
              <button
                class="btn btn-sm"
                :class="e.my_rsvp?.status === 'yes' ? 'btn-primary' : 'btn-outline'"
                @click="onRsvp(e.id, 'yes')"
              >
                Ideš
              </button>
              <button
                class="btn btn-sm"
                :class="e.my_rsvp?.status === 'undecided' ? 'btn-primary' : 'btn-outline'"
                @click="onRsvp(e.id, 'undecided')"
              >
                Možda
              </button>
              <button
                class="btn btn-sm"
                :class="e.my_rsvp?.status === 'no' ? 'btn-primary' : 'btn-outline'"
                @click="onRsvp(e.id, 'no')"
              >
                Ne
              </button>
            </div>
          </div>
        </li>
      </ul>
    </div>
  </div>
</template>
