<script lang="ts" setup>
defineOptions({ name: "ActivityIndexPage" });

const isHydrated = ref(false);
onMounted(() => {
  isHydrated.value = true;
});

const TYPE_LABELS = {
  catch_added: "Dodat ulov",
  session_opened: "Sesija otvorena",
  session_approved: "Sesija odobrena",
  session_rejected: "Sesija odbijena",
} as const;

function titleFor(it: any) {
  const t = it?.type as keyof typeof TYPE_LABELS | undefined;
  return it?.title ?? (t ? TYPE_LABELS[t] : "Aktivnost");
}

function fmtAbsolute(iso?: string | null) {
  return iso
    ? new Intl.DateTimeFormat("sr-RS", { dateStyle: "medium", timeStyle: "short" }).format(
        new Date(iso),
      )
    : "—";
}

function fmtAgo(iso?: string | null) {
  if (!iso)
    return "—";
  const s = (Date.now() - new Date(iso).getTime()) / 1000;
  if (s < 60)
    return `${Math.max(1, Math.floor(s))}s`;
  if (s < 3600)
    return `${Math.floor(s / 60)}m`;
  if (s < 86400)
    return `${Math.floor(s / 3600)}h`;
  return fmtAbsolute(iso);
}

// SSR/CSR usklađen prikaz vremena
function timeText(iso?: string | null) {
  return isHydrated.value ? fmtAgo(iso) : fmtAbsolute(iso);
}

const { $api } = useNuxtApp() as any;
const toast = useToast();

// ikonice & ton
function typeIcon(t: string) {
  switch (t) {
    case "catch_added":
      return "tabler:fish";
    case "session_opened":
      return "tabler:clipboard-plus";
    case "session_approved":
      return "tabler:thumb-up";
    case "session_rejected":
      return "tabler:thumb-down";
    default:
      return "tabler:activity";
  }
}
function typeTone(t: string) {
  switch (t) {
    case "catch_added":
      return "badge-primary";
    case "session_opened":
      return "badge-info";
    case "session_approved":
      return "badge-success";
    case "session_rejected":
      return "badge-error";
    default:
      return "badge-ghost";
  }
}
function activitySub(it: any) {
  if (it?.meta?.url)
    return it.meta.url;
  return `ID #${it?.ref_id ?? it?.id ?? "—"}`;
}

/* (opciono) me, ako želiš prikaz grupa u UI — ne koristimo group_id više */
const { data: me } = await useAsyncData(
  "me",
  async () => {
    const r = await $api.get("/v1/me");
    return r.data;
  },
  { server: false, immediate: true },
);

const groups = computed(() => me.value?.groups ?? []);
const selectedGroupId = ref<number | null>(null);

// može ostati radi UI, ali ne šaljemo group_id
watchEffect(() => {
  if (selectedGroupId.value == null && groups.value.length > 0) {
    selectedGroupId.value = groups.value[0]?.id ?? null;
  }
});

/* Aktivnost (iz /v1/home?include=activity) — vraća niz bez meta */
const {
  data: activityRes,
  pending,
  refresh,
} = await useAsyncData(
  // key se i dalje veže na selectedGroupId da refresh-uje ako promeniš izbor
  () => `activity:${selectedGroupId.value ?? 0}`,
  async () => {
    try {
      const res = await $api.get("/v1/home", {
        params: {
          include: "activity",
          // group_id: selectedGroupId.value ?? undefined, // ⬅️ ne šaljemo u single-tenant
        },
      });
      return res?.data?.activity ?? [];
    }
    catch (e: any) {
      toast.error(e?.response?.data?.message || "Greška pri učitavanju aktivnosti.");
      return [];
    }
  },
  { watch: [selectedGroupId] },
);

// ✅ OVO su tvoje stavke
const items = computed<any[]>(() => (Array.isArray(activityRes.value) ? activityRes.value : []));
</script>

