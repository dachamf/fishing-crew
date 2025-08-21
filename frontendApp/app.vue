<script setup lang="ts">
useHead({
  link: [
    { rel: 'manifest', href: '/site.webmanifest' },
    { rel: 'icon', href: '/favicons/favicon.ico' },
    { rel: 'apple-touch-icon', href: '/icons/icon-180.png' }
  ],
  meta: [{ name: 'theme-color', content: '#0b1220' }]
})

</script>

<template>
  <div>
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>
  </div>
</template>
