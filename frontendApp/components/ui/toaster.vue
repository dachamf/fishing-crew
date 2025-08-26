<script setup lang="ts">
const { toasts, dismiss } = useToast();
function cls(t: string) {
  return t === "success"
    ? "alert-success"
    : t === "error"
      ? "alert-error"
      : t === "warning" ? "alert-warning" : "alert-info";
}
</script>

<template>
  <!-- top-right toasts (daisyUI) -->
  <div class="toast toast-top toast-end z-[9999]">
    <div
      v-for="t in toasts"
      :key="t.id"
      class="alert shadow"
      :class="cls(t.type)"
    >
      <span class="mr-3">{{ t.message }}</span>
      <button class="btn btn-ghost btn-xs" @click="dismiss(t.id)">
        ✕
      </button>
    </div>
  </div>
</template>
