<script setup lang="ts">
import type { GroupMember } from "~/types/api";

defineOptions({ name: "GroupMembersPage" });

const route = useRoute();
const id = computed(() => Number(route.params.id));
const { $api } = useNuxtApp() as any;
const { user } = useAuth();

const errorMessage = ref<string | null>(null);
const query = ref("");

const {
  data: members,
  pending: loading,
  error,
} = await useAsyncData<GroupMember[]>(
  () => `group:${id.value}:members`,
  async () => {
    errorMessage.value = null;
    if (!id.value || Number.isNaN(id.value)) {
      errorMessage.value = "Neispravan ID grupe.";
      return [];
    }
    const res = await $api.get(`/v1/groups/${id.value}/members`);
    const raw: any[] = res.data?.data ?? res.data ?? [];
    return raw.map((m: any) => ({
      ...m,
      display_name: m.profile?.display_name ?? m.display_name,
      avatar_url: m.profile?.avatar_url ?? m.avatar_url,
      role: m.pivot?.role ?? m.role,
    }));
  },
  {
    server: false,
    default: () => [],
    watch: [id],
  },
);

const { data: groupInfo } = await useAsyncData<{ name?: string }>(
  () => `group:${id.value}:info`,
  async () => {
    if (!id.value || Number.isNaN(id.value))
      return {};
    const res = await $api.get(`/v1/groups/${id.value}`);
    return { name: res.data?.name };
  },
  { server: false, watch: [id] },
);

watch(error, (e: any) => {
  if (e?.response?.data?.message) {
    errorMessage.value = e.response.data.message;
  }
});

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase();
  if (!q)
    return members.value ?? [];
  return (members.value ?? []).filter((m) => {
    const name = (m.display_name || m.name || "").toLowerCase();
    const email = (m.email || "").toLowerCase();
    const role = (m.role || "").toLowerCase();
    return name.includes(q) || email.includes(q) || role.includes(q);
  });
});

const myId = computed(() => user.value?.id ?? null);
</script>

<template>
  <div class="container mx-auto p-4 space-y-4">
    <div class="card bg-base-100 shadow">
      <div class="card-body">
        <div class="flex items-center justify-between gap-3">
          <div>
            <h1 class="text-2xl font-semibold tracking-tight">
              Članovi grupe
              <span
                v-if="groupInfo?.name"
                class="opacity-60 font-normal"
              >— {{ groupInfo.name }}</span>
            </h1>
            <p class="text-sm opacity-70">
              Brz pregled članova i uloga u grupi.
            </p>
          </div>
          <NuxtLink class="btn btn-ghost btn-sm" :to="`/groups/${id}`">
            ← Nazad
          </NuxtLink>
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-3">
          <div class="join w-full md:w-auto">
            <input
              v-model="query"
              type="search"
              class="input input-bordered join-item w-full md:w-64"
              placeholder="Pretraga po imenu, emailu ili ulozi…"
            >
            <button class="btn join-item" @click="query = ''">
              Reset
            </button>
          </div>
          <div class="flex items-center gap-2 text-sm opacity-70">
            <span class="badge badge-outline"> Ukupno: {{ members?.length || 0 }} </span>
            <span class="badge badge-outline"> Prikazano: {{ filtered.length }} </span>
          </div>
        </div>
      </div>
    </div>

    <div v-if="loading" class="grid gap-3">
      <div
        v-for="i in 5"
        :key="i"
        class="card bg-base-100 shadow"
      >
        <div class="card-body">
          <div class="skeleton h-6 w-40" />
        </div>
      </div>
    </div>

    <div v-else-if="errorMessage" class="alert alert-error">
      {{ errorMessage }}
    </div>

    <div v-else-if="!filtered.length" class="opacity-70">
      Nema članova.
    </div>

    <div v-else class="grid gap-3 md:grid-cols-2">
      <div
        v-for="m in filtered"
        :key="m.id"
        class="card shadow border"
        :class="
          m.id === myId
            ? 'bg-success/5 border-success/20 ring-1 ring-success/10'
            : 'bg-base-100 border-base-200'
        "
      >
        <div class="card-body flex items-center justify-between gap-3">
          <div class="flex items-center gap-3">
            <div class="avatar">
              <div
                class="w-11 rounded-full border overflow-hidden ring-2"
                :class="
                  m.id === myId
                    ? 'border-success/40 ring-success/30'
                    : 'border-base-300 ring-base-200'
                "
              >
                <img :src="m.profile?.avatar_url || m.avatar_url || '/icons/icon-64.png'" alt="">
              </div>
            </div>
            <div class="leading-tight">
              <div class="font-semibold" :class="m.id === myId ? 'text-success' : ''">
                {{ m.display_name || m.name || `#${m.id}` }}
              </div>
              <div class="text-sm" :class="m.id === myId ? 'text-success/80' : 'opacity-70'">
                {{ m.email || '—' }}
              </div>
            </div>
          </div>
          <span
            class="badge badge-outline capitalize"
            :class="m.id === myId ? 'badge-success' : ''"
          >
            {{ m.role || 'member' }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>
