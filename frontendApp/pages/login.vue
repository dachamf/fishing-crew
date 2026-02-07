<script setup lang="ts">
import { useHead } from "#imports";
import { ref } from "vue";

useHead({
  title: "Prijava — Fishing Crew",
  meta: [
    {
      name: "description",
      content: "Prijavite se u Fishing Crew da planirate događaje i pratite ulove sa ekipom.",
    },
  ],
});

const auth = useAuth();
const router = useRouter();

const email = ref("");
const password = ref("");
const showPass = ref(false);
const remember = ref(true); // ✅ Remember me
const busy = ref(false); // ✅ busy-state
const formErr = ref<string | null>(null);

async function submit() {
  if (busy.value)
    return;
  busy.value = true;
  formErr.value = null;
  try {
    await auth.login(email.value.trim(), password.value, remember.value);
    await router.push("/");
  }
  catch (e: any) {
    formErr.value = e?.response?.data?.message || "Greška pri prijavi.";
  }
  finally {
    busy.value = false;
  }
}
</script>

<template>
  <div class="min-h-screen bg-base-200 relative overflow-hidden">
    <!-- blagi gradient/mesh za bolji kontrast u dark temi -->
    <div
      class="pointer-events-none absolute inset-0 opacity-50"
      aria-hidden="true"
      style="
        background:
          radial-gradient(60rem 60rem at 10% -10%, hsl(var(--p) / 0.12) 0%, transparent 60%),
          radial-gradient(40rem 40rem at 110% 10%, hsl(var(--s) / 0.12) 0%, transparent 60%);
      "
    />

    <div class="container mx-auto px-4 py-10 flex items-center justify-center">
      <div
        class="w-full max-w-md card bg-base-100/70 supports-[backdrop-filter]:backdrop-blur shadow-xl ring-1 ring-base-content/10"
      >
        <div class="card-body">
          <h1 class="text-2xl font-bold text-base-content/90">
            Prijava
          </h1>
          <p class="text-base-content/60 text-sm">
            Dobrodošao nazad 👋
          </p>

          <form
            class="mt-6 space-y-4"
            :aria-busy="busy"
            @submit.prevent="submit"
          >
            <p
              v-if="formErr"
              class="alert alert-error text-sm mb-3"
              role="alert"
              aria-live="assertive"
            >
              {{ formErr }}
            </p>

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
              >
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
                autocomplete="current-password"
                placeholder="••••••••"
                class="input input-bordered w-full placeholder:text-base-content/60"
                required
                aria-label="Lozinka"
                @keyup.enter="submit"
              >
            </label>

            <!-- ✅ Remember me -->
            <label class="label cursor-pointer mt-3 gap-3">
              <input
                v-model="remember"
                type="checkbox"
                class="checkbox checkbox-primary"
                aria-label="Zapamti me na ovom uređaju"
              >
              <span class="label-text">Zapamti me</span>
            </label>

            <button
              type="submit"
              class="btn btn-primary w-full mt-2"
              :class="{ 'btn-disabled loading': busy }"
              :disabled="busy"
              aria-label="Prijavi se"
              :aria-busy="busy"
            >
              <span
                v-if="busy"
                class="loading loading-spinner loading-sm mr-2"
                aria-hidden="true"
              />
              <span>Prijavi se</span>
            </button>

            <div class="flex items-center justify-between text-sm mt-2">
              <NuxtLink to="/register" class="link link-primary">
                Nemaš nalog? Registruj se
              </NuxtLink>
              <NuxtLink to="/forgotPassword" class="link link-hover text-base-content/60">
                Zaboravljena lozinka
              </NuxtLink>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>
