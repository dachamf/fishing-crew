// ESLint 9 flat config for Nuxt 3/4 + TypeScript + Vue
import js from '@eslint/js'
import tseslint from 'typescript-eslint'
import vue from 'eslint-plugin-vue'
import vueParser from 'vue-eslint-parser'
import prettier from 'eslint-config-prettier'

export default [

  // Ignorisanja
  {
    ignores: ['.nuxt/**', '.output/**', 'dist/**', 'node_modules/**', '.eslintrc.*']
  },

  js.configs.recommended,

  // Vue preporuke (postavljaju vue parser)
  ...vue.configs['flat/recommended'],

  // TS preporuke (bez type-aware lint-a; type-check radiš odvojeno sa `nuxi typecheck`)
  ...tseslint.configs.recommended,

  // Parser opcije za .vue + TS <script setup lang="ts">
  {
    files: ['**/*.vue'],
    languageOptions: {
      parser: vueParser,
      parserOptions: {
        // TS parser za <script lang="ts">
        parser: tseslint.parser,
        ecmaVersion: 'latest',
        sourceType: 'module',
        extraFileExtensions: ['.vue'],
      },
    },
    rules: {
      // Za Nuxt pages/layouts često koristimo single-word imena; isključi za njih u override-u ispod
    },
  },

  // Parser za .ts/.tsx fajlove
  {
    files: ['**/*.ts', '**/*.tsx'],
    languageOptions: {
      parser: tseslint.parser,
      parserOptions: {
        ecmaVersion: 'latest',
        sourceType: 'module',
      },
    },
  },

  // Override: dozvoli single-word imena u pages/layouts
  {
    files: ['pages/**/*.{vue,ts,tsx}', 'layouts/**/*.{vue,ts,tsx}'],
    rules: { 'vue/multi-word-component-names': 'off' },
  },

  // Ako ne želiš da preimenuješ `components/app/menu.vue` u `AppMenu.vue`,
  // možeš ignorisati konkretno ime:
  {
    files: ['components/**/*.{vue,ts,tsx}'],
    rules: {
      'vue/multi-word-component-names': ['warn', { ignores: ['menu', 'index', 'default', 'profile'] }],
    },
  },

  // Opšta pravila (prilagodi po ukusu)
  {
    rules: {
      // Nuxt auto-importi često triggeruju no-undef; možeš privremeno ugasiti:
      'no-undef': 'off',

      'no-console': 'warn',
      '@typescript-eslint/no-unused-vars': ['warn', { argsIgnorePattern: '^_' }],
      '@typescript-eslint/no-explicit-any': 'off', // privremeno; postroži kasnije
    },
  },

  // Uvek poslednje: Prettier ugašava konfliktne stilove
  prettier,
]
