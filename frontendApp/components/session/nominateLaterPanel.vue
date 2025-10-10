<script lang="ts" setup>
import { useHydrated } from "~/composables/useHydrated";
import { toErrorMessage } from "~/utils/http";

type ID = number;
const props = defineProps<{ sessionId: ID; groupId: ID | null }>();
const emit = defineEmits<{ (e: "done"): void }>();

const { $api } = useNuxtApp() as any;
const { nominateLater } = useMySessions();
const hydrated = useHydrated();
const toast = useToast();

type Member = {
  id: ID;
  name?: string;
  display_name?: string;
  avatar_url?: string;
  profile?: { avatar_path?: string };
};

const members = ref<Member[] | null>(null);
const loading = ref(false);
const isLoading = computed(() => !hydrated.value || members.value === null);

const me = await $api
  .get("/v1/me")
  .then((r: any) => r.data)
  .catch(() => null);
const myId = me?.id as number | undefined;

onMounted(async () => {
  if (!props.groupId) {
    members.value = [];
    return;
  }
  try {
    const r = await $api.get(`/v1/groups/${props.groupId}/members`);
    members.value = r.data?.data ?? r.data ?? [];
  }
  catch {
    members.value = [];
  }
});

const picks = ref<ID[]>([]);

function avatar(m: Member) {
  return m.avatar_url || m.profile?.avatar_path || "/icons/icon-64.png";
}
const fname = (m: Member) => m.display_name || m.name || `#${m.id}`;

async function submit() {
  if (!picks.value.length) {
    toast.error("Izaberi bar jednog recenzenta.");
    return;
  }
  loading.value = true;
  try {
    await nominateLater(
      props.sessionId,
      picks.value.filter(uid => uid !== myId),
    );
    toast.success("Poslati zahtevi za potvrdu.");
    picks.value = [];
    emit("done");
  }
  catch (e: any) {
    toast.error(toErrorMessage(e?.response?.data?.message) || "Greška pri nominaciji.");
  }
  finally {
    loading.value = false;
  }
}
</script>

<template>
  <div class="space-y-2">
    <p class="text-sm opacity-75">
      Dodaj recenzente za ovu već zatvorenu sesiju. Biće im poslat link za potvrdu.
    </p>

    <div class="border rounded-lg p-2 max-h-60 overflow-auto">
      <div v-if="isLoading" class="p-2 text-sm opacity-70">
        Učitavanje…
      </div>
      <div v-else-if="(members?.length || 0) === 0" class="p-2 text-sm opacity-70">
        Nema članova
      </div>
      <template v-else>
        <label
          v-for="m in (members || []).filter((u) => u.id !== myId)"
          :key="m.id"
          class="label cursor-pointer justify-start gap-3 py-2"
        >
          <input
            v-model="picks"
            :value="m.id"
            class="checkbox checkbox-sm"
            type="checkbox"
          >
          <div class="avatar">
            <div class="w-6 rounded-full border border-base-300 overflow-hidden">
              <img :src="avatar(m)" alt="">
            </div>
          </div>
          <span>{{ fname(m) }}</span>
        </label>
      </template>
    </div>

    <div class="flex justify-end">
      <button
        :class="{ loading }"
        :disabled="loading"
        class="btn btn-primary"
        @click="submit"
      >
        Pošalji nominacije
      </button>
    </div>
  </div>
</template>
