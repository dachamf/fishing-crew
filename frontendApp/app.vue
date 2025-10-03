<script setup lang="ts">
useHead({
  link: [
    { rel: "manifest", href: "/site.webmanifest" },
    { rel: "icon", href: "/favicons/favicon.ico" },
    { rel: "apple-touch-icon", href: "/icons/icon-180.png" },
  ],
  meta: [{ name: "theme-color", content: "#0b1220" }],
});
</script>

<template>
  <a
    href="#main"
    class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 bg-base-100 rounded px-3 py-2 shadow"
  >
    Preskoči na sadržaj
  </a>
  <div>
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>
  </div>
</template>
