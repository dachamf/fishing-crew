<script lang="ts" setup>
import type { FishingCatch, GroupMember, PhotoLite } from "~/types/api";

defineOptions({ name: "CatchDetailPage" });

const route = useRoute();
const id = Number(route.params.id);
const { $api } = useNuxtApp() as any;
const { withdraw } = useCatchReview();
const toast = useToast();
const { user } = useAuth();
const { data, pending, error, refresh } = await useAsyncData<FishingCatch>(
  () => `catch:${id}`,
  async () => {
    const res = await $api.get(`/v1/catches/${id}`, {
      params: { include: "user,group,session.user,session.photos,confirmations" },
    });
    const c = res.data as FishingCatch;
    // fallback ako BE ne vrati confirmations u include-u
    if (!Array.isArray(c.confirmations)) {
      try {
        const r2 = await $api.get(`/v1/catches/${id}/confirmations`);
        c.confirmations = r2.data?.data ?? r2.data ?? [];
      }
      catch {}
    }
    return c;
  },
);

const photos = computed<PhotoLite[]>(() => data.value?.session?.photos ?? []);
const speciesText = computed(
  () => data.value?.species_label || data.value?.species || data.value?.species_name || "-",
);

// ---- Confirmations (approve / reject) ----

const rejectOpen = ref(false);
const note = ref("");
const confirming = ref<null | "approved" | "rejected">(null);

async function openReject() {
  rejectOpen.value = true;
}

async function confirmReject() {
  await sendConfirm("rejected");
  rejectOpen.value = false;
}

// group id iz ulova ili sesije
const groupId = computed<number | null>(
  () => data.value?.group?.id ?? data.value?.session?.group?.id ?? data.value?.group_id ?? null,
);

// učitaj članove grupe (pokušaj /members, pa fallback na include=members)
const { data: members, pending: membersLoading } = await useAsyncData<GroupMember[]>(
  () => (groupId.value ? `group:${groupId.value}:members` : `group:none`),
  async () => {
    if (!groupId.value)
      return [];
    try {
      const r = await $api.get(`/v1/groups/${groupId.value}/members`);
      return r.data?.data ?? r.data ?? [];
    }
    catch {
      try {
        const r2 = await $api.get(`/v1/groups/${groupId.value}`, { params: { include: "members" } });
        return r2.data?.members ?? [];
      }
      catch {
        return [];
      }
    }
  },
  { watch: [groupId], server: false },
);

const myId = computed(() => user.value?.id ?? null);
const myConfirmation = computed(
  () => (data.value?.confirmations || []).find(c => c.confirmed_by === myId.value) || null,
);
const filteredMembers = computed(() => (members.value || []).filter(m => m.id !== myId.value));

const isOwner = computed(() => data.value?.user?.id === myId.value);
const myPending = computed(() =>
  (data.value?.confirmations || []).some(
    c => c.confirmed_by === myId.value && c.status === "pending",
  ),
);
const canNominate = computed(() => isOwner.value);

async function sendConfirm(status: "approved" | "rejected") {
  try {
    confirming.value = status;
    await $api.post(`/v1/catches/${id}/confirmations`, {
      status,
      note: note.value || undefined,
    });
    note.value = "";
    await refresh();
  }
  catch (e: any) {
    toast.error(e?.response?.data?.message || "Greška pri potvrdi");
  }
  finally {
    toast.success("Potvrda poslata");
    confirming.value = null;
  }
}

// multi-izbor članova
const selectedMemberIds = ref<number[]>([]);
const nameById = computed<Record<number, string>>(() =>
  Object.fromEntries(
    (members.value || []).map(m => [m.id, m.display_name || m.name || `#${m.id}`]),
  ),
);
const avatarOf = (m: GroupMember) => m.avatar_url || m.profile?.avatar_url || "/icons/icon-64.png";

