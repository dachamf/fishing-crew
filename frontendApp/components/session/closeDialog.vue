<script lang="ts" setup>
import { useHydrated } from "~/composables/useHydrated";
import { toErrorMessage } from "~/utils/http";

const props = defineProps<{
  modelValue: boolean;
  sessionId: ID;
  groupId?: ID;
}>();

const emit = defineEmits<{
  (e: "update:modelValue", v: boolean): void;
  (e: "closed"): void;
}>();
type ID = number;
type GroupMember = {
  id: ID;
  name?: string;
  display_name?: string;
  avatar_url?: string;
  profile?: { avatar_path?: string };
};

const hydrated = useHydrated();
const { $api } = useNuxtApp() as any;

const open = useState<boolean>(() => props.modelValue);
watch(
  () => props.modelValue,
  v => (open.value = v),
);
watch(open, v => emit("update:modelValue", v));

const toast = useToast();

// fetch članova – samo na klijentu (server:false)
// VAŽNO: pending je false na SSR, pa ćemo naš loading računati preko hydrated
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

// me / myId
const me = await $api
  .get("/v1/me")
  .then((r: any) => r.data)
  .catch(() => null);
const myId = me?.id as number | undefined;

// helpers
const selected = ref<ID[]>([]);
function avatar(m: GroupMember) {
  return m.avatar_url || m.profile?.avatar_path || "/icons/icon-64.png";
}
const name = (m: GroupMember) => m.display_name || m.name || `#${m.id}`;

// SSR-stabilno stanje za prikaz teksta
const isLoading = computed<boolean>(() => !hydrated.value || !!pending.value);
const isEmpty = computed<boolean>(() => Array.isArray(members.value) && members.value.length === 0);

const loading = ref(false);
async function submit() {
  loading.value = true;
  try {
    const { closeAndNominate } = useMySessions();
    await closeAndNominate(
      props.sessionId,
      selected.value.filter(uid => uid !== myId),
    );
    toast.success("Sesija zatvorena i poslati zahtevi.");
    open.value = false;
    emit("closed");
    emit("update:modelValue", false);
  }
  catch (e: any) {
    toast.error(toErrorMessage(e?.response?.data?.message) || "Greška pri zatvaranju sesije.");
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
    <p v-if="!selected.length" class="text-xs opacity-70 mt-2">
      Zatvaraš sesiju bez nominacija. Ulovi ostaju <b>nepotvrđeni</b> dok ne dodaš recenzente.
    </p>
    <p v-else class="opacity-80 mb-2">
      Izaberi članove grupe koji će potvrditi ulove iz ove sesije.
    </p>

    <div class="border rounded-lg p-2 max-h-64 overflow-auto">
      <!-- 1) UVEK postoji ovaj wrapper
           2) Menjamo SAMO unutrašnji tekst; time nema SSR/CSR mismatch-a -->
      <div v-if="isLoading" class="p-2 text-sm opacity-70">
        Učitavanje…
      </div>

      <div v-else-if="isEmpty" class="p-2 text-sm opacity-70">
        Nema članova
      </div>

      <template v-else>
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
            <div class="w-6 rounded-full border border-base-300 overflow-hidden">
              <img :src="avatar(m)" alt="">
            </div>
          </div>
          <span>{{ name(m) }}</span>
        </label>
      </template>
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
        :disabled="loading"
        class="btn btn-primary"
        @click="submit"
      >
        {{ selected.length ? 'Zatvori & pošalji' : 'Zatvori bez nominacija' }}
      </button>
    </template>
  </UiDialog>
</template>
