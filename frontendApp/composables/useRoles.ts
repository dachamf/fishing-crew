import type { Role } from "~/types/api";

export function useRoles(groupId: Ref<number | null | undefined>) {
  const roles = ref<Role[]>([]);
  const loading = ref(false);
  const error = ref<string | null>(null);
  const { $api } = useNuxtApp() as any;

  const canManage = computed(() => roles.value.includes("owner") || roles.value.includes("mod"));

  async function fetchRoles() {
    const gid = groupId.value;
    if (!gid)
      return;
    loading.value = true;
    error.value = null;
    try {
      const res = await $api.get("/v1/me/roles", {
        params: { group_id: gid },
        withCredentials: true,
      });
      roles.value = (res?.data?.roles ?? []) as Role[];
    }
    catch (e: any) {
      error.value = e?.response?.data?.message ?? "Greška pri učitavanju rola";
    }
    finally {
      loading.value = false;
    }
  }

  watch(
    groupId,
    () => {
      void fetchRoles();
    },
    {
      immediate: true,
    },
  );

  return { roles, canManage, loading, error, fetchRoles };
}
