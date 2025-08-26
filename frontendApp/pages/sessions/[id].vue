<script setup lang="ts">
import type { FishingSession, PhotoLite } from '~/types/api'

defineOptions({ name: 'SessionDetailPage' })

const route = useRoute()
const id = Number(route.params.id)
const { $api } = useNuxtApp() as any
const photos = computed<PhotoLite[]>(() => data.value?.photos ?? [])

const { data, pending, error } = await useAsyncData<FishingSession>(
  () => `session:${id}`,
  async () => {
    const res = await $api.get(`/v1/sessions/${id}`, {
      params: { include: 'catches.user,photos' }
    })
    return res.data as FishingSession
  }
)
</script>


<template>
  <div class="container mx-auto p-4 space-y-4">
    <div class="breadcrumbs text-sm">
      <ul>
        <li><NuxtLink to="/catches">Sesije</NuxtLink></li>
        <li>Detalj</li>
      </ul>
    </div>

    <div v-if="pending" class="flex items-center gap-2">
      <span class="loading loading-spinner"></span> Učitavanje…
    </div>
    <div v-else-if="error" class="alert alert-error">Greška pri učitavanju.</div>
    <div v-else>
      <div class="flex items-start justify-between">
        <div>
          <h1 class="text-2xl font-semibold">{{ data?.title || 'Fishing sesija' }}</h1>
          <div class="opacity-75 flex flex-wrap gap-2">
            <FishingCatchesTimeBadge :iso="data?.started_at" :with-time="true" />
            <span v-if="data?.location_name" class="badge badge-ghost">{{ data.location_name }}</span>
            <span v-if="data?.group?.name" class="badge badge-ghost">{{ data.group.name }}</span>
          </div>
        </div>
        <span v-if="data?.status" class="badge badge-outline">{{ data.status }}</span>
      </div>

      <div v-if="(data?.photos?.length||0) > 0" class="mt-3 grid grid-cols-3 gap-2">
        <div 
          v-for="(p, idx) in (data?.photos ?? []).slice(0,3)" 
          :key="idx" 
          class="aspect-video rounded-xl overflow-hidden border border-base-300">
          <img :src="p.url" class="w-full h-full object-cover" loading="lazy"  alt=""/>
        </div>
      </div>

      <div class="divider">Ulov</div>

      <div class="overflow-x-auto">
        <table class="table">
          <thead>
          <tr>
            <th>Vrsta</th>
            <th class="text-right">Kom</th>
            <th class="text-right">Težina (kg)</th>
            <th class="text-right">Najveća (kg)</th>
            <th>Korisnik</th>
            <th>Status</th>
            <th></th>
          </tr>
          </thead>
          <tbody>
          <tr v-for="row in data?.catches || []" :key="row.id">
            <td>{{ row.species_label || row.species || row.species_name || '-' }}</td>
            <td class="text-right">{{ row.count }}</td>
            <td class="text-right">{{ Number(row.total_weight_kg||0).toFixed(3) }}</td>
            <td class="text-right">{{ Number(row.biggest_single_kg||0).toFixed(3) }}</td>
            <td>
              <div class="flex items-center gap-2">
                <div class="avatar">
                  <div class="w-6 rounded-full overflow-hidden border border-base-300">
                    <img :src="row.user?.avatar_url || '/icons/icon-64.png'">
                  </div>
                </div>
                <span class="text-sm">{{ row.user?.display_name || row.user?.name }}</span>
              </div>
            </td>
            <td>
              <span
                class="badge" :class="{
                'badge-warning': row.status==='pending',
                'badge-success': row.status==='approved',
                'badge-error': row.status==='rejected',
              }">
                {{ row.status }}
              </span>
            </td>
            <td class="text-right">
              <NuxtLink :to="`/catches/${row.id}`" class="btn btn-ghost btn-xs">Detalji ulova</NuxtLink>
            </td>
          </tr>
          </tbody>
        </table>
      </div>

    </div>
  </div>
</template>
