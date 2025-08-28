<script lang="ts" setup>
import { Tab, TabGroup, TabList, TabPanel, TabPanels } from "@headlessui/vue";

import type { LeaderboardItem } from "~/types/api";

const props = defineProps<{
  activity: LeaderboardItem[]; // npr. po sessions_total (ili pieces_total)
  weight: LeaderboardItem[]; // po weight_total
  biggest: LeaderboardItem[]; // po biggest
}>();
</script>

<template>
  <TabGroup>
    <TabList class="tabs tabs-bordered mb-4">
      <Tab v-slot="{ selected }" as="template">
        <button :class="[selected && 'tab-active']" class="tab">
          Aktivnost
        </button>
      </Tab>
      <Tab v-slot="{ selected }" as="template">
        <button :class="[selected && 'tab-active']" class="tab">
          Ukupna težina
        </button>
      </Tab>
      <Tab v-slot="{ selected }" as="template">
        <button :class="[selected && 'tab-active']" class="tab">
          Najveći primerak
        </button>
      </Tab>
    </TabList>

    <TabPanels>
      <TabPanel>
        <slot :rows="props.activity" name="activity" />
      </TabPanel>
      <TabPanel>
        <slot :rows="props.weight" name="weight" />
      </TabPanel>
      <TabPanel>
        <slot :rows="props.biggest" name="biggest" />
      </TabPanel>
    </TabPanels>
  </TabGroup>
</template>
