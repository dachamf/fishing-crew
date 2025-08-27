<script lang="ts" setup>
import type { FishingSession, ID } from "~/types/api";

defineOptions({ name: "SessionAssignedList" });

const props = defineProps<{
  page?: number;
}>();
const emit = defineEmits<{
  (e: "page", page: number): void;
}>();

const { assignedToMe, review } = useSessionReview();
const toast = useToast();

const loading = ref(true);
const items = ref<FishingSession[]>([]);
const meta = ref<any>(null);

async function load(p = props.page || 1) {
  loading.value = true;
  try {
    const { items: it, meta: m } = await assignedToMe(p);
    items.value = Array.isArray(it) ? it : [];
    meta.value = m;
  }
  catch (e: any) {
    toast.error(e?.response?.data?.message || "Greška pri učitavanju");
  }
  finally {
    loading.value = false;
  }
}
watch(
  () => props.page,
  (p) => {
    if (p)
      load(p);
  },
  { immediate: true },
);

function goPage(p: number) {
  if (!meta.value)
    return;
  const next = Math.min(Math.max(1, p), meta.value?.last_page || 1);
  emit("page", next);
}

const note = ref("");
const rejectingId = ref<ID | null>(null);
const approvingId = ref<ID | null>(null);
const rejectOpenFor = ref<ID | null>(null);

function openReject(id: ID) {
  note.value = "";
  rejectOpenFor.value = id;
}

async function approve(id: ID) {
  approvingId.value = id;
  try {
    await review(id, "approved", note.value || undefined);
    toast.success("Sesija odobrena");
    await load(meta.value?.current_page || 1);
  }
  catch (e: any) {
    toast.error(e?.response?.data?.message || "Greška");
  }
  finally {
    approvingId.value = null;
    note.value = "";
  }
}

async function confirmReject() {
  const id = rejectOpenFor.value;
  if (!id)
    return;
  rejectingId.value = id;
  try {
    await review(id, "rejected", note.value || undefined);
    toast.success("Sesija odbijena");
    await load(meta.value?.current_page || 1);
  }
  catch (e: any) {
    toast.error(e?.response?.data?.message || "Greška");
  }
  finally {
    rejectingId.value = null;
    rejectOpenFor.value = null;
    note.value = "";
  }
}
</script>

<template>
  <div class="space-y-3">
    <!-- Loading -->
    <div v-if="loading" class="grid gap-3">
      <div
        v-for="i in 3"
        :key="i"
        class="card bg-base-100 shadow"
      >
        <div class="card-body">
          <div class="skeleton h-6 w-48" />
          <div class="mt-2 flex gap-2">
            <div class="skeleton h-5 w-20" />
            <div class="skeleton h-5 w-24" />
            <div class="skeleton h-5 w-28" />
          </div>
        </div>
      </div>
    </div>

    <!-- Empty -->
    <div v-else-if="!items.length" class="opacity-70">
      Nema sesija koje čekaju tvoju odluku.
    </div>

    <!-- List -->
    <div v-else class="grid gap-3">
      <div
        v-for="s in items"
        :key="s.id"
        class="card bg-base-100 shadow"
      >
        <div class="card-body">
          <div class="flex items-start justify-between gap-3">
            <div>
              <div class="flex items-center gap-2">
                <NuxtLink :to="`/sessions/${s.id}`" class="text-lg font-semibold hover:underline">
                  {{ s.title || `Sesija #${s.id}` }}
                </NuxtLink>
                <span class="badge badge-outline">zatvorena</span>
              </div>
              <div class="flex flex-wrap items-center gap-2 opacity-75">
                <span class="badge badge-ghost">Ulov(a): {{ s.catches?.length ?? '—' }}</span>
                <span class="badge badge-ghost">
                  Počela: {{ s.started_at ? new Date(s.started_at).toLocaleString('sr-RS') : '—' }}
                </span>
              </div>
            </div>

            <div class="join">
              <button
                :class="{ loading: approvingId === s.id }"
                class="btn btn-success join-item"
                @click="approve(s.id)"
              >
                Odobri
              </button>
              <button class="btn btn-error join-item" @click="openReject(s.id)">
                Odbij
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="meta" class="join mt-2">
      <button
        :disabled="meta.current_page <= 1"
        class="btn btn-sm join-item"
        @click="goPage(1)"
      >
        «
      </button>
      <button
        :disabled="meta.current_page <= 1"
        class="btn btn-sm join-item"
        @click="goPage(meta.current_page - 1)"
      >
        ‹
      </button>
      <button class="btn btn-sm join-item pointer-events-none">
        Str. {{ meta.current_page }} / {{ meta.last_page }}
      </button>
      <button
        :disabled="meta.current_page >= meta.last_page"
        class="btn btn-sm join-item"
        @click="goPage(meta.current_page + 1)"
      >
        ›
      </button>
      <button
        :disabled="meta.current_page >= meta.last_page"
        class="btn btn-sm join-item"
        @click="goPage(meta.last_page)"
      >
        »
      </button>
    </div>

    <!-- Confirm reject dialog -->
    <UiConfirmDialog
      :loading="rejectingId !== null"
      :model-value="rejectOpenFor != null"
      :prevent-close="rejectingId !== null"
      cancel-text="Odustani"
      confirm-text="Potvrdi odbijanje"
      title="Potvrdi odbijanje sesije"
      tone="danger"
      @confirm="confirmReject"
      @update:model-value="
        (v) => {
          if (!v) rejectOpenFor = null
        }
      "
    >
      <p class="opacity-80 mb-2">
        Ova radnja označava <b>celu sesiju</b> kao odbijenu (svi ulovi će biti <b>rejected</b>).
      </p>
      <label class="label-text text-sm">Napomena (opciono)</label>
      <textarea
        v-model="note"
        class="textarea textarea-bordered w-full"
        placeholder="Zašto odbijaš?"
        rows="3"
      />
    </UiConfirmDialog>
  </div>
</template>
