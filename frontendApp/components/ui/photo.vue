<script lang="ts" setup>
defineOptions({ name: "UiPhoto" });

const props = withDefaults(
  defineProps<{
    /** Alternativa: prosledi ceo objekat { url, urls } */
    photo?: PhotoLike | null;
    /** Ili prosledi posebno src + urls */
    src?: string | null;
    urls?: VariantMap | null;

    alt?: string;
    /** npr. "(max-width: 768px) 90vw, 33vw" */
    sizes?: string;
    loading?: "lazy" | "eager";
    decoding?: "async" | "sync" | "auto";
    /** HTML attribute: auto | high | low */
    fetchPriority?: "auto" | "high" | "low";
  }>(),
  {
    photo: null,
    src: null,
    urls: null,
    alt: "",
    sizes: "(max-width: 768px) 90vw, 33vw",
    loading: "lazy",
    decoding: "async",
    fetchPriority: "auto",
  },
);
type VariantMap = Partial<Record<"sm" | "md" | "lg" | string, string>>;
type PhotoLike = { url?: string | null; urls?: VariantMap | null };

// Normalizacija izvora
const normSrc = computed(() => props.src ?? props.photo?.url ?? "");
const normUrls = computed<VariantMap | null>(() => props.urls ?? props.photo?.urls ?? null);

// Najbolji fallback src (ako nema srcset-a)
const bestSrc = computed(
  () => normUrls.value?.md || normUrls.value?.lg || normUrls.value?.sm || normSrc.value || "",
);

// srcset iz varijanti
const srcsetVal = computed(() => {
  const u = normUrls.value || {};
  const parts: string[] = [];
  if (u.sm)
    parts.push(`${u.sm} 320w`);
  if (u.md)
    parts.push(`${u.md} 800w`);
  if (u.lg)
    parts.push(`${u.lg} 1600w`);
  return parts.length ? parts.join(", ") : undefined;
});
</script>

<template>
  <img
    :src="bestSrc"
    :srcset="srcsetVal"
    :sizes="srcsetVal ? sizes : undefined"
    :alt="alt"
    :loading="loading"
    :decoding="decoding"
    :fetchpriority="fetchPriority"
    class="block w-full h-full object-cover"
  >
</template>
