<script setup lang="ts">
import { toErrorMessage } from '~/utils/http'

const { $api } = useNuxtApp() as any
const auth = useAuth()
const { profile, loadMe, avatarBuster } = useProfile()
const { success, error } = useToast()

const route = useRoute()
const router = useRouter()

// Mobile dropdown toggle
const open = ref(false)

// Sticky shadow on scroll
const scrolled = ref(false)
function onScroll() { scrolled.value = window.scrollY > 4 }
onMounted(async () => {
  if (auth.user.value && !profile.value) await loadMe()
  if (process.client) {
    onScroll()
    window.addEventListener('scroll', onScroll, { passive: true })
  }
})
onUnmounted(() => { if (process.client) window.removeEventListener('scroll', onScroll) })

// Aktivni link helper
function isActive(to: string, exact = false) {
  return exact ? route.path === to : route.path.startsWith(to)
}

// Navigacija
const links = computed(() => [
  { to: '/', label: 'Početna', exact: true },
  { to: '/events', label: 'Događaji' },
  { to: '/events/new', label: 'Novi događaj', auth: true },
  { to: '/catches', label: 'Ulov' },
  { to: '/leaderboard', label: 'Leaderboard' },
])
const visibleLinks = computed(() =>
  links.value.filter(l => !l.auth || !!auth.user.value)
)

// Avatar URL sa cache-busterom
const placeholder = '/icons/icon-64.png'
const avatarUrl = computed(() => {
  const url = profile.value?.avatar_url
  if (!url) return placeholder
  const sep = url.includes('?') ? '&' : '?'
  return `${url}${sep}v=${avatarBuster.value}`
})

// Verifikacija
const isVerified = computed(() => !!auth.user.value?.email_verified_at)
const sendingVerify = ref(false)
async function resendVerification() {
  if (!auth.user.value) return
  try {
    sendingVerify.value = true
    await $api.post('/auth/email/verification-notification')
    success('Poslali smo verifikacioni email ✅')
  } catch (e: any) {
    error(toErrorMessage(e))
  } finally {
    sendingVerify.value = false
  }
}

// Logout + redirect
async function doLogout() {
  try { await auth.logout() }
  finally { open.value = false; router.push('/login') }
}

// Zatvori dropdown na promenu rute
watch(() => route.fullPath, () => (open.value = false))
</script>

<template>
  <!-- sticky + blur + poluprozirna pozadina -->
  <div
    class="navbar sticky top-0 z-50 backdrop-blur bg-base-200/70 transition-shadow"
    :class="scrolled ? 'shadow-md border-b border-base-300' : 'shadow-sm'"
  >
    <!-- LEFT -->
    <div class="navbar-start">
      <!-- Mobile menu -->
      <div class="dropdown">
        <button class="btn btn-ghost lg:hidden" aria-label="Open main menu" @click="open = !open">
          <svg
xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
               viewBox="0 0 24 24" stroke="currentColor">
            <path
stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>
        <ul
          v-show="open"
          class="menu menu-sm dropdown-content bg-base-100 rounded-box z-50 mt-3 w-56 p-2 shadow"
        >
          <li v-for="l in visibleLinks" :key="l.to">
            <NuxtLink :to="l.to" :class="[{ 'active font-semibold': isActive(l.to, l.exact) }]">
              {{ l.label }}
            </NuxtLink>
          </li>

          <li v-if="!auth.user.value" class="mt-2">
            <NuxtLink class="btn btn-primary btn-sm" to="/login">Prijava</NuxtLink>
          </li>
          <li v-if="!auth.user.value">
            <NuxtLink class="btn btn-ghost btn-sm" to="/register">Registracija</NuxtLink>
          </li>

          <li v-if="auth.user.value && !isVerified" class="mt-2">
            <button
class="btn btn-warning btn-sm w-full"
                    :disabled="sendingVerify"
                    @click="resendVerification">
              {{ sendingVerify ? 'Slanje...' : 'Ponovo pošalji verifikaciju' }}
            </button>
          </li>

          <li class="mt-2"><ThemeSwitch /></li>
        </ul>
      </div>

      <!-- Brand -->
      <NuxtLink to="/" class="btn btn-ghost text-xl font-bold">
        Fishing Crew
        <img src="/logo.png" alt="logo" width="128" height="32" class="inline-block align-text-bottom ml-2" />
      </NuxtLink>
    </div>

    <!-- CENTER (desktop nav) -->
    <div class="navbar-center hidden lg:flex">
      <ul class="menu menu-horizontal px-1">
        <li v-for="l in visibleLinks" :key="l.to">
          <NuxtLink
            :to="l.to"
            :class="['rounded-btn', { 'active font-semibold': isActive(l.to, l.exact) }]"
          >
            {{ l.label }}
          </NuxtLink>
        </li>
      </ul>
    </div>

    <!-- RIGHT -->
    <div class="navbar-end gap-3">
      <!-- Badge “Neverifikovano” (desktop) -->
      <span v-if="auth.user.value && !isVerified" class="badge badge-warning hidden md:inline-flex">
        Neverifikovano
      </span>

      <ThemeSwitch />
      <!-- Not logged in -->
      <div v-if="!auth.user.value" class="hidden lg:flex gap-2">
        <NuxtLink class="btn btn-primary btn-sm" to="/login">Prijava</NuxtLink>
        <NuxtLink class="btn btn-ghost btn-sm" to="/register">Registracija</NuxtLink>
      </div>

      <!-- Logged in -->
      <div v-else class="dropdown dropdown-end">
        <button class="btn btn-ghost btn-circle" aria-label="Profile menu">
          <div class="indicator">
            <!-- mala tačkica kad nije verifikovan -->
            <span v-if="!isVerified" class="indicator-item badge badge-warning badge-xs">!</span>

            <!-- avatar wrapper koji garantuje krug -->
            <div class="avatar">
              <div class="w-10 rounded-full ring ring-offset-2 ring-base-300 overflow-hidden">
                <img
                  :key="avatarUrl"
                  :src="avatarUrl"
                  class="w-full h-full object-cover"
                  alt="avatar"
                />
              </div>
            </div>
          </div>
        </button>

        <ul class="menu menu-sm dropdown-content bg-base-100 rounded-box z-50 mt-3 w-56 p-2 shadow">
          <li class="px-3 py-2">
            <div class="text-sm opacity-70">Ulogovan</div>
            <div class="font-medium truncate">
              {{ profile?.display_name || auth.user.value?.name }}
            </div>
            <div v-if="!isVerified" class="mt-2">
              <button
class="btn btn-warning btn-xs w-full"
                      :disabled="sendingVerify"
                      @click="resendVerification">
                {{ sendingVerify ? 'Slanje…' : 'Verifikuj email' }}
              </button>
            </div>
          </li>
          <li><NuxtLink to="/profile">Profil</NuxtLink></li>
          <li><NuxtLink to="/catches">Moji ulovi</NuxtLink></li>
          <li><NuxtLink to="/events">Događaji</NuxtLink></li>
          <li><button @click="doLogout">Odjava</button></li>
        </ul>
      </div>
    </div>
  </div>
</template>

<style scoped>
@reference "tailwindcss";
.active { color: oklch(var(--p)); }
</style>
