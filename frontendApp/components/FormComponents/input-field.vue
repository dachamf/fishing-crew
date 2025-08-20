<script lang="ts" setup>
const props = defineProps<{
  error?: string;
  label: string;
  name: string;
  type?: "text" | "textarea" | "number";
  disabled?: boolean;
}>();
</script>

<template>
  <fieldset class="fieldset">
    <legend class="fieldset-legend">
      {{ props.label }}
    </legend>
    <Field
      :as="type === 'textarea' ? 'textarea' : 'input'"
      :type="type || 'text'"
      placeholder=""
      class="w-full"
      :name="props.name"
      :disabled="disabled"
      :class="{
        'input-error': props.error,
        'input': !type || type === 'text' || type === 'number',
        'textarea': type === 'textarea',
      }"
    />
    <p v-if="props.error" class="fieldset-label text-error">
      {{ props.error }}
    </p>
  </fieldset>
</template>
