<script setup lang="ts">
import UiDialog from '~/components/ui/Dialog.vue'

type Size = 'sm' | 'md' | 'lg' | 'xl'
type Tone = 'default' | 'danger' | 'warning'

const props = withDefaults(defineProps<{
  modelValue: boolean
  title?: string
  message?: string
  confirmText?: string
  cancelText?: string
  tone?: Tone
  size?: Size
  loading?: boolean
  preventClose?: boolean
  showClose?: boolean
}>(), {
  confirmText: 'Potvrdi',
  cancelText: 'Otkaži',
  tone: 'default',
  size: 'sm',
  loading: false,
  preventClose: false,
  showClose: true,
})

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
  (e: 'confirm'): void
  (e: 'cancel'): void
  (e: 'open'): void
  (e: 'close'): void
}>()

const confirmBtnClass = computed(() => {
  if (props.tone === 'danger') return 'btn-error'
  if (props.tone === 'warning') return 'btn-warning'
  return 'btn-primary'
})
</script>

<template>
  <UiDialog
    :model-value="modelValue"
    :title="title"
    :size="size"
    :prevent-close="preventClose"
    :show-close="showClose"
    @open="$emit('open')"
    @close="$emit('close')"
    @update:modelValue="$emit('update:modelValue', $event)"
  >
    <!-- Body -->
    <slot>
      <p class="opacity-80">
        {{ message || 'Da li si siguran?' }}
      </p>
    </slot>

    <!-- Footer -->
    <template #footer>
      <div class="flex items-center gap-2">
        <button class="btn" :disabled="loading" @click="$emit('cancel'); $emit('update:modelValue', false)">
          {{ cancelText }}
        </button>
        <button
          class="btn"
          :class="[confirmBtnClass, { loading }]"
          @click="$emit('confirm')"
        >
          {{ confirmText }}
        </button>
      </div>
    </template>
  </UiDialog>
</template>
