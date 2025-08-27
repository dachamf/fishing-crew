<script lang="ts" setup>
type ID = number;
type GroupMember = {
  id: ID;
  name?: string;
  display_name?: string;
  avatar_url?: string;
  profile?: { avatar_path?: string };
};

const props = defineProps<{
  modelValue: boolean;
  sessionId: ID;
  groupId?: ID;
}>();

const emit = defineEmits<{
  (e: "update:modelValue", v: boolean): void;
  (e: "closed"): void;
}>();

const { $api } = useNuxtApp() as any;
const open = useState<boolean>(() => props.modelValue);
watch(
  () => props.modelValue,
  v => (open.value = v),
);
watch(open, v => emit("update:modelValue", v));

const toast = useToast();

const { data: members, pending } = await useAsyncData<GroupMember[]>(
  () => (props.groupId ? `group:${props.groupId}:members` : `group:none`),
  async () => {
    if (!props.groupId)
      return [];
    try {
      const r = await $api.get(`/v1/groups/${props.groupId}/members`);
      return r.data?.data ?? r.data ?? [];
    }
    catch {
      return [];
    }
  },
  { server: false, watch: [() => props.groupId] },
);

const me = await $api
  .get("/v1/me")
  .then((r: any) => r.data)
  .catch(() => null);
const myId = me?.id;

const selected = ref<ID[]>([]);
const avatar = (m: GroupMember) => m.avatar_url || m.profile?.avatar_path || "/icons/icon-64.png";
const name = (m: GroupMember) => m.display_name || m.name || `#${m.id}`;

const loading = ref(false);
async function submit() {
  loading.value = true;
  try {
    await $api.post(`/v1/sessions/${props.sessionId}/close-and-nominate`, {
      reviewer_ids: selected.value.filter(uid => uid !== myId),
    });
    toast.success("Sesija zatvorena i poslatizahtevi");
    open.value = false;
    emit("closed");
    emit("update:modelValue", false);
  }
  catch (e: any) {
    toast.error(toErrorMessage(e?.response?.data?.message) || "Greška pri zatvaranju sesije");
  }
  finally {
    loading.value = false;
  }
}
</script>

<template>
  <UiDialog
    v-model="open"
    :prevent-close="loading"
    size="md"
    title="Zatvori sesiju"
  >
    <p class="opacity-80 mb-2">
      Izaberi članove grupe koji će potvrditi ulove iz ove sesije.
    </p>

    <div class="border rounded-lg p-2 max-h-64 overflow-auto">
      <div v-if="pending" class="p-2">
        Učitavanje…
      </div>
      <div v-else-if="!members?.length" class="p-2 opacity-70">
        Nema članova
      </div>
      <label
        v-for="m in (members || []).filter((u) => u.id !== myId)"
        :key="m.id"
        class="label cursor-pointer justify-start gap-3 py-2"
      >
        <input
          v-model="selected"
          :value="m.id"
          class="checkbox checkbox-sm"
          type="checkbox"
        >
        <div class="avatar">
          <div class="w-6 rounded-full border border-base-300">
            <img :src="avatar(m)" alt="">
          </div>
        </div>
        <span>{{ name(m) }}</span>
      </label>
    </div>

    <template #footer>
      <button
        :disabled="loading"
        class="btn"
        @click="open = false"
      >
        Otkaži
      </button>
      <button
        :class="{ loading }"
        :disabled="!selected.length"
        class="btn btn-primary"
        @click="submit"
      >
        Zatvori & pošalji
      </button>
    </template>
  </UiDialog>
</template>
