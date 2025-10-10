<script lang="ts" setup>
import type { GroupLite, HomeActivityItem } from "~/types/api";

defineOptions({ name: "ActivityIndexPage" });

const { $api } = useNuxtApp() as any;
const toast = useToast();

/* Load me (for groups) */
const { data: me } = await useAsyncData(
  "me",
  async () => {
    const r = await $api.get("/v1/me");
    return r.data;
  },
  { server: false, immediate: true },
);

const groups = computed<GroupLite[]>(() =>
  Array.isArray(me.value?.groups) ? me.value!.groups : [],
);
const selectedGroupId = ref<number | null>(null);

/* pick first group once loaded */
watchEffect(() => {
  if (selectedGroupId.value == null && groups.value.length > 0) {
    selectedGroupId.value = groups.value[0]?.id ?? null; // ✅ guarded
  }
});

/* fetch activity (from /v1/home include=activity) */
const {
  data: activityRes,
  pending,
  error,
  refresh,
} = await useAsyncData(
  () => `activity:${selectedGroupId.value ?? 0}`,
  async () => {
    try {
      const res = await $api.get("/v1/home", {
        params: {
          include: "activity",
          group_id: selectedGroupId.value ?? undefined,
        },
      });
      // BE returns { activity: [...] } in Home payload
      return res?.data?.activity ?? [];
    }
    catch (e: any) {
      toast.error(e?.response?.data?.message || "Greška pri učitavanju aktivnosti.");
      return [];
    }
  },
  { watch: [selectedGroupId] },
);

const items = computed<HomeActivityItem[]>(() =>
  Array.isArray(activityRes.value) ? activityRes.value : [],
);

/* helpers */
function labelFor(it: HomeActivityItem): string {
  if (it?.meta && typeof it.meta === "object" && "title" in it.meta && (it.meta as any).title) {
    return String((it.meta as any).title);
  }
  switch (it.type) {
    case "session_opened":
      return "Otvorena sesija";
    case "catch_added":
      return "Dodat ulov";
    case "session_approved":
      return "Sesija odobrena";
    case "session_rejected":
      return "Sesija odbijena";
    default:
      return "Aktivnost";
  }
}
function subtitleFor(it: HomeActivityItem): string {
  const dt = it.created_at ? new Date(it.created_at) : null;
  return dt ? dt.toLocaleString("sr-RS") : "";
}
function hrefFor(it: HomeActivityItem): string {
  const url = it?.meta && typeof it.meta === "object" ? (it.meta as any).url : null;
  return typeof url === "string" && url.length ? url : "#";
}
</script>

<template>
  <div class="max-w-3xl mx-auto p-6">
    <div class="flex items-center gap-3 mb-4">
      <h1 class="text-2xl font-semibold">
        Aktivnost
      </h1>

      <div class="ml-auto flex items-center gap-2">
        <select
          v-model.number="selectedGroupId"
          class="select select-sm select-bordered"
          :disabled="!groups.length"
        >
          <option
            v-for="g in groups"
            :key="g.id"
            :value="g.id"
          >
            {{ g.name }}
          </option>
        </select>

        <!-- ✅ wrap refresh to satisfy TS (expects MouseEvent handler) -->
        <button class="btn btn-sm" @click="() => refresh()">
          Osveži
        </button>
      </div>
    </div>

    <div v-if="pending" class="space-y-2">
      <div class="skeleton h-20" />
      <div class="skeleton h-20" />
      <div class="skeleton h-20" />
    </div>

    <div v-else-if="error" class="alert alert-error">
      Greška pri učitavanju aktivnosti.
      <button class="btn btn-sm ml-auto" @click="() => refresh()">
        Pokušaj ponovo
      </button>
    </div>

    <div v-else>
      <ul v-if="items.length" class="space-y-3">
        <li
          v-for="it in items"
          :key="`${it.type}:${it.id}:${it.created_at}`"
          class="card bg-base-100 shadow"
        >
          <div class="card-body">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <!-- ✅ use label/meta instead of non-existent it.title -->
                <div class="font-medium truncate">
                  {{ labelFor(it) }}
                </div>
                <div class="text-xs opacity-70">
                  {{ subtitleFor(it) }}
                </div>
              </div>
              <NuxtLink :to="hrefFor(it)" class="btn btn-ghost btn-xs">
                Otvori
              </NuxtLink>
            </div>
          </div>
        </li>
      </ul>

      <div v-else class="opacity-70">
        Nema aktivnosti za prikaz.
      </div>

      <div class="mt-4 flex justify-end">
        <!-- ✅ same refresh wrapper here -->
        <button class="btn btn-sm" @click="() => refresh()">
          Osveži
        </button>
      </div>
    </div>
  </div>
</template>
