<script lang="ts" setup>
defineOptions({ name: "NavAssignedBell" });

const { items, loading, fetchOnce, startPolling } = useAssignedPreview();

const auth = useAuth();
const route = useRoute();

const { total: assignedCount, refresh: refreshCount } = useAssignedCount();

const timeago = useRelativeTime();

// init + lagani polling
onMounted(() => {
  fetchOnce();
  startPolling(60_000);
  refreshCount();
});

// refresh na promenu rute / usera
watch([() => route.fullPath, () => auth.user.value?.id], () => {
  fetchOnce();
  refreshCount();
});
</script>

<template>
  <div class="dropdown dropdown-end">
    <div
      class="btn btn-ghost btn-circle"
      role="button"
      aria-label="Ulovi dodeljeni meni"
    >
      <div class="indicator">
        <Icon name="tabler:clipboard-check" size="20" />
        <span
          v-if="assignedCount > 0"
          class="badge badge-secondary badge-sm absolute -right-1 -top-1"
          title="Ulovi koji čekaju tvoju odluku"
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

        <ul v-else-if="(items?.length || 0) > 0" class="mt-1 space-y-2">
          <li
            v-for="c in items || []"
            :key="c.id"
            class="flex items-start justify-between gap-3"
          >
            <div class="min-w-0">
              <NuxtLink
                :title="`Ulov #${c.id}`"
                :to="`/catches/${c.id}`"
                class="font-medium text-sm hover:underline truncate block"
              >
                Ulov #{{ c.id }}
              </NuxtLink>
              <div class="text-xs opacity-70">
                Datum:
                {{ c.caught_at ? timeago(c.caught_at) : '—' }} • Kom:
                {{ c.count ?? '—' }}
              </div>
            </div>
            <NuxtLink :to="`/catches/${c.id}`" class="btn btn-ghost btn-xs">
              Otvori
            </NuxtLink>
          </li>
        </ul>

        <div v-else class="opacity-70 text-sm">
          Nema ulova koji čekaju tvoju odluku.
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
