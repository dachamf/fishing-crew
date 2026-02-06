<script lang="ts" setup>
import { toErrorMessage } from "~/utils/http";

const { $api } = useNuxtApp() as any;
const auth = useAuth();
const { profile, loadMe, avatarBuster } = useProfile();
const { success, error } = useToast();

const { unread } = useNotifications();

const { startPolling: startAssignedPolling, fetchOnce: fetchAssignedOnce } = useAssignedPreview();

// +++ Assigned to me bell + preview + counter +++
const { assignedToMe } = useSessionReview();

const route = useRoute();

// Mobile dropdown toggle
const open = ref(false);

const assignedPreview = ref<{ items: any[]; meta: any } | null>(null);
computed(() => assignedPreview.value?.meta?.total ?? assignedPreview.value?.items?.length ?? 0);
async function loadAssignedPreview() {
  if (!auth.user.value) {
    return;
  }
  try {
    const { items, meta } = await assignedToMe(1, 5);
    assignedPreview.value = { items: Array.isArray(items) ? items : [], meta };
  }
  catch {
    assignedPreview.value = { items: [], meta: null };
  }
}

// osveži na mount i kad se promeni ruta ili user
onMounted(loadAssignedPreview);
watch([() => route.fullPath, () => auth.user.value?.id], () => loadAssignedPreview());

// Sticky shadow on scroll
const scrolled = ref(false);

function onScroll() {
  scrolled.value = window.scrollY > 4;
}

onMounted(async () => {
  if (auth.user.value && !profile.value) {
    await loadMe();
  }
  await fetchAssignedOnce();
  startAssignedPolling(60_000);
  if (import.meta.client) {
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
  }
});

onUnmounted(() => {
  if (import.meta.client)
    window.removeEventListener("scroll", onScroll);
});

// Aktivni link helper
function isActive(to: string, exact = false) {
  return exact ? route.path === to : route.path.startsWith(to);
}

// Navigacija
const links = computed(() => [
  { to: "/", label: "Početna", exact: true },
  { to: "/events", label: "Događaji", auth: true },
  { to: "/catches", label: "Ulov", auth: true },
  { to: "/leaderboard", label: "Lista Najboljih", auth: true },
]);
const visibleLinks = computed(() => links.value.filter(l => !l.auth || !!auth.user.value));

// Avatar URL sa cache-busterom
const placeholder = "/icons/icon-64.png";
const avatarUrl = computed(() => {
  const url = profile.value?.avatar_url;
  if (!url)
    return placeholder;
  const sep = url.includes("?") ? "&" : "?";
  return `${url}${sep}v=${avatarBuster.value}`;
});

// Verifikacija
const isVerified = computed(() => !!auth.user.value?.email_verified_at);
const sendingVerify = ref(false);

async function resendVerification() {
  if (!auth.user.value)
    return;
  try {
    sendingVerify.value = true;
    await $api.post("/auth/email/verification-notification");
    success("Poslali smo verifikacioni email ✅");
  }
  catch (e: any) {
    error(toErrorMessage(e));
  }
  finally {
    sendingVerify.value = false;
  }
}

// Logout + redirect
async function doLogout() {
  open.value = false;
  await auth.logoutAndRedirect();
}

// Zatvori dropdown na promenu rute
watch(
  () => route.fullPath,
  () => (open.value = false),
);
</script>

