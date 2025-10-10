<script setup lang="ts">
useHead({ title: "Status sistema — Fishing Crew" });
</script>

<template>
  <div class="max-w-2xl mx-auto p-6 space-y-4">
    <h1 class="text-2xl font-semibold">
      Status sistema
    </h1>
    <p class="opacity-70">
      Svi servisi rade normalno ✅
    </p>
    <ul class="list-disc pl-6 text-sm opacity-80">
      <li>API: <span class="text-green-600">Online</span></li>
      <li>Baza podataka: <span class="text-green-600">Online</span></li>
      <li>Storage (S3): <span class="text-green-600">Online</span></li>
    </ul>
  </div>
</template>
