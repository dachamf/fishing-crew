<script setup lang="ts">
const props = defineProps<{
  groupId?: number | null;
  canManagePrefetched?: boolean;
  shortcutsPrefetched?: { label: string; href: string }[];
}>();

const usePrefetched = computed(
  () => typeof props.canManagePrefetched === "boolean" || Array.isArray(props.shortcutsPrefetched),
);

// 1) Emoji mapa po ruti (možeš dodati još ključeva po potrebi)
const EMOJI_BY_ROUTE: Record<string, string> = {
  "/members": "👥",
  "/groups": "👥",
  "/events": "📅",
  "/sessions/assigned": "✅",
  "/sessions/assigned-to-me": "✅",
};

// 2) Helper: ako label već počinje emoji-jem – ne dupliraj; u suprotnom, prefiksuj prema href
const startsWithEmoji = (s: string) => /^\p{Extended_Pictographic}/u.test(s);
function withEmoji(label: string, href: string) {
  if (startsWithEmoji(label))
    return label;
  const hit = Object.keys(EMOJI_BY_ROUTE).find(key => href.includes(key));
  return hit ? `${EMOJI_BY_ROUTE[hit]} ${label}` : label;
}

const _router = useRouter();
const gid = computed(() => props.groupId ?? null);
const { roles: _roles, canManage, loading, error } = useRoles(gid);

const _canManage = computed(() =>
  usePrefetched.value ? !!props.canManagePrefetched : canManage.value,
);
const _shortcuts = computed(() => {
  const base = usePrefetched.value
    ? (props.shortcutsPrefetched ?? [])
    : [
        { label: "Članovi", href: gid.value ? `/groups/${gid.value}/members` : "/groups" },
        { label: "Događaji", href: "/events" },
        { label: "Odobrenja", href: "/sessions/assigned-to-me" },
      ];
  return base.map(s => ({ ...s, label: withEmoji(s.label, s.href) }));
});
</script>

<template>
  <div class="card bg-base-100 shadow-lg w-full">
    <div class="card-body">
      <div class="flex items-center justify-between">
        <h2 class="card-title">
          Admin
        </h2>
        <span v-if="!usePrefetched && loading" class="loading loading-xs loading-spinner" />
      </div>
      <div v-if="error" class="alert alert-warning text-sm">
        {{ error }}
      </div>

      <div v-if="_canManage" class="grid sm:grid-cols-3 gap-2">
        <NuxtLink
          v-for="s in _shortcuts"
          :key="s.href"
          :to="s.href"
          class="btn btn-sm whitespace-nowrap"
        >
          <!-- (opciono) font emoji fallback -->
          <span class="font-emoji">{{ s.label }}</span>
        </NuxtLink>
      </div>
      <div v-else class="text-sm opacity-70">
        Nema admin privilegija za ovu grupu.
      </div>
    </div>
  </div>
</template>

<style scoped>
.font-emoji {
  font-family: "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji", sans-serif;
}
</style>
