<script setup lang="ts">
type Size = 'sm' | 'md' | 'lg' | 'xl'

const props = withDefaults(defineProps<{
  modelValue: boolean
  title?: string
  size?: Size
  closeOnEsc?: boolean
  closeOnBackdrop?: boolean
  showClose?: boolean
  preventClose?: boolean  // koristi se da onemogući zatvaranje dok traje akcija (loading)
}>(), {
  size: 'md',
  closeOnEsc: true,
  closeOnBackdrop: true,
  showClose: true,
  preventClose: false
})

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
  (e: 'open'): void
  (e: 'close'): void
}>()

const el = ref<HTMLDialogElement | null>(null)

const open = () => {
  if (!process.client) return
  if (el.value && !el.value.open) {
    el.value.showModal()
    emit('open')
    document.documentElement.classList.add('overflow-y-hidden')
  }
}
const close = () => {
  if (!process.client) return
  if (props.preventClose) return
  if (el.value && el.value.open) {
    el.value.close()
    emit('close')
    document.documentElement.classList.remove('overflow-y-hidden')
  }
  emit('update:modelValue', false)
}

watch(() => props.modelValue, (v) => {
  if (v) open(); else close()
}, { immediate: true })

onBeforeUnmount(() => {
  document.documentElement.classList.remove('overflow-y-hidden')
})

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape' && props.closeOnEsc) close()
}
function onBackdrop() {
  if (props.closeOnBackdrop) close()
}

const boxSize = computed(() => ({
  sm: 'max-w-sm',
  md: 'max-w-lg',
  lg: 'max-w-2xl',
  xl: 'max-w-4xl'
}[props.size]))
</script>

<template>
  <!-- Teleport da bude na body -->
  <Teleport to="body">
    <dialog
      ref="el"
      class="modal"
      role="alertdialog"
      aria-modal="true"
      @keydown="onKeydown"
      @close="emit('update:modelValue', false)"
    >
      <div class="modal-box" :class="boxSize">
        <!-- Header -->
        <div class="flex items-center justify-between gap-4 mb-2">
          <h3 v-if="title" class="font-semibold text-lg">{{ title }}</h3>
          <slot name="header" />
          <button
            v-if="showClose"
            class="btn btn-sm btn-ghost"
            aria-label="Close"
            @click="close"
          >
            ✕
          </button>
        </div>

        <!-- Body -->
        <div class="mt-2">
          <slot />
        </div>

        <!-- Footer -->
        <div class="modal-action">
          <slot name="footer">
            <button class="btn" @click="close">Zatvori</button>
          </slot>
        </div>
      </div>

      <!-- Backdrop -->
      <form method="dialog" class="modal-backdrop" @click.prevent="onBackdrop">
        <button aria-label="Close backdrop">close</button>
      </form>
    </dialog>
  </Teleport>
</template>
