<script setup lang="ts">
import { useHead } from "#imports";
import axios from "axios";
import { ref } from "vue";

definePageMeta({
  public: true,
  alias: ["/forgot-password"],
});

useHead({
  title: "Zaboravljena lozinka — Fishing Crew",
  meta: [{ name: "description", content: "Resetujte lozinku za Fishing Crew nalog." }],
});

const { $api } = useNuxtApp() as any;
const { success, error } = useToast();

const email = ref("");
const busy = ref(false);
const sent = ref(false);
const formErr = ref<string | null>(null);

async function submit() {
  if (busy.value)
    return;
  busy.value = true;
  formErr.value = null;
  try {
    await $api.post("/auth/forgot-password", { email: email.value.trim() });
    sent.value = true;
    success("Poslali smo link za reset lozinke.");
  }
  catch (e: unknown) {
    let msg = "Greška pri slanju zahteva.";

    if (axios.isAxiosError(e)) {
      const data = e.response?.data as any;
      const serverMsg = data?.message;

      if (typeof serverMsg === "string" && serverMsg.trim().length > 0) {
        msg = serverMsg;
      }
      else if (e.message.trim().length > 0) {
        // npr. Network Error, timeout...
        msg = e.message;
      }
    }
    else if (e instanceof Error && e.message.trim().length > 0) {
      msg = e.message;
    }

    formErr.value = msg;
    error(msg);
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
            Zaboravljena lozinka
          </h1>
          <p class="text-base-content/60 text-sm">
            Unesi email i poslaćemo ti link za reset.
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
            <p v-if="sent" class="alert alert-success text-sm mb-3">
              Link za reset lozinke je poslat na email.
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
              Pošalji link
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
