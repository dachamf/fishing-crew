<script setup lang="ts">
import { ref } from 'vue'
import { useHead } from '#imports'

useHead({
  title: 'Registracija — Fishing Crew',
  meta: [
    { name: 'description', content: 'Kreirajte nalog da pratite ulove i organizujete pecaroške događaje sa ekipom.' }
  ]
})

const auth = useAuth()
const router = useRouter()

const name = ref('')
const email = ref('')
const password = ref('')
const password_confirmation = ref('')
const showPass = ref(false)
const busy = ref(false)
const formErr = ref<string | null>(null)

async function submit() {
  if (busy.value) return
  busy.value = true
  formErr.value = null
  try {
    // pretpostavka: auth.register(name,email,password,password_confirmation)
    await auth.register(
      name.value.trim(),
      email.value.trim(),
      password.value,
      password_confirmation.value
    )
    // nakon registracije često ide verifikacija email-a → preusmeri na /verify ili /login
    await router.push('/verify')
  } catch (e: any) {
    formErr.value =
      e?.response?.data?.errors
        ? Object.values(e.response.data.errors).flat().join(' ')
        : (e?.response?.data?.message || 'Greška pri registraciji.');
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-base-200 relative overflow-hidden">
    <!-- blagi gradient/mesh za bolji kontrast u dark temi -->
    <div
      class="pointer-events-none absolute inset-0 opacity-50"
      aria-hidden="true"
      style="background:
        radial-gradient(60rem 60rem at 10% -10%, hsl(var(--p)/.12) 0%, transparent 60%),
        radial-gradient(40rem 40rem at 110% 10%, hsl(var(--s)/.12) 0%, transparent 60%)"
    />

    <div class="container mx-auto px-4 py-10 flex items-center justify-center">
      <div
        class="w-full max-w-md card bg-base-100/70 supports-[backdrop-filter]:backdrop-blur
               shadow-xl ring-1 ring-base-content/10"
      >
        <div class="card-body">
          <h1 class="text-2xl font-bold text-base-content/90">Registracija</h1>
          <p class="text-base-content/60 text-sm">
            Kreiraj nalog i pridruži se ekipi 🎣
          </p>

          <form
            class="mt-6 space-y-4"
            novalidate
            :aria-busy="busy"
            @submit.prevent="submit"
          >
            <p v-if="formErr" class="alert alert-error text-sm mb-3" role="alert" aria-live="assertive">
              {{ formErr }}
            </p>

            <label class="form-control w-full">
              <div class="label">
                <span class="label-text text-base-content/80">Ime i prezime (nadimak)</span>
              </div>
              <input
                v-model="name"
                type="text"
                autocomplete="name"
                placeholder="Mika Ribar"
                class="input input-bordered w-full placeholder:text-base-content/60"
                required
                aria-label="Ime i prezime"
              />
            </label>

            <label class="form-control w-full">
              <div class="label">
                <span class="label-text text-base-content/80">Email</span>
              </div>
              <input
                v-model="email"
                type="email"
                autocomplete="email"
                placeholder="you@example.com"
                class="input input-bordered w-full placeholder:text-base-content/60"
                required
                aria-label="Email adresa"
              />
            </label>

            <label class="form-control w-full">
              <div class="label">
                <span class="label-text text-base-content/80">Lozinka</span>
                <button
                  type="button"
                  class="link link-hover text-sm text-base-content/60"
                  @click="showPass = !showPass"
                >
                  {{ showPass ? 'Sakrij' : 'Prikaži' }}
                </button>
              </div>
              <input
                v-model="password"
                :type="showPass ? 'text' : 'password'"
                autocomplete="new-password"
                placeholder="••••••••"
                class="input input-bordered w-full placeholder:text-base-content/60"
                required
                aria-label="Lozinka"
                minlength="8"
              />
            </label>

            <label class="form-control w-full">
              <div class="label">
                <span class="label-text text-base-content/80">Potvrdi lozinku</span>
              </div>
              <input
                v-model="password_confirmation"
                :type="showPass ? 'text' : 'password'"
                autocomplete="new-password"
                placeholder="••••••••"
                class="input input-bordered w-full placeholder:text-base-content/60"
                required
                minlength="8"
                aria-label="Potvrda lozinke"
                @keyup.enter="submit"
              />
            </label>

            <button
              type="submit"
              class="btn btn-primary w-full mt-4"
              :disabled="busy"
              :aria-busy="busy"
              aria-label="Potvrdi registraciju"
            >
              <span v-if="busy" class="loading loading-spinner loading-sm mr-2" aria-hidden="true" />
              <span>Kreiraj nalog</span>
            </button>

            <div class="text-sm mt-2 text-center">
              Već imaš nalog?
              <NuxtLink to="/login" class="link link-primary">Prijavi se</NuxtLink>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>
