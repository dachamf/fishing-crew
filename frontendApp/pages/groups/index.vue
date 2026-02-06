<script setup lang="ts">
import type { GroupLite } from "~/types/api";

defineOptions({ name: "GroupsIndexPage" });

const { $api } = useNuxtApp() as any;
const toast = useToast();

const groups = ref<GroupLite[]>([]);
const loading = ref(true);

async function load() {
  loading.value = true;
  try {
    const res = await $api.get("/v1/groups");
    const payload = res.data ?? {};
    groups.value = payload.data ?? payload.items ?? payload ?? [];
  }
  catch (e: any) {
    toast.error(e?.response?.data?.message || "Greška pri učitavanju grupa");
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
        Grupe
      </h1>
    </div>

    <div v-if="loading" class="grid gap-3">
      <div
        v-for="i in 3"
        :key="i"
        class="card bg-base-100 shadow"
      >
        <div class="card-body">
          <div class="skeleton h-6 w-48" />
          <div class="mt-2 flex gap-2">
            <div class="skeleton h-5 w-20" />
            <div class="skeleton h-5 w-24" />
          </div>
        </div>
      </div>
    </div>

    <div v-else-if="!groups.length" class="opacity-70">
      Nema grupa.
    </div>

    <div v-else class="grid gap-3 md:grid-cols-2">
      <div
        v-for="g in groups"
        :key="g.id"
        class="card bg-base-100 shadow"
      >
        <div class="card-body">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h2 class="text-lg font-semibold">
                {{ g.name }}
              </h2>
              <div class="flex flex-wrap gap-2 opacity-75">
                <span v-if="g.season_year" class="badge badge-ghost">
                  Sezona: {{ g.season_year }}
                </span>
                <span class="badge badge-ghost"> Članovi: {{ g.members_count ?? '—' }} </span>
                <span class="badge badge-ghost"> Događaji: {{ g.events_count ?? '—' }} </span>
              </div>
            </div>
            <div class="flex gap-2">
              <NuxtLink class="btn btn-ghost btn-xs" :to="`/groups/${g.id}`">
                Detalji
              </NuxtLink>
              <NuxtLink class="btn btn-primary btn-xs" :to="`/groups/${g.id}/members`">
                Članovi
              </NuxtLink>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