<template>
  <!-- sticky + blur + poluprozirna pozadina -->
  <div
    :class="scrolled ? 'shadow-md border-b border-base-300' : 'shadow-sm'"
    class="navbar sticky top-0 z-50 backdrop-blur bg-base-200/70 transition-shadow"
  >
    <!-- LEFT -->
    <div class="navbar-start">
      <!-- Mobile menu -->
      <div class="dropdown">
        <button
          aria-label="Open main menu"
          class="btn btn-ghost lg:hidden"
          @click="open = !open"
        >
          <svg
            class="h-5 w-5"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            xmlns="http://www.w3.org/2000/svg"
          >
            <path
              d="M4 6h16M4 12h16M4 18h16"
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
            />
          </svg>
        </button>
        <ul
          v-show="open"
          class="menu menu-sm dropdown-content bg-base-100 rounded-box z-50 mt-3 w-56 p-2 shadow"
        >
          <li v-for="l in visibleLinks" :key="l.to">
            <NuxtLink :class="[{ 'active font-semibold': isActive(l.to, l.exact) }]" :to="l.to">
              {{ l.label }}
            </NuxtLink>
          </li>

          <li v-if="!auth.user.value" class="mt-2">
            <NuxtLink class="btn btn-primary btn-sm" to="/login">
              Prijava
            </NuxtLink>
          </li>
          <li v-if="!auth.user.value">
            <NuxtLink class="btn btn-ghost btn-sm" to="/register">
              Registracija
            </NuxtLink>
          </li>

          <li v-if="auth.user.value && !isVerified" class="mt-2">
            <button
              :disabled="sendingVerify"
              class="btn btn-warning btn-sm w-full"
              @click="resendVerification"
            >
              {{ sendingVerify ? 'Slanje...' : 'Ponovo pošalji verifikaciju' }}
            </button>
          </li>

          <li class="mt-2">
            <ClientOnly>
              <UiThemeSwitch />
              <template #fallback>
                <!-- Stub iste visine/širine da nema skakanja layout-a -->
                <span class="btn btn-ghost btn-circle opacity-0" aria-hidden="true" />
              </template>
            </ClientOnly>
          </li>
        </ul>
      </div>

      <!-- Brand -->
      <NuxtLink class="btn btn-ghost text-xl font-bold" to="/">
        Fishing Crew
        <img
          alt="logo"
          class="inline-block align-text-bottom ml-2"
          height="32"
          src="/logo.png"
          width="128"
        >
      </NuxtLink>
    </div>

    <!-- CENTER (desktop nav) -->
    <div class="navbar-center hidden lg:flex">
      <ul class="menu menu-horizontal px-1">
        <li v-for="l in visibleLinks" :key="l.to">
          <NuxtLink
            :class="[{ 'active font-semibold': isActive(l.to, l.exact) }]"
            :to="l.to"
            class="rounded-btn"
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

      <ClientOnly>
        <UiThemeSwitch />
        <template #fallback>
          <!-- Stub iste visine/širine da nema skakanja layout-a -->
          <span class="btn btn-ghost btn-circle opacity-0" aria-hidden="true" />
        </template>
      </ClientOnly>
      <!-- Not logged in -->
      <div v-if="!auth.user.value" class="hidden lg:flex gap-2">
        <NuxtLink class="btn btn-primary btn-sm" to="/login">
          Prijava
        </NuxtLink>
        <NuxtLink class="btn btn-ghost btn-sm" to="/register">
          Registracija
        </NuxtLink>
      </div>

      <div v-else>
        <div class="dropdown dropdown-end">
          <div
            aria-label="Assigned to me"
            class="btn btn-ghost btn-circle"
            role="button"
            tabindex="0"
          >
            <div class="indicator">
              <button class="btn btn-ghost btn-circle" aria-label="Obaveštenja">
                <Icon name="tabler:bell" size="20" />
                <span
                  v-if="unread > 0"
                  class="badge badge-primary badge-sm absolute -right-1 -top-1"
                >{{ unread }}</span>
              </button>
            </div>
          </div>
          <NavAssignedBell />
          <!-- Dropdown sa top 5 sesija -->
          <div
            class="mt-3 dropdown-content w-80 card card-compact bg-base-100 shadow z-50"
            tabindex="0"
          >
            <div class="card-body">
              <div class="flex items-center justify-between">
                <h3 class="card-title text-base">
                  Za moju odluku
                </h3>
                <NuxtLink class="link link-primary text-sm" to="/sessions/assigned">
                  Vidi sve
                </NuxtLink>
              </div>

              <ul v-if="(assignedPreview?.items?.length || 0) > 0" class="mt-1 space-y-2">
                <li
                  v-for="s in assignedPreview?.items || []"
                  :key="s.id"
                  class="flex items-start justify-between gap-3"
                >
                  <div class="min-w-0">
                    <NuxtLink
                      :title="s.title || `Sesija #${s.id}`"
                      :to="`/sessions/${s.id}`"
                      class="font-medium text-sm hover:underline truncate block"
                    >
                      {{ s.title || `Sesija #${s.id}` }}
                    </NuxtLink>
                    <div class="text-xs opacity-70">
                      Počela:
                      {{ s.started_at ? new Date(s.started_at).toLocaleString('sr-RS') : '—' }} •
                      Ulova: {{ s.catches_count ?? '—' }}
                    </div>
                  </div>
                  <NuxtLink :to="`/sessions/${s.id}`" class="btn btn-ghost btn-xs">
                    Otvori
                  </NuxtLink>
                </li>
              </ul>

              <div v-else class="opacity-70 text-sm">
                Nema sesija koje čekaju tvoju odluku.
              </div>

              <div class="pt-1">
                <NuxtLink class="btn btn-primary btn-sm w-full" to="/sessions/assigned">
                  Otvori listu zadataka
                </NuxtLink>
              </div>
            </div>
          </div>
        </div>
        <!-- Logged in -->
        <div class="dropdown dropdown-end">
          <button aria-label="Profile menu" class="btn btn-ghost btn-circle">
            <div class="indicator">
              <!-- mala tačkica kad nije verifikovan -->
              <span v-if="!isVerified" class="indicator-item badge badge-warning badge-xs">!</span>

              <!-- avatar wrapper koji garantuje krug -->
              <div class="avatar">
                <div class="w-10 rounded-full ring ring-offset-2 ring-base-300 overflow-hidden">
                  <img
                    :key="avatarUrl"
                    :src="avatarUrl"
                    alt="avatar"
                    class="w-full h-full object-cover"
                  >
                </div>
              </div>
            </div>
          </button>

          <ul
            class="menu menu-sm dropdown-content bg-base-100 rounded-box z-50 mt-3 w-56 p-2 shadow"
          >
            <li class="px-3 py-2">
              <div class="text-sm opacity-70">
                Ulogovan
              </div>
              <div class="font-medium truncate">
                {{ profile?.display_name || auth.user.value?.name }}
              </div>
              <div v-if="!isVerified" class="mt-2">
                <button
                  :disabled="sendingVerify"
                  class="btn btn-warning btn-xs w-full"
                  @click="resendVerification"
                >
                  {{ sendingVerify ? 'Slanje…' : 'Verifikuj email' }}
                </button>
              </div>
            </li>
            <li>
              <NuxtLink to="/profile">
                Profil
              </NuxtLink>
            </li>
            <li>
              <NuxtLink to="/catches">
                Moji ulovi
              </NuxtLink>
            </li>
            <li>
              <NuxtLink to="/events">
                Događaji
              </NuxtLink>
            </li>
            <li>
              <button @click="doLogout">
                Odjava
              </button>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@reference "tailwindcss";
.active {
  color: oklch(var(--p));
}
</style>
