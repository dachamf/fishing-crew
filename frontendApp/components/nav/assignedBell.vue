<script lang="ts" setup>
import type { FishingSession } from "~/types/api";

import { useRelativeTime } from "~/composables/useRelativeTime";

defineOptions({ name: "NavAssignedBell" });

const auth = useAuth();
const route = useRoute();

const { assignedToMe } = useSessionReview();
const { total: assignedCount, refresh: refreshCount } = useAssignedCount();

// Proširenje tipa: sessions iz preview-a dolaze sa catches_count
type AssignedSession = FishingSession & { catches_count?: number };

const assignedPreview = ref<{ items: AssignedSession[]; meta: any } | null>(null);
const loading = ref(false);
const timeago = useRelativeTime();

async function loadAssignedPreview() {
  if (!auth.user.value) {
    // FIX: bez nedeklarisanih items/meta
    assignedPreview.value = { items: [], meta: null };
    return;
  }
  loading.value = true;
  let attempt = 0;
  const wait = 500;

  while (attempt < 3) {
    try {
      const { items, meta } = await assignedToMe(1, 5);
      assignedPreview.value = {
        items: (Array.isArray(items) ? items : []) as AssignedSession[],
        meta,
      };
    }
    catch {
      assignedPreview.value = { items: [], meta: null };
      attempt++;
      if (attempt < 3) {
        await new Promise(r => setTimeout(r, wait));
      }
    }
    finally {
      loading.value = false;
    }
  }
}

// init + lagani polling
onMounted(() => {
  loadAssignedPreview();
  refreshCount();
  if (import.meta.client) {
    const iv = setInterval(() => {
      loadAssignedPreview();
      refreshCount();
    }, 60_000);
    onUnmounted(() => clearInterval(iv));
  }
});

// refresh na promenu rute / usera
watch([() => route.fullPath, () => auth.user.value?.id], () => {
  loadAssignedPreview();
  refreshCount();
});
</script>

<template>
  <div class="dropdown dropdown-end">
    <div
      class="btn btn-ghost btn-circle"
      role="button"
      aria-label="Sesije dodeljene meni"
    >
      <div class="indicator">
        <Icon name="tabler:clipboard-check" size="20" />
        <span
          v-if="assignedCount > 0"
          class="badge badge-secondary badge-sm absolute -right-1 -top-1"
          title="Sesije koje čekaju tvoju odluku"
        >{{ assignedCount }}</span>
      </div>
    </div>

    <div class="mt-3 dropdown-content w-80 card card-compact bg-base-100 shadow z-50">
      <div class="card-body">
        <div class="flex items-center justify-between">
          <h3 class="card-title text-base">
            Za moju odluku
          </h3>
          <NuxtLink class="link link-primary text-sm" to="/sessions/assigned">
            Vidi sve
          </NuxtLink>
        </div>

        <div v-if="loading" class="mt-2 space-y-2">
          <div
            v-for="i in 3"
            :key="i"
            class="skeleton h-6 w-56"
          />
        </div>

        <ul v-else-if="(assignedPreview?.items?.length || 0) > 0" class="mt-1 space-y-2">
          <li
            v-for="s in assignedPreview?.items || []"
            :key="s.id"
            class="flex items-start justify-between gap-3"
          >
            <div class="min-w-0">
              <NuxtLink
                :title="s.title || `Sesija #${s.id}`"
                :to="`/sessions/${s.id}`"
                class="font-medium text-sm hover:underline truncate block"
              >
                {{ s.title || `Sesija #${s.id}` }}
              </NuxtLink>
              <div class="text-xs opacity-70">
                Počela:
                {{ s.started_at ? timeago(s.started_at) : '—' }} • Ulova:
                {{ s.catches_count ?? s.catches?.length ?? '—' }}
              </div>
            </div>
            <NuxtLink :to="`/sessions/${s.id}`" class="btn btn-ghost btn-xs">
              Otvori
            </NuxtLink>
          </li>
        </ul>

        <div v-else class="opacity-70 text-sm">
          Nema sesija koje čekaju tvoju odluku.
        </div>

        <div class="pt-1">
          <NuxtLink class="btn btn-primary btn-sm w-full" to="/sessions/assigned">
            Otvori listu zadataka
          </NuxtLink>
        </div>
      </div>
    </div>
  </div>
</template>
