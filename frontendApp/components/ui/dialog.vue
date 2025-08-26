<script lang="ts" setup>
type Size = "sm" | "md" | "lg" | "xl";

const props = withDefaults(defineProps<{
  modelValue: boolean;
  title?: string;
  size?: Size;
  closeOnEsc?: boolean;
  closeOnBackdrop?: boolean;
  showClose?: boolean;
  preventClose?: boolean; // koristi se da onemogući zatvaranje dok traje akcija (loading)
}>(), {
  size: "md",
  closeOnEsc: true,
  closeOnBackdrop: true,
  showClose: true,
  preventClose: false,
});

const emit = defineEmits<{
  (e: "update:modelValue", v: boolean): void;
  (e: "open"): void;
  (e: "close"): void;
}>();

const el = ref<HTMLDialogElement | null>(null);

function open() {
  if (!import.meta.client)
    return;
  if (el.value && !el.value.open) {
    el.value.showModal();
    emit("open");
    document.documentElement.classList.add("overflow-y-hidden");
  }
}
function close() {
  if (!import.meta.client)
    return;
  if (props.preventClose)
    return;
  if (el.value && el.value.open) {
    el.value.close();
    emit("close");
    document.documentElement.classList.remove("overflow-y-hidden");
  }
  emit("update:modelValue", false);
}

watch(() => props.modelValue, (v) => {
  if (v)
    open();
  else close();
}, { immediate: true });

onBeforeUnmount(() => {
  document.documentElement.classList.remove("overflow-y-hidden");
});

function onKeydown(e: KeyboardEvent) {
  if (e.key === "Escape" && props.closeOnEsc)
    close();
}
function onBackdrop() {
  if (props.closeOnBackdrop)
    close();
}

const boxSize = computed(() => ({
  sm: "max-w-sm",
  md: "max-w-lg",
  lg: "max-w-2xl",
  xl: "max-w-4xl",
}[props.size]));
</script>

<template>
  <!-- Teleport da bude na body -->
  <Teleport to="body">
    <dialog
      ref="el"
      aria-modal="true"
      class="modal"
      role="alertdialog"
      @close="emit('update:modelValue', false)"
      @keydown="onKeydown"
    >
      <div :class="boxSize" class="modal-box">
        <!-- Header -->
        <div class="flex items-center justify-between gap-4 mb-2">
          <h3 v-if="title" class="font-semibold text-lg">
            {{ title }}
          </h3>
          <slot name="header" />
          <button
            v-if="showClose"
            aria-label="Close"
            class="btn btn-sm btn-ghost"
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
            <button class="btn" @click="close">
              Zatvori
            </button>
          </slot>
        </div>
      </div>

      <!-- Backdrop -->
      <form
        class="modal-backdrop"
        method="dialog"
        @click.prevent="onBackdrop"
      >
        <button aria-label="Close backdrop">
          close
        </button>
      </form>
    </dialog>
  </Teleport>
</template>
