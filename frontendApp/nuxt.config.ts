import { defineNuxtConfig } from "nuxt/config";

export default defineNuxtConfig({
  compatibilityDate: "2025-07-15",
  devtools: { enabled: false },
  modules: [
    "@nuxt/eslint",
    "@nuxt/icon",
    "@pinia/nuxt",
    "@vite-pwa/nuxt",
    "@vueuse/nuxt",
    "@nuxt/image",
  ],
  image: {
    format: ["webp", "avif", "png"],
  },
  css: ["~/assets/css/app.css", "maplibre-gl/dist/maplibre-gl.css"],
  postcss: { plugins: { "@tailwindcss/postcss": {}, "autoprefixer": {} } },
  typescript: { strict: true },
  eslint: {
    config: {
      standalone: false,
    },
  },
  app: {
    head: {
      title: "HFC",
      titleTemplate: "%s · HFC",
      meta: [
        {
          name: "description",
          content: "HFC — aplikacija i zajednica za ribolovce.",
        },
      ],
    },
  },
  runtimeConfig: {
    public: {
      apiBase: "",
    },
  },
  nitro: {
    compressPublicAssets: true, // brotli/gzip
    routeRules: {
      "/api/**": { headers: { "cache-control": "public, max-age=60" } }, // lagani cache
      "/api-ssr/**": { headers: { "cache-control": "public, max-age=60" } },
      "/_ipx/**": { headers: { "cache-control": "public, max-age=604800, immutable" } },
    },
  },
  vite: {
    build: { sourcemap: false, minify: "esbuild" },
    server: {
      allowedHosts: true,
      hmr: true,
    },
  },
});
