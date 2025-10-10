<script setup lang="ts">
useHead({ title: "Kontakt — Fishing Crew" });
</script>

<template>
  <div class="max-w-2xl mx-auto p-6 space-y-4">
    <h1 class="text-2xl font-semibold">
      Kontakt
    </h1>
    <p class="opacity-70">
      Imate pitanje, predlog ili želite da prijavite problem? Pošaljite nam poruku.
    </p>

    <form class="space-y-3">
      <label class="form-control">
        <span class="label-text">Vaše ime</span>
        <input
          type="text"
          class="input input-bordered"
          placeholder="Ime i prezime"
        >
      </label>

      <label class="form-control">
        <span class="label-text">Email</span>
        <input
          type="email"
          class="input input-bordered"
          placeholder="primer@email.com"
        >
      </label>

      <label class="form-control">
        <span class="label-text">Poruka</span>
        <textarea class="textarea textarea-bordered h-24" placeholder="Vaša poruka..." />
      </label>

      <button class="btn btn-primary">
        Pošalji
      </button>
    </form>
  </div>
</template>
