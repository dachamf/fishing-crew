<script setup lang="ts">
const props = defineProps<{ limit?: number }>()
const emit = defineEmits<{(e:'files', v:File[]):void}>()
const files = ref<File[]>([])
function onPick(e:Event) {
  const input = e.target as HTMLInputElement
  const list = Array.from(input.files || [])
  files.value = list.slice(0, props.limit ?? 3); emit('files', files.value)
}
</script>

<template>
  <div class="form-control">
    <label class="label"><span class="label-text">Fotografije (max {{ limit ?? 3 }})</span></label>
    <input type="file" accept="image/*" class="file-input file-input-bordered"
           multiple @change="onPick" />
    <div class="mt-2 flex gap-2 flex-wrap">
      <img v-for="(f,i) in files" :key="i" :src="URL.createObjectURL(f)" class="h-20 rounded-lg object-cover" />
    </div>
  </div>
</template>
