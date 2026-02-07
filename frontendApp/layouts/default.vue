<template>
  <div>
    <AppMenu />
    <main class="min-h-[calc(100vh-4rem)] bg-base-200 text-base-content">
      <slot />
    </main>
    <AppFooter />
  </div>
</template>
