<script lang="ts" setup>
const props = defineProps<{ limit?: number }>();
const emit = defineEmits<{ (e: "files", v: File[]): void }>();
const files = ref<File[]>([]);
function onPick(e: Event) {
  const input = e.target as HTMLInputElement;
  const list = Array.from(input.files || []);
  files.value = list.slice(0, props.limit ?? 3);
  emit("files", files.value);
}

function objectURL(f: File | Blob) {
  return (window.URL || (window as any).webkitURL).createObjectURL(f);
}
</script>

<template>
  <div class="form-control">
    <label class="label"><span class="label-text">Fotografije (max {{ limit ?? 3 }})</span></label>
    <input
      accept="image/*"
      class="file-input file-input-bordered"
      multiple
      type="file"
      @change="onPick"
    >
    <div class="mt-2 flex gap-2 flex-wrap">
      <img
        v-for="(f, i) in files"
        :key="i"
        :src="objectURL(f)"
        alt=""
        class="h-20 rounded-lg object-cover"
      >
    </div>
  </div>
</template>
