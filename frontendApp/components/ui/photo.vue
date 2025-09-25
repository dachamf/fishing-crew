<script setup lang="ts">
import type { ImgHTMLAttributes } from "vue";

import { computed, ref } from "vue";

type SizeKey = "sm" | "md" | "lg";

const props = withDefaults(
  defineProps<{
    photo: {
      id: number;
      url?: string;
      urls?: Record<string, string>;
      width?: number | null;
      height?: number | null;
    };
    size?: SizeKey;
    alt?: string;
    sizes?: string;
    aspect?: string | number;
    fit?: "cover" | "contain" | "fill" | "none" | "scale-down";
    rounded?: string;
    class?: string;
    eager?: boolean;
    priority?: boolean;
    skeleton?: boolean;
    blur?: boolean;
  }>(),
  {
    size: "md",
    alt: "",
    sizes: "(max-width: 640px) 320px, (max-width: 1024px) 800px, 1600px",
    fit: "cover",
    rounded: "rounded-2xl",
    eager: false,
    priority: false,
    skeleton: true,
    blur: true,
  },
);

const loaded = ref(false);
const { build, srcset } = usePhotoUrl();

const urlSm = computed(() => props.photo.urls?.sm ?? build(props.photo, "sm"));
const urlMd = computed(
  () => props.photo.urls?.[props.size || "md"] ?? build(props.photo, props.size),
);

const srcSet = computed(() => {
  if (props.photo.urls) {
    const u = props.photo.urls;
    const items: string[] = [];
    if (u.sm)
      items.push(`${u.sm} 320w`);
    if (u.md)
      items.push(`${u.md} 800w`);
    if (u.lg)
      items.push(`${u.lg} 1600w`);
    return items.join(", ");
  }
  return srcset(props.photo, ["sm", "md", "lg"] as unknown as SizeKey[]);
});

const containerStyle = computed(() => {
  if (props.photo.width && props.photo.height) {
    const ratio = props.photo.width / props.photo.height;
    return { aspectRatio: String(ratio) };
  }
  if (props.aspect) {
    return { aspectRatio: String(props.aspect) };
  }
  return {};
});

// Tip-safe img atribute (bez generičkog stringa)
const imgAttrs = computed<Pick<ImgHTMLAttributes, "alt" | "loading" | "decoding">>(() => ({
  alt: props.alt,
  loading: (props.eager ? "eager" : "lazy") as "eager" | "lazy",
  decoding: (props.eager ? "sync" : "async") as "auto" | "async" | "sync",
}));

// Stilovi za blur LQIP pozadinu i skeleton – čiste computed vrednosti, bez pipes
const blurStyle = computed(() => ({
  backgroundImage: `url('${urlSm.value}')`,
  backgroundSize: "cover",
  backgroundPosition: "center",
  filter: loaded.value ? "blur(0px)" : "blur(12px)",
  opacity: loaded.value ? 0 : 1,
}));
</script>

<template>
  <div
    class="relative overflow-hidden bg-base-2 00"
    :class="[rounded, props.class]"
    :style="containerStyle"
  >
    <!-- LQIP blur background -->
    <div
      v-if="blur"
      class="absolute inset-0 transition-opacity duration-300 will-change-[opacity]"
      :style="blurStyle"
      aria-hidden="true"
    />

    <!-- Skeleton -->
    <div
      v-if="skeleton && !loaded"
      class="absolute inset-0 animate-pulse bg-base-300/60"
      aria-hidden="true"
    />

    <!-- Main image -->
    <img
      :src="urlMd"
      :srcset="srcSet"
      :sizes="sizes"
      :style="{ objectFit: fit }"
      class="block w-full h-full object-center transition-opacity duration-300"
      :class="[{ 'opacity-0': !loaded, 'opacity-100': loaded }]"
      v-bind="imgAttrs"
      :fetchpriority="(priority ? 'high' : 'auto') as any"
      @load="loaded = true"
    >
  </div>
</template>
