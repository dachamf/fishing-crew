<script lang="ts" setup>
type PhotoUrls = Partial<Record<"sm" | "md" | "lg" | string, string>>;

const props = withDefaults(
  defineProps<{
    /** Fallback/primary src ako nema urls */
    src?: string | null;
    /** Varijante vraćene sa BE: { sm, md, lg } */
    urls?: PhotoUrls | null;
    alt?: string;
    /** npr. "(max-width: 768px) 90vw, 50vw" */
    sizes?: string;
    loading?: "lazy" | "eager";
    decoding?: "async" | "sync" | "auto";
    /** HTML attribute: auto | high | low */
    fetchPriority?: "auto" | "high" | "low";
  }>(),
  {
    src: null,
    urls: null,
    alt: "",
    sizes: "(max-width: 768px) 90vw, 33vw",
    loading: "lazy",
    decoding: "async",
    fetchPriority: "auto",
  },
);

const bestSrc = computed(
  () => props.urls?.md || props.urls?.lg || props.urls?.sm || props.src || "",
);
const srcsetVal = computed(() => {
  const u = props.urls || {};
  const parts: string[] = [];
  if (u.sm)
    parts.push(`${u.sm} 320w`);
  if (u.md)
    parts.push(`${u.md} 800w`);
  if (u.lg)
    parts.push(`${u.lg} 1600w`);
  return parts.join(", ");
});
</script>

<template>
  <img
    :src="bestSrc"
    :srcset="srcsetVal || undefined"
    :sizes="srcsetVal ? sizes : undefined"
    :alt="alt"
    :loading="loading"
    :decoding="decoding"
    :fetchpriority="fetchPriority"
    class="block w-full h-full object-cover"
  >
</template>
