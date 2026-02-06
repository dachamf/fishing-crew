<script setup lang="ts">
import { useHead } from "#imports";
import { ref } from "vue";

definePageMeta({ public: true });

useHead({
  title: "Reset lozinke — Fishing Crew",
  meta: [{ name: "description", content: "Postavite novu lozinku za Fishing Crew nalog." }],
});

const { $api } = useNuxtApp() as any;
const { success, error } = useToast();
const router = useRouter();
const route = useRoute();

const token = ref((route.query.token as string) || "");
const email = ref((route.query.email as string) || "");
const password = ref("");
const passwordConfirmation = ref("");
const busy = ref(false);
const formErr = ref<string | null>(null);

async function submit() {
  if (busy.value)
    return;
  busy.value = true;
  formErr.value = null;
  try {
    await $api.post("/auth/reset-password", {
      token: token.value,
      email: email.value.trim(),
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    });
    success("Lozinka je uspešno resetovana.");
    await router.push("/login");
  }
  catch (e: any) {
    formErr.value = e?.response?.data?.message || "Greška pri resetovanju lozinke.";
    error(formErr.value);
  }
  finally {
    busy.value = false;
  }
}
</script>

<template>
  <div class="min-h-screen bg-base-200 relative overflow-hidden">
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
            Reset lozinke
          </h1>
          <p class="text-base-content/60 text-sm">
            Unesi novu lozinku.
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
                <span class="label-text text-base-content/80">Nova lozinka</span>
              </div>
              <input
                v-model="password"
                type="password"
                autocomplete="new-password"
                placeholder="••••••••"
                class="input input-bordered w-full placeholder:text-base-content/60"
                required
                aria-label="Nova lozinka"
              >
            </label>

            <label class="form-control w-full">
              <div class="label">
                <span class="label-text text-base-content/80">Potvrdi lozinku</span>
              </div>
              <input
                v-model="passwordConfirmation"
                type="password"
                autocomplete="new-password"
                placeholder="••••••••"
                class="input input-bordered w-full placeholder:text-base-content/60"
                required
                aria-label="Potvrdi lozinku"
              >
            </label>

            <button
              type="submit"
              class="btn btn-primary w-full mt-2"
              :class="{ 'btn-disabled loading': busy }"
              :disabled="busy"
            >
              <span
                v-if="busy"
                class="loading loading-spinner loading-sm mr-2"
                aria-hidden="true"
              />
              Resetuj lozinku
            </button>

            <div class="text-sm mt-2">
              <NuxtLink to="/login" class="link link-primary">
                Nazad na prijavu
              </NuxtLink>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>
