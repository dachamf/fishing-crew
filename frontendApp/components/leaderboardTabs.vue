<script setup lang="ts">
import { Tab, TabGroup, TabList, TabPanel, TabPanels } from "@headlessui/vue";

const props = defineProps<{ activity: any[]; weight: any[]; biggest: any[] }>();
</script>

<template>
  <TabGroup>
    <TabList class="tabs tabs-bordered mb-4">
      <Tab v-slot="{ selected }" as="template">
        <button class="tab" :class="[selected && 'tab-active']">
          Aktivnost
        </button>
      </Tab>
      <Tab v-slot="{ selected }" as="template">
        <button class="tab" :class="[selected && 'tab-active']">
          Ukupna težina
        </button>
      </Tab>
      <Tab v-slot="{ selected }" as="template">
        <button class="tab" :class="[selected && 'tab-active']">
          Najveći primerak
        </button>
      </Tab>
    </TabList>
    <TabPanels>
      <TabPanel><slot name="activity" :rows="props.activity" /></TabPanel>
      <TabPanel><slot name="weight" :rows="props.weight" /></TabPanel>
      <TabPanel><slot name="biggest" :rows="props.biggest" /></TabPanel>
    </TabPanels>
  </TabGroup>
</template>
