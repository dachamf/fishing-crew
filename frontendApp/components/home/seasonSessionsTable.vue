<script lang="ts" setup>
import type { FishingSession, ID, LaravelPagination } from "~/types/api";

defineOptions({ name: "HomeSeasonSessionsTable" });

const props = withDefaults(
  defineProps<{
    year: number;
    groupId?: ID;
    perPage?: number;
  }>(),
  {
    perPage: 10,
  },
);

type Row = FishingSession & {
  /** dolazi iz withCount('catches') u BE index-u */
  catches_count?: number;
};

const { $api } = useNuxtApp() as any;

const page = ref(1);
const key = computed(() => `home:season-sessions:${props.groupId || 0}:${props.year}:${page.value}`);

const { data, pending, error } = await useAsyncData(
  key.value,
  async () => {
    const res = await $api.get("/v1/sessions", {
      params: {
        user_id: "me",
        season_year: props.year,
        group_id: props.groupId || undefined,
        include: "photos", // ako BE vrati accessor “photos”, lepo ćemo ga iskoristiti
        per_page: props.perPage,
        page: page.value,
      },
    });
    return res.data as LaravelPagination<Row>;
  },
  { watch: [() => key.value] },
);

const items = computed<Row[]>(() => data.value?.data ?? []);
const meta = computed(() => ({
  current_page: data.value?.current_page ?? 1,
  last_page: data.value?.last_page ?? 1,
  total: data.value?.total ?? 0,
}));

function go(p: number) {
  const next = Math.min(Math.max(1, p), meta.value.last_page);
  if (next !== page.value)
    page.value = next;
}

function statusBadge(s?: string) {
  return {
    "badge-warning": s === "open",
    "badge-success": s === "closed",
  };
}
function finalBadge(s?: string | null) {
  return {
    "badge-ghost": !s,
    "badge-success": s === "approved",
    "badge-error": s === "rejected",
  };
}
</script>

<template>
  <div class="card bg-base-100 shadow">
    <div class="card-body">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold">
          Moje sesije u {{ year }}.
        </h2>
        <div v-if="meta.total" class="text-sm opacity-70">
          Ukupno: {{ meta.total }}
        </div>
      </div>

      <div v-if="pending" class="space-y-2 mt-2">
        <div class="skeleton h-6 w-full" />
        <div class="skeleton h-6 w-5/6" />
        <div class="skeleton h-6 w-2/3" />
      </div>

      <div v-else-if="error" class="alert alert-error mt-2">
        Greška pri učitavanju sesija.
      </div>

      <div v-else>
        <div v-if="!items.length" class="opacity-70">
          Nema sesija u ovoj sezoni.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="table">
            <thead>
              <tr>
                <th>Sesija</th>
                <th>Početak</th>
                <th class="text-right">
                  Ulov(a)
                </th>
                <th>Status</th>
                <th>Final</th>
                <th />
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in items" :key="s.id">
                <td class="min-w-[18rem]">
                  <div class="flex items-center gap-3">
                    <!-- Thumb ako postoji -->
                    <div
                      v-if="(s.photos?.length || 0) > 0"
                      class="w-16 h-10 rounded-xl overflow-hidden border border-base-300"
                    >
                      <UiPhoto :photo="s.photos![0]" />
                    </div>
                    <div class="min-w-0">
                      <NuxtLink
                        :to="`/sessions/${s.id}`"
                        class="font-medium hover:underline truncate block"
                        :title="s.title || `Sesija #${s.id}`"
                      >
                        {{ s.title || `Sesija #${s.id}` }}
                      </NuxtLink>
                      <div class="text-xs opacity-70 truncate">
                        {{ s.event?.title || s.group?.name || '' }}
                      </div>
                    </div>
                  </div>
                </td>
                <td>
                  <span class="whitespace-nowrap">
                    {{ s.started_at ? new Date(s.started_at).toLocaleString('sr-RS') : '—' }}
                  </span>
                </td>
                <td class="text-right">
                  {{ s.catches_count ?? s.catches?.length ?? 0 }}
                </td>
                <td>
                  <span class="badge" :class="statusBadge(s.status)">{{ s.status }}</span>
                </td>
                <td>
                  <span class="badge capitalize" :class="finalBadge(s.final_result || null)">
                    {{ s.final_result || '—' }}
                  </span>
                </td>
                <td class="text-right">
                  <NuxtLink :to="`/sessions/${s.id}`" class="btn btn-ghost btn-xs">
                    Otvori
                  </NuxtLink>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Paginacija -->
        <div v-if="meta.last_page > 1" class="join mt-3">
          <button
            class="btn btn-sm join-item"
            :disabled="meta.current_page <= 1"
            @click="go(1)"
          >
            «
          </button>
          <button
            class="btn btn-sm join-item"
            :disabled="meta.current_page <= 1"
            @click="go(meta.current_page - 1)"
          >
            ‹
          </button>
          <button class="btn btn-sm join-item pointer-events-none">
            Str. {{ meta.current_page }} / {{ meta.last_page }}
          </button>
          <button
            class="btn btn-sm join-item"
            :disabled="meta.current_page >= meta.last_page"
            @click="go(meta.current_page + 1)"
          >
            ›
          </button>
          <button
            class="btn btn-sm join-item"
            :disabled="meta.current_page >= meta.last_page"
            @click="go(meta.last_page)"
          >
            »
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
