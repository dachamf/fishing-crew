<script lang="ts" setup>
type Status = "pending" | "approved" | "rejected" | "open" | "closed" | string;

const props = withDefaults(
  defineProps<{
    status: Status;
    title?: string;
    class?: string;
  }>(),
  { title: "", class: "" },
);

const label = computed(() => String(props.status || "").replaceAll("_", " "));
const cls = computed(() => ({
  "badge": true,
  "capitalize": true,
  "badge-warning": ["pending", "open"].includes(String(props.status)),
  "badge-success": ["approved"].includes(String(props.status)),
  "badge-error": ["rejected"].includes(String(props.status)),
  "badge-outline": !["pending", "approved", "rejected", "open", "closed"].includes(
    String(props.status),
  ),
  [props.class!]: !!props.class,
}));
</script>

<template>
  <span :class="cls" :title="title">{{ label }}</span>
</template>