async function requestConfirmations() {
  const ids = selectedMemberIds.value.filter(Boolean);
  if (!ids.length)
    return toast.info("Izaberi bar jednog člana.");
  try {
    await $api.post(`/v1/catches/${id}/request-confirmation`, { user_ids: ids });
    selectedMemberIds.value = [];
    await refresh();
  }
  catch (e: any) {
    toast.error(toErrorMessage(e?.response?.data?.message) || "Greška pri slanju zahteva");
  }
}

async function withdrawDecision() {
  try {
    await withdraw(id);
    toast.success("Odluka povučena");
    await refresh();
  }
  catch (e: any) {
    toast.error(e?.response?.data?.message || "Greška pri povlačenju odluke");
  }
}
</script>

<template>
  <div class="container mx-auto p-4 space-y-4">
    <div class="breadcrumbs text-sm">
      <ul>
        <li>
          <NuxtLink to="/catches">
            Sesije
          </NuxtLink>
        </li>
        <li>Ulov #{{ id }}</li>
      </ul>
    </div>

    <!-- Loading -->
    <div v-if="pending" class="grid md:grid-cols-3 gap-4">
      <div class="md:col-span-2 card bg-base-100 shadow">
        <div class="card-body space-y-3">
          <div class="skeleton h-6 w-40" />
          <div class="flex gap-2">
            <div class="skeleton h-6 w-24" />
            <div class="skeleton h-6 w-28" />
            <div class="skeleton h-6 w-20" />
          </div>
          <div class="grid grid-cols-3 gap-2">
            <div class="skeleton h-28" />
            <div class="skeleton h-28" />
            <div class="skeleton h-28" />
          </div>
        </div>
      </div>
      <div class="card bg-base-100 shadow">
        <div class="card-body space-y-2">
          <div class="skeleton h-6 w-32" />
          <div class="space-y-2">
            <div class="skeleton h-5 w-full" />
            <div class="skeleton h-5 w-3/5" />
          </div>
          <div class="skeleton h-24 w-full" />
        </div>
      </div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="alert alert-error">
      Greška pri učitavanju.
    </div>

    <!-- Content -->
    <div v-else class="grid md:grid-cols-3 gap-4">
      <!-- Info -->
      <div class="md:col-span-2 card bg-base-100 shadow">
        <div class="card-body space-y-2">
          <h1 class="text-2xl font-semibold">
            Ulov — {{ speciesText }}
          </h1>

          <div class="opacity-75 flex flex-wrap gap-2">
            <span class="badge badge-ghost">Grupa: {{ data?.group?.name || '—' }}</span>
            <span class="badge badge-ghost">Korisnik: {{ data?.user?.display_name || data?.user?.name }}</span>
            <span class="badge badge-ghost">Kom: {{ data?.count }}</span>
            <span class="badge badge-ghost">Težina: {{ Number(data?.total_weight_kg || 0).toFixed(3) }} kg</span>
            <span class="badge badge-ghost">Najveća: {{ Number(data?.biggest_single_kg || 0).toFixed(3) }} kg</span>
            <span class="badge badge-outline">{{ data?.status }}</span>
          </div>

          <div class="mt-2">
            <div class="font-medium">
              Napomena
            </div>
            <div class="opacity-80">
              {{ data?.note || '—' }}
            </div>
          </div>

          <div v-if="(photos.length || 0) > 0" class="mt-3 grid grid-cols-3 gap-2">
            <div
              v-for="(p, idx) in photos.slice(0, 3)"
              :key="idx"
              class="aspect-video rounded-xl overflow-hidden border border-base-300"
            >
              <img
                :src="p.url"
                class="w-full h-full object-cover"
                loading="lazy"
              >
            </div>
          </div>
        </div>
      </div>

      <!-- Confirmations panel -->
      <div class="card bg-base-100 shadow">
        <div class="card-body space-y-3">
          <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold">
              Potvrde
            </h2>
            <span class="badge">{{ data?.confirmations?.length || 0 }}</span>
          </div>

          <ul class="space-y-2 max-h-64 overflow-auto">
            <li
              v-for="c in data?.confirmations || []"
              :key="c.id"
              class="flex items-start justify-between"
            >
              <div>
                <span
                  :class="{
                    'badge-success': c.status === 'approved',
                    'badge-error': c.status === 'rejected',
                    'badge-warning': c.status === 'pending',
                  }"
                  class="badge capitalize"
                >
                  {{ c.status }}
                </span>
                <div class="text-sm opacity-70">
                  {{ c.note || '—' }}
                </div>
              </div>
              <div class="text-xs opacity-60">
                {{ c.created_at ? new Date(c.created_at).toLocaleString('sr-RS') : '' }}
              </div>
            </li>
          </ul>

          <div class="divider my-2" />

          <div v-if="canNominate" class="form-control">
            <label class="label">Zatraži potvrde</label>

            <!-- dropdown sa checkbox-ovima -->
            <div class="dropdown">
              <div class="btn" tabindex="0">
                {{
                  selectedMemberIds.length
                    ? `Izabrano: ${selectedMemberIds.length}`
                    : 'Izaberi članove'
                }}
              </div>
              <ul
                class="dropdown-content menu p-2 shadow bg-base-100 rounded-box w-72 max-h-64 overflow-auto"
                tabindex="0"
              >
                <li v-if="membersLoading" class="opacity-70 px-2 py-1">
                  Učitavanje…
                </li>
                <li v-else-if="!members?.length" class="opacity-70 px-2 py-1">
                  Nema članova
                </li>

                <li v-for="m in filteredMembers || []" :key="m.id">
                  <label class="label cursor-pointer justify-start gap-3 py-2">
                    <input
                      v-model="selectedMemberIds"
                      :value="m.id"
                      class="checkbox checkbox-sm"
                      type="checkbox"
                    >
                    <div class="avatar">
                      <div class="w-6 rounded-full border border-base-300">
                        <img :src="avatarOf(m)" alt="">
                      </div>
                    </div>
                    <span>{{ m.display_name || m.name }}</span>
                  </label>
                </li>
              </ul>
            </div>

            <!-- prikaz izabranih -->
            <div v-if="selectedMemberIds.length" class="mt-2 flex flex-wrap gap-2">
              <span
                v-for="uid in selectedMemberIds"
                :key="uid"
                class="badge badge-outline"
              >
                {{ nameById[uid] || `#${uid}` }}
              </span>
            </div>

            <div class="mt-2">
              <button
                :disabled="!selectedMemberIds.length"
                class="btn btn-primary btn-sm"
                @click="requestConfirmations"
              >
                Pošalji zahteve
              </button>
            </div>
          </div>

          <div class="divider my-2" />

          <div class="form-control">
            <label class="label-text text-sm">Moja odluka</label>
            <textarea
              v-model="note"
              class="textarea textarea-bordered"
              placeholder="Opciona napomena"
              rows="2"
            />
            <div v-if="myPending" class="mt-2 join gap-1">
              <button
                :class="{ loading: confirming === 'approved' }"
                class="btn btn-success join-item"
                @click="sendConfirm('approved')"
              >
                Odobri
              </button>

              <!-- Odbij otvara dijalog -->
              <button class="btn btn-error join-item" @click="openReject">
                Odbij
              </button>
            </div>
            <div v-else-if="myConfirmation" class="mt-2 flex items-center gap-3 text-sm opacity-80">
              <div>
                Odluka je već poslata:
                <span class="badge capitalize">{{ myConfirmation.status }}</span>
              </div>
              <button class="btn btn-ghost btn-xs" @click="withdrawDecision">
                Povuci odluku
              </button>
            </div>

            <!-- ConfirmDialog sa custom telom (textarea za napomenu) -->
            <UiConfirmDialog
              v-model="rejectOpen"
              :loading="confirming === 'rejected'"
              :prevent-close="confirming === 'rejected'"
              cancel-text="Odustani"
              confirm-text="Potvrdi odbijanje"
              title="Potvrdi odbijanje"
              tone="danger"
              @confirm="confirmReject"
            >
              <p class="opacity-80 mb-2">
                Ova radnja će označiti ulov kao <b>odbijen</b>.
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
        </div>
      </div>
    </div>
  </div>
</template>
