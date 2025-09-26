<script lang="ts" setup>
// Prihvatamo i undefined vrednosti u urls, jer BE šalje partial varijante.
type StringDict = Record<string, string | undefined>;

export type PhotoLike = {
  id?: number | string;
  url?: string | null;
  urls?: StringDict | null;
  ord?: number | null;
  // dopuštamo dodatna polja
  [k: string]: any;
};

const props = withDefaults(
  defineProps<{
    /** Može da stigne iz BE kao PhotoLite */
    photo?: PhotoLike | null;
    /** Fallback/primary src ako nema urls */
    src?: string | null;
    /** Eksplicitne varijante preko props-a (npr. { sm, md, lg }) */
    urls?: StringDict | null;
    alt?: string;
    /** npr. "(max-width: 768px) 90vw, 50vw" */
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

// Spoji urls iz photo + props.urls
const mergedUrls = computed<StringDict>(() => ({
  ...(props.photo?.urls ?? {}),
  ...(props.urls ?? {}),
}));

function pickFirst(...keys: string[]) {
  for (const k of keys) {
    const v = mergedUrls.value[k];
    if (v)
      return v;
  }
  return undefined;
}

const bestSrc = computed(() => pickFirst("md", "lg", "sm") || props.photo?.url || props.src || "");

const srcsetVal = computed(() => {
  const u = mergedUrls.value;
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
    :srcset="srcsetVal"
    :sizes="srcsetVal ? sizes : undefined"
    :alt="alt"
    :loading="loading"
    :decoding="decoding"
    :fetchpriority="fetchPriority"
    class="block w-full h-full object-cover"
  >
</template>
