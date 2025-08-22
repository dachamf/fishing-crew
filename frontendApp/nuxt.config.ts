// https://nuxt.com/docs/api/configuration/nuxt-config

export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  modules: ['@nuxt/icon', '@pinia/nuxt', '@vite-pwa/nuxt'],
  css: ['~/assets/css/app.css'],
  postcss: { plugins: { '@tailwindcss/postcss': {}, autoprefixer: {} } },
  typescript: { strict: true },
  app: {
    head: {
      title: 'HFC',
      titleTemplate: '%s · HFC',
      meta: [
        {
        name: 'description',
        content: 'HFC — aplikacija i zajednica za ribolovce.'
      }
      ]
    }
  },
  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'https://api.fishermen-crew.ddev.site/api'
    }
  },
  vite: {
    server: {
      allowedHosts: true,
      hmr: {host: 'app.fishermen-crew.ddev.site', protocol: 'wss', clientPort: 443},
    }
  },
});
