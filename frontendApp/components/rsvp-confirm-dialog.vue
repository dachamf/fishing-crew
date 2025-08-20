<script setup lang="ts">
import { Dialog, DialogPanel, DialogTitle, TransitionRoot, TransitionChild } from '@headlessui/vue'
const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{ (e:'confirm'): void }>()
</script>
<template>
  <TransitionRoot :show="open">
    <Dialog class="relative z-50" @close="open=false">
      <TransitionChild enter="ease-out duration-200" enter-from="opacity-0" enter-to="opacity-100" leave="ease-in duration-150" leave-from="opacity-100" leave-to="opacity-0">
        <div class="fixed inset-0 bg-black/50" />
      </TransitionChild>
      <div class="fixed inset-0 grid place-items-center p-4">
        <TransitionChild enter="ease-out duration-200" enter-from="opacity-0 scale-95" enter-to="opacity-100 scale-100" leave="ease-in duration-150" leave-from="opacity-100 scale-100" leave-to="opacity-0 scale-95">
          <DialogPanel class="bg-base-100 p-6 rounded-2xl shadow-xl w-full max-w-md">
            <DialogTitle class="text-lg font-semibold mb-2">Potvrdi RSVP</DialogTitle>
            <p class="opacity-80 mb-4">Da li želiš da potvrdiš izbor?</p>
            <div class="flex justify-end gap-2">
              <button class="btn" @click="open=false">Otkaži</button>
              <button class="btn btn-primary" @click="emit('confirm'); open=false">Potvrdi</button>
            </div>
          </DialogPanel>
        </TransitionChild>
      </div>
    </Dialog>
  </TransitionRoot>
</template>
