<script setup lang="ts">
useHead({ title: "Changelog — Fishing Crew" });
</script>

<template>
  <div class="max-w-3xl mx-auto p-6">
    <h1 class="text-2xl font-semibold mb-4">
      Changelog
    </h1>
    <ul class="timeline timeline-vertical">
      <li>
        <div class="timeline-start">
          v0.9.7
        </div>
        <div class="timeline-middle">
          <Icon name="tabler:fish" />
        </div>
        <div class="timeline-end mb-4">
          <p><strong>Novo:</strong> Dodate fotografije po događajima</p>
          <p><strong>Ispravljeno:</strong> Sitni vizuelni bagovi na mobilnim uređajima</p>
        </div>
      </li>
      <li>
        <div class="timeline-start">
          v0.9.6
        </div>
        <div class="timeline-middle">
          <Icon name="tabler:anchor" />
        </div>
        <div class="timeline-end mb-4">
          <p><strong>Dodato:</strong> Prikaz RSVP statusa na početnoj</p>
        </div>
      </li>
    </ul>
  </div>
</template>
