<script setup lang="ts">
const props = defineProps<{ groupId?: number | null }>();
const router = useRouter();

const gid = computed(() => props.groupId ?? null);
const { roles, canManage, loading, error } = useRoles(gid);

// Shortcut navigacije — prilagodi svojim realnim rutama
function goMembers() {
  router.push(`/groups/${gid.value}/members`);
}
function goEvents() {
  router.push(`/events`);
}
function goApprovals() {
  router.push(`/sessions/assigned-to-me`);
}
</script>

<template>
  <div class="card bg-base-100 shadow-lg w-full">
    <div class="card-body">
      <div class="flex items-center justify-between">
        <h2 class="card-title">
          Admin
        </h2>
        <span v-if="loading" class="loading loading-xs loading-spinner" />
      </div>

      <div v-if="error" class="alert alert-warning text-sm">
        {{ error }}
      </div>

      <!-- Vidljivo samo owner/mod (AC) -->
      <div v-if="canManage" class="grid sm:grid-cols-3 gap-2">
        <button class="btn btn-sm" @click="goMembers">
          👥 Članovi
        </button>
        <button class="btn btn-sm" @click="goEvents">
          📅 Događaji
        </button>
        <button class="btn btn-sm" @click="goApprovals">
          ✅ Odobrenja
        </button>
      </div>

      <div v-else class="text-sm opacity-70">
        Nema admin privilegija za ovu grupu.
      </div>

      <div v-if="roles?.length" class="text-xs opacity-60 mt-2">
        Tvoje role: {{ roles.join(', ') }}
      </div>
    </div>
  </div>
</template>
