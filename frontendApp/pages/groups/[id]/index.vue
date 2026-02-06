<script setup lang="ts">
import type { GroupLite } from "~/types/api";

defineOptions({ name: "GroupDetailPage" });

const route = useRoute();
const id = Number(route.params.id);
const { $api } = useNuxtApp() as any;
const toast = useToast();

const group = ref<GroupLite | null>(null);
const loading = ref(true);

async function load() {
  loading.value = true;
  try {
    const res = await $api.get(`/v1/groups/${id}`);
    group.value = res.data as GroupLite;
  }
  catch (e: any) {
    toast.error(e?.response?.data?.message || "Greška pri učitavanju grupe");
  }
  finally {
    loading.value = false;
  }
}

onMounted(load);
</script>

<template>
  <div class="container mx-auto p-4 space-y-4">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold">
        Detalj grupe
      </h1>
      <NuxtLink class="btn btn-ghost btn-sm" to="/groups">
        ← Sve grupe
      </NuxtLink>
    </div>

    <div v-if="loading" class="card bg-base-100 shadow">
      <div class="card-body">
        <div class="skeleton h-6 w-48" />
        <div class="mt-2 flex gap-2">
          <div class="skeleton h-5 w-20" />
          <div class="skeleton h-5 w-24" />
        </div>
      </div>
    </div>

    <div v-else-if="!group" class="opacity-70">
      Grupa nije pronađena.
    </div>

    <div v-else class="card bg-base-100 shadow">
      <div class="card-body space-y-2">
        <h2 class="text-xl font-semibold">
          {{ group.name }}
        </h2>
        <div class="flex flex-wrap gap-2 opacity-75">
          <span v-if="group.season_year" class="badge badge-ghost">
            Sezona: {{ group.season_year }}
          </span>
          <span class="badge badge-ghost">
            Članovi: {{ (group as any).members_count ?? '—' }}
          </span>
          <span class="badge badge-ghost">
            Događaji: {{ (group as any).events_count ?? '—' }}
          </span>
        </div>
        <div class="pt-2">
          <NuxtLink class="btn btn-primary btn-sm" :to="`/groups/${group.id}/members`">
            Vidi članove
          </NuxtLink>
        </div>
      </div>
    </div>
  </div>
</template>
