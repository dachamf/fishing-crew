<script setup lang="ts">
import type { HomeMiniLeaderboard } from "~/types/api";

defineProps<{
  data?: HomeMiniLeaderboard;
  groupId?: number;
  year?: number;
  limit?: number;
  title?: string;
  viewAllTo?: string;
}>();
</script>

<template>
  <div class="card bg-base-100 shadow">
    <div class="card-body">
      <div class="flex items-center justify-between">
        <h2 class="card-title">
          {{ title || 'Mini leaderboard' }}
        </h2>
        <NuxtLink
          v-if="viewAllTo"
          :to="viewAllTo"
          class="link link-primary text-sm"
        >
          Vidi sve
        </NuxtLink>
      </div>

      <div v-if="!data" class="grid grid-cols-2 gap-4">
        <div class="space-y-2">
          <div class="skeleton h-5 w-full" />
          <div class="skeleton h-5 w-4/5" />
          <div class="skeleton h-5 w-3/5" />
        </div>
        <div class="space-y-2">
          <div class="skeleton h-5 w-full" />
          <div class="skeleton h-5 w-4/5" />
          <div class="skeleton h-5 w-3/5" />
        </div>
      </div>

      <div v-else class="grid md:grid-cols-2 gap-6">
        <!-- Total weight -->
        <div>
          <h3 class="font-medium mb-2">
            Top po ukupnoj težini
          </h3>
          <ol class="space-y-2">
            <li
              v-for="(row, i) in data.weightTop || []"
              :key="row.user.id"
              class="flex items-center justify-between"
            >
              <div class="flex items-center gap-2 min-w-0">
                <span class="badge badge-ghost">{{ i + 1 }}</span>
                <div class="avatar">
                  <div class="w-7 rounded-full border border-base-300 overflow-hidden">
                    <img :src="row.user.avatar_url || '/icons/icon-64.png'" alt="">
                  </div>
                </div>
                <span class="truncate">{{
                  row.user.display_name || row.user.name || `#${row.user.id}`
                }}</span>
              </div>
              <span class="font-medium">{{ Number(row.total_weight_kg || 0).toFixed(2) }} kg</span>
            </li>
          </ol>
        </div>

        <!-- Biggest single -->
        <div>
          <h3 class="font-medium mb-2">
            Najveća pojedinačna
          </h3>
          <ol class="space-y-2">
            <li
              v-for="(row, i) in data.biggestTop || []"
              :key="row.user.id"
              class="flex items-center justify-between"
            >
              <div class="flex items-center gap-2 min-w-0">
                <span class="badge badge-ghost">{{ i + 1 }}</span>
                <div class="avatar">
                  <div class="w-7 rounded-full border border-base-300 overflow-hidden">
                    <img :src="row.user.avatar_url || '/icons/icon-64.png'" alt="">
                  </div>
                </div>
                <span class="truncate">{{
                  row.user.display_name || row.user.name || `#${row.user.id}`
                }}</span>
              </div>
              <span class="font-medium">{{ Number(row.biggest_single_kg || 0).toFixed(2) }} kg</span>
            </li>
          </ol>
        </div>
      </div>
    </div>
  </div>
</template>
