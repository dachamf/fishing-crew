<script setup lang="ts">
type RSVP = "yes" | "no" | "undecided" | null;

const props = defineProps<{ rsvp: RSVP }>();

function badge(r: RSVP) {
  switch (r) {
    case "yes":
      return { text: "idem", cls: "badge-success" };
    case "no":
      return { text: "ne idem", cls: "badge-ghost" };
    case "undecided":
      return { text: "neodlučan", cls: "badge-warning" };
    default:
      return { text: "—", cls: "badge-ghost" };
  }
}
</script>

<template>
  <span class="badge" :class="badge(props.rsvp).cls">
    {{ badge(props.rsvp).text }}
  </span>
</template>
