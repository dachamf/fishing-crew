<script lang="ts" setup>
import UiDialog from "~/components/ui/dialog.vue";

type Size = "sm" | "md" | "lg" | "xl";
type Tone = "default" | "danger" | "warning";

const props = withDefaults(
  defineProps<{
    modelValue: boolean;
    title?: string;
    message?: string;
    confirmText?: string;
    cancelText?: string;
    tone?: Tone;
    size?: Size;
    loading?: boolean;
    preventClose?: boolean;
    showClose?: boolean;
  }>(),
  {
    confirmText: "Potvrdi",
    cancelText: "Otkaži",
    tone: "default",
    size: "sm",
    loading: false,
    preventClose: false,
    showClose: true,
  },
);

const emit = defineEmits<{
  (e: "update:modelValue", v: boolean): void;
  (e: "confirm"): void;
  (e: "cancel"): void;
  (e: "open"): void;
  (e: "close"): void;
}>();

const confirmBtnClass = computed(() => {
  if (props.tone === "danger")
    return "btn-error";
  if (props.tone === "warning")
    return "btn-warning";
  return "btn-primary";
});

// v-model bridge
const open = computed({
  get: () => props.modelValue,
  set: (v: boolean) => emit("update:modelValue", v),
});

function onCancel() {
  emit("cancel");
  if (!props.loading && !props.preventClose)
    emit("update:modelValue", false);
}

function onConfirm() {
  // ne zatvaramo ovde — parent (npr. confirmReject) će zatvoriti kad završi
  emit("confirm");
}
</script>

<template>
  <UiDialog
    :model-value="open"
    :prevent-close="preventClose || loading"
    :show-close="showClose"
    :size="size"
    :title="title"
    @close="$emit('close')"
    @open="$emit('open')"
    @update:model-value="$emit('update:modelValue', $event)"
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
        <button
          :disabled="loading"
          class="btn"
          @click="onCancel"
        >
          {{ cancelText }}
        </button>
        <button
          :class="[confirmBtnClass, { loading }]"
          class="btn"
          @click="onConfirm"
        >
          {{ confirmText }}
        </button>
      </div>
    </template>
  </UiDialog>
</template>
