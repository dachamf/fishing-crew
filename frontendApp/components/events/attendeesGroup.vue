<script lang="ts" setup>
type Att = { user_id: number; user?: any; rsvp?: string | null; pivot?: { rsvp?: string | null } };

const props = defineProps<{
  title: string;
  list: Att[];
  tone?: "success" | "warning" | "ghost";
}>();

function avatarUrl(a: Att) {
  return a?.user?.profile?.avatar_url || a?.user?.avatar_url || "/icons/icon-64.png";
}

function label(a: Att) {
  return a?.user?.profile?.display_name || a?.user?.display_name || a?.user?.name || `#${a.user_id}`;
}
</script>

<template>
  <div class="space-y-2">
    <div class="flex items-center justify-between">
      <span class="badge badge-sm" :class="`badge-${props.tone || 'ghost'}`">{{
        props.title
      }}</span>
      <span class="text-xs opacity-60">{{ props.list.length }}</span>
    </div>

    <div class="flex flex-wrap gap-2">
      <div
        v-for="(a, i) in list.slice(0, 8)"
        :key="`${a.user_id}-${i}`"
        class="tooltip"
        :data-tip="label(a)"
      >
        <div class="avatar">
          <div class="w-7 rounded-full border border-base-300 overflow-hidden">
            <img :src="avatarUrl(a)" alt="">
          </div>
        </div>
      </div>

      <span
        v-if="props.list.length > 8"
        class="badge badge-ghost"
      >+{{ props.list.length - 8 }}</span>
    </div>
  </div>
</template>
