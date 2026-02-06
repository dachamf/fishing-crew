<script lang="ts" setup>
defineOptions({ name: "SessionsAssignedPage" });

const route = useRoute();
const router = useRouter();

const page = ref<number>(Number(route.query.page || 1));

watch(page, (p) => {
  const q = new URLSearchParams({ ...(route.query as any), page: String(p) });
  router.replace({ path: route.path, query: Object.fromEntries(q) });
});
</script>

<template>
  <div class="container mx-auto p-4 space-y-4">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold">
        Ulovi koji čekaju mene
      </h1>
      <NuxtLink class="btn btn-ghost btn-sm" to="/sessions">
        ← Sve sesije
      </NuxtLink>
    </div>

    <CatchAssignedList :page="page" @page="(p) => (page = p)" />
  </div>
</template>
