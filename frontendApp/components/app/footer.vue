<script setup lang="ts">
const props = withDefaults(
  defineProps<{
    compact?: boolean;
  }>(),
  {
    compact: false,
  },
);

const year = new Date().getFullYear();
</script>

<template>
  <footer
    class="border-t border-base-300 bg-base-200 text-base-content/90"
    aria-labelledby="site-footer"
  >
    <!-- wide -->
    <div v-if="!props.compact" class="container mx-auto px-4 py-10 grid gap-8 md:grid-cols-4">
      <!-- Brand -->
      <div>
        <h2 id="site-footer" class="sr-only">
          Footer
        </h2>
        <NuxtLink to="/" class="inline-flex items-center gap-2 font-semibold text-lg">
          <img
            src="/logo.png"
            alt=""
            class="h-6 w-auto"
          >
          Fishing Crew
        </NuxtLink>
        <p class="mt-3 text-sm opacity-70">
          Zajednica entuzijasta za ribolov. Planiraj događaje, beleži ulove, vodi statistiku.
        </p>

        <!-- Social -->
        <div class="mt-4 flex items-center gap-3">
          <a
            class="btn btn-ghost btn-sm btn-circle"
            aria-label="Instagram"
            href="https://instagram.com"
            target="_blank"
            rel="noopener"
          >
            <Icon name="tabler:brand-instagram" size="18" />
          </a>
          <a
            class="btn btn-ghost btn-sm btn-circle"
            aria-label="Facebook"
            href="https://facebook.com"
            target="_blank"
            rel="noopener"
          >
            <Icon name="tabler:brand-facebook" size="18" />
          </a>
          <a
            class="btn btn-ghost btn-sm btn-circle"
            aria-label="GitHub"
            href="https://github.com"
            target="_blank"
            rel="noopener"
          >
            <Icon name="tabler:brand-github" size="18" />
          </a>
        </div>
      </div>

      <!-- Product -->
      <div>
        <h3 class="font-semibold">
          Proizvod
        </h3>
        <ul class="mt-3 space-y-2 text-sm">
          <li>
            <NuxtLink class="link link-hover" to="/events">
              Događaji
            </NuxtLink>
          </li>
          <li>
            <NuxtLink class="link link-hover" to="/catches">
              Ulov
            </NuxtLink>
          </li>
          <li>
            <NuxtLink class="link link-hover" to="/leaderboard">
              Lista Najboljih
            </NuxtLink>
          </li>
          <li>
            <NuxtLink class="link link-hover" to="/activity">
              Aktivnost
            </NuxtLink>
          </li>
        </ul>
      </div>

      <!-- Support -->
      <div>
        <h3 class="font-semibold">
          Podrška
        </h3>
        <ul class="mt-3 space-y-2 text-sm">
          <li>
            <NuxtLink class="link link-hover" to="/help">
              Pomoć &amp; FAQ
            </NuxtLink>
          </li>
          <li>
            <NuxtLink class="link link-hover" to="/contact">
              Kontakt
            </NuxtLink>
          </li>
          <li>
            <NuxtLink class="link link-hover" to="/changelog">
              Changelog
            </NuxtLink>
          </li>
          <li>
            <NuxtLink class="link link-hover" to="/status">
              Status
            </NuxtLink>
          </li>
        </ul>
      </div>

      <!-- Newsletter (opciono) -->
      <div>
        <h3 class="font-semibold">
          Novosti
        </h3>
        <p class="mt-3 text-sm opacity-70">
          Primi povremene vesti i savete o sezoni i tehnikama.
        </p>
        <form class="mt-3 join w-full max-w-sm" @submit.prevent>
          <input
            type="email"
            class="input input-sm input-bordered join-item flex-1"
            placeholder="tvoj@email.com"
            aria-label="Email"
            required
          >
          <button class="btn btn-sm btn-primary join-item">
            Prijavi se
          </button>
        </form>
      </div>
    </div>

    <!-- bottom bar -->
    <div class="border-t border-base-300">
      <div
        class="container mx-auto px-4 py-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
      >
        <p class="text-xs opacity-70">
          © {{ year }} Fishing Crew. Sva prava zadržana.
        </p>

        <div class="flex flex-wrap items-center gap-4 text-xs">
          <NuxtLink class="link link-hover" to="/privacy">
            Privatnost
          </NuxtLink>
          <NuxtLink class="link link-hover" to="/terms">
            Uslovi
          </NuxtLink>
          <NuxtLink class="link link-hover" to="/impressum">
            Impresum
          </NuxtLink>
          <!-- mini theme toggle (ikonice sun/moon) -->
          <button class="btn btn-ghost btn-xs" aria-label="Promeni temu">
            <UiThemeSwitch />
          </button>
        </div>
      </div>
    </div>
  </footer>
</template>