<template>
  <div class="mx-auto max-w-4xl p-6">
    <!-- Header -->
    <div class="card bg-base-100 shadow-sm mb-4">
      <div class="card-body gap-3 md:flex md:items-center md:justify-between">
        <div>
          <h1 class="text-2xl font-semibold">
            Aktivnost
          </h1>
          <p class="opacity-70 text-sm">
            Sveža dešavanja.
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <!-- (opciono) UI selektor grupe — čisto vizuelno -->
          <select
            v-if="groups.length"
            v-model="selectedGroupId"
            class="select select-bordered select-sm"
            aria-label="Izaberi grupu"
          >
            <option
              v-for="g in groups"
              :key="g.id"
              :value="g.id"
            >
              {{ g.name }}
            </option>
          </select>

          <span
            v-if="Array.isArray(items) && items.length"
            class="badge badge-ghost"
            aria-label="Ukupno stavki"
          >
            {{ items.length }} stavki
          </span>

          <button
            class="btn btn-sm"
            :class="{ 'btn-disabled': pending }"
            :disabled="pending"
            @click="refresh()"
          >
            <Icon name="tabler:refresh" class="mr-1" />
            {{ pending ? 'Osvežavam…' : 'Osveži' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Skeletoni -->
    <div v-if="pending" class="space-y-3">
      <div
        v-for="i in 4"
        :key="i"
        class="card bg-base-100 shadow-sm"
      >
        <div class="card-body">
          <div class="flex gap-3">
            <div class="skeleton h-10 w-10 rounded-full" />
            <div class="flex-1 space-y-2">
              <div class="skeleton h-4 w-40" />
              <div class="skeleton h-3 w-24" />
              <div class="skeleton h-3 w-64" />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else-if="!items?.length" class="card bg-base-100 shadow-sm">
      <div class="card-body items-center text-center">
        <Icon name="tabler:calendar-off" size="40" />
        <h2 class="card-title mt-1">
          Nema aktivnosti
        </h2>
        <p class="opacity-70">
          Bićemo te obavestili kada se pojavi nešto novo.
        </p>
        <button class="btn btn-sm mt-2" @click="refresh()">
          <Icon name="tabler:refresh" class="mr-1" /> Osveži
        </button>
      </div>
    </div>

    <!-- Timeline lista -->
    <div v-else class="card bg-base-100 shadow-sm">
      <div class="card-body">
        <TransitionGroup
          name="list-fade"
          tag="ul"
          class="timeline timeline-vertical w-full"
        >
          <li v-for="it in items" :key="`${it.type}-${it.id}-${it.created_at}`">
            <div class="timeline-start text-xs opacity-70">
              {{ timeText(it.created_at) }}
            </div>
            <div class="timeline-middle">
              <div class="avatar placeholder">
                <div class="bg-base-200 rounded-full w-8 h-8 grid place-items-center">
                  <Icon :name="typeIcon(it.type)" size="18" />
                </div>
              </div>
            </div>
            <div class="timeline-end mb-6 w-full">
              <div class="card bg-base-200/60 shadow-sm">
                <div class="card-body p-4">
                  <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                      <div class="flex items-center gap-2">
                        <h3 class="font-semibold truncate">
                          {{ titleFor(it) }}
                        </h3>
                        <span class="badge badge-xs" :class="typeTone(it.type)">
                          {{ it.type.replace('session_', '').replace('_', ' ') }}
                        </span>
                      </div>
                      <p class="text-sm opacity-70 truncate">
                        {{ activitySub(it) }}
                      </p>
                    </div>

                    <NuxtLink
                      v-if="it?.meta?.url"
                      class="btn btn-ghost btn-xs"
                      :to="it.meta.url"
                      :aria-label="`Otvori ${titleFor(it)}`"
                      title="Otvori detalj"
                    >
                      Otvori
                    </NuxtLink>
                  </div>
                </div>
              </div>
            </div>
          </li>
        </TransitionGroup>

        <!-- Refresh (bez "load more" jer nema meta) -->
        <div class="pt-2 flex items-center gap-2">
          <button
            class="btn btn-sm"
            :disabled="pending"
            @click="refresh()"
          >
            <Icon name="tabler:refresh" class="mr-1" />
            Osveži
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.list-fade-enter-active,
.list-fade-leave-active {
  transition: all 0.18s ease;
}
.list-fade-enter-from,
.list-fade-leave-to {
  opacity: 0;
  transform: translateY(4px);
}
</style>
