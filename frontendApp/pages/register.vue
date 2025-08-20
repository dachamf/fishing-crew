<script setup lang="ts">
definePageMeta({ public: true, middleware: 'guest' }) // guest middleware iz ranije poruke
const { register } = useAuth()
const route = useRoute()
const name = ref('');
const email = ref('');
const password = ref('');
const password_confirmation = ref('');

const error = ref<string|null>(null);
const pending = ref(false)

async function onSubmit() {
  error.value = null; pending.value = true
  try {
    await register(name.value, email.value, password.value, password_confirmation.value)
    return navigateTo((route.query.next as string) || '/')
  } catch (e: any) {
    // Laravel validation shape
    const msg = e?.response?.data?.message || 'Registration failed'
    const errs = e?.response?.data?.errors
    error.value = errs ? Object.values(errs).flat().join('\n') : msg
  } finally { pending.value = false }
}
</script>

<template>
  <div class="relative flex flex-col items-center justify-center h-screen overflow-hidden">
    <div class="w-full p-6 bg-white border-t-4 border-gray-600 rounded-md shadow-md border-top lg:max-w-lg">
      <h1 class="text-3xl font-semibold text-center text-gray-700">
        Fishermen Crew
      </h1>
      <form class="space-y-4" @submit.prevent="onSubmit">
        <div>
          <label class="label">
            <span class="label-text">Ime</span>
          </label>
          <input type="text" placeholder="Ime" class="w-full input input-bordered" v-model="name" />
        </div>
        <div>
          <label class="label">
            <span class="label-text">Email</span>
          </label>
          <input type="text" placeholder="Email Adresa" class="w-full input input-bordered" v-model="email" />
        </div>
        <div>
          <label class="label">
            <span class="label-text">Lozinka</span>
          </label>
          <input type="password" placeholder="Lozinka" class="w-full input input-bordered" v-model="password" />
        </div>
        <div>
          <label class="label">
            <span class="label-text">Potvrdi lozinku</span>
          </label>
          <input type="password" placeholder="Potvrdi lozinku" class="w-full input input-bordered" v-model="password_confirmation" />
        </div>
        <div>
          <button class="btn btn-block">{{ pending ? 'Kreiram...' : 'Registruj se' }}</button>
        </div>
        <span>Već imate nalog?
                    <NuxtLink to="login" class="text-blue-600 hover:text-blue-800 hover:underline">Uloguj se</NuxtLink></span>
      </form>
      <div class="flex items-center w-full my-4">
        <hr class="w-full" />
        <p class="px-3 ">ILI</p>
        <hr class="w-full" />
      </div>
      <div class="my-6 space-y-2">
        <button aria-label="Login with Google" type="button"
                class="flex items-center justify-center w-full p-2 space-x-4 border rounded-md focus:ring-2 focus:ring-offset-1 focus:ring-gray-400">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" class="w-5 h-5 fill-current">
            <path
              d="M16.318 13.714v5.484h9.078c-0.37 2.354-2.745 6.901-9.078 6.901-5.458 0-9.917-4.521-9.917-10.099s4.458-10.099 9.917-10.099c3.109 0 5.193 1.318 6.38 2.464l4.339-4.182c-2.786-2.599-6.396-4.182-10.719-4.182-8.844 0-16 7.151-16 16s7.156 16 16 16c9.234 0 15.365-6.49 15.365-15.635 0-1.052-0.115-1.854-0.255-2.651z">
            </path>
          </svg>
          <p>Uloguj se Googlom</p>
        </button>
      </div>
    </div>
  </div>
</template>
