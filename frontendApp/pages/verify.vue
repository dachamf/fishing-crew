<script setup lang="ts">
// dostupna i bez logina (klik iz maila može da dođe npr. iz ekst. browsera)
definePageMeta({ public: true })

const route = useRoute()
const status = computed(() => (route.query.status as string) || '')

const { token } = useAuth()
const { $api } = useNuxtApp() as any
const sent = ref(false)
const err  = ref<string|null>(null)
const pending = ref(false)

async function resend() {
  if (!token.value) { err.value = 'Uloguj se da pošalješ novi link.'; return }
  pending.value = true; err.value = null
  try {
    await $api.post('/auth/email/verification-notification')
    sent.value = true
  } catch (e:any) {
    err.value = e?.response?.data?.message || 'Greška pri slanju'
  } finally { pending.value = false }
}
</script>

<template>
  <div class="max-w-lg mx-auto p-6 space-y-4">
    <h1 class="text-2xl font-semibold">Verifikacija email adrese</h1>
    <p v-if="status==='success'">Hvala! Tvoja email adresa je uspešno verifikovana. Možeš nastaviti sa aplikacijom.</p>
    <p v-else-if="status==='already-verified'">Email je već verifikovan.</p>
    <p v-else>Proveri inbox i klikni na link za potvrdu.</p>

    <div class="space-x-2">
      <button class="btn btn-primary" :disabled="pending" @click="resend">
        {{ pending ? 'Šaljem...' : 'Pošalji ponovo' }}
      </button>
      <NuxtLink class="btn" to="/">Idi na početnu</NuxtLink>
    </div>

    <p v-if="sent" class="text-success">Link je poslat.</p>
    <p v-if="err" class="text-error">{{ err }}</p>
  </div>
</template>
