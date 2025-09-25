<script lang="ts" setup>
import type {
  FishingSession,
  SessionConfirmation,
  SessionReview,
  SessionReviewStatus,
  UserLite,
} from "~/types/api";

defineOptions({ name: "SessionDetailPage" });

const route = useRoute();
const router = useRouter(); // ⬅️ NEW
const id = Number(route.params.id);
const { $api } = useNuxtApp() as any;
const toast = useToast();
const { review, confirmByToken } = useSessionReview(); // ⬅️ UPDATED (dodali confirmByToken)

const coords = ref<{ lng: number | null; lat: number | null }>({ lng: null, lat: null });

/** ⬅️ NEW: token iz query stringa (za potvrdu bez login-a) */
const token = computed(() => {
  const t = route.query.token;
  return typeof t === "string" && t.length ? t : undefined;
});
const tokenLoading = ref(false);

// Učitaj me (radi identifikacije da li sam reviewer)
const { data: me } = await useAsyncData<UserLite>(
  "me:sessions-id",
  async () => (await $api.get("/v1/me")).data,
  { server: false, immediate: true },
);

// Učitaj sesiju + reviews.reviewer (+ eventualno catches ako prikazuješ)
const { data, pending, error, refresh } = await useAsyncData<FishingSession>(
  () => `session:${id}`,
  async () => {
    const res = await $api.get(`/v1/sessions/${id}`, {
      params: {
        include: "catches.user,photos,reviews.reviewer,user,confirmations.nominee",
      },
    });
    return res.data as FishingSession;
  },
  { watch: [() => id] },
);

async function tokenDecision(decision: "approved" | "rejected") {
  if (!token.value) {
    return;
  }
  tokenLoading.value = true;
  try {
    await confirmByToken(id, token.value, decision);
    toast.success(decision === "approved" ? "Odobreno." : "Odbijeno.");
    // ukloni token iz URL-a da se panel ne prikazuje ponovo
    const nextQuery = { ...route.query };
    delete (nextQuery as any).token;
    await router.replace({ query: nextQuery });
    await refresh();
  }
  catch (e: any) {
    toast.error(e?.response?.data?.message || "Greška");
  }
  finally {
    tokenLoading.value = false;
  }
}

// Helpers
const myId = computed(() => me.value?.id);
const reviews = computed<SessionReview[]>(() => data.value?.reviews ?? []);
const myReview = computed<SessionReview | null>(() => {
  const uid = myId.value;
  return (reviews.value.find(r => r.reviewer_id === uid) ?? null) as SessionReview | null;
});

const confirmations = computed<SessionConfirmation[]>(
  () => (data.value?.confirmations ?? []) as SessionConfirmation[],
);

const sessionOverall = computed<SessionReviewStatus>(() => {
  const list = reviews.value;
  if (!list.length)
    return "pending";
  if (list.some(r => r.status === "rejected"))
    return "rejected";
  if (list.every(r => r.status === "approved"))
    return "approved";
  return "pending";
});

// UI state
const note = ref("");
const rejecting = ref(false);
const approveLoading = ref(false);
const rejectOpen = ref(false);

// Akcije
async function approveSession() {
  if (!data.value?.id)
    return;
  approveLoading.value = true;
  try {
    await review(data.value.id, "approved", note.value || undefined);
    note.value = "";
    toast.success("Odobreno.");
    await refresh();
  }
  catch (e: any) {
    toast.error(e?.response?.data?.message || "Greška");
  }
  finally {
    approveLoading.value = false;
  }
}

function openReject() {
  rejectOpen.value = true;
}
async function confirmReject() {
  if (!data.value?.id)
    return;
  rejecting.value = true;
  try {
    await review(data.value.id, "rejected", note.value || undefined);
    note.value = "";
    toast.success("Odbijeno.");
    await refresh();
  }
  catch (e: any) {
    toast.error(e?.response?.data?.message || "Greška");
  }
  finally {
    rejecting.value = false;
    rejectOpen.value = false;
  }
}

// Badge klasa helper
function statusClass(s: SessionReviewStatus) {
  return {
    "badge-warning": s === "pending",
    "badge-success": s === "approved",
    "badge-error": s === "rejected",
  };
}

const closeOpen = ref(false);
function onClosed() {
  closeOpen.value = false;
  refresh();
}

// kad se učita sesija, popuni coords
watch(
  () => data.value,
  (v) => {
    if (!v)
      return;
    coords.value = {
      lng: v?.longitude ?? null,
      lat: v?.latitude ?? null,
    };
  },
  { immediate: true },
);

// dozvoli edit lokacije samo vlasniku dok je sesija OPEN
const canEditLocation = computed(() => {
  const isOwner = data.value?.user?.id && myId.value && data.value.user.id === myId.value;
  return data.value?.status === "open" && !!isOwner;
});
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
        <li>Detalj</li>
      </ul>
    </div>

    <!-- ⬅️ NEW: Token panel (Approve/Reject bez login-a) -->
    <div
      v-if="$route.query.token"
      class="alert bg-base-100 border border-base-300 rounded-xl shadow flex flex-col gap-2"
    >
      <div class="font-semibold">
        Potvrda sesije
      </div>
      <p class="opacity-75 text-sm">
        Ovaj link omogućava potvrdu sesije kao nominovani recenzent.
      </p>
      <div class="join">
        <button
          class="btn btn-success join-item"
          :class="{ loading: tokenLoading }"
          :disabled="tokenLoading"
          @click="tokenDecision('approved')"
        >
          ✅ Odobri
        </button>
        <button
          class="btn btn-error join-item"
          :class="{ loading: tokenLoading }"
          :disabled="tokenLoading"
          @click="tokenDecision('rejected')"
        >
          ❌ Odbij
        </button>
      </div>
    </div>

    <div class="card bg-base-100 shadow rounded-xl h-full">
      <div class="card-body grid md:grid-cols-2 gap-6">
        <!--        Levo je sesija -->
        <div v-if="pending" class="flex items-center gap-2">
          <span class="loading loading-spinner" /> Učitavanje…
        </div>
        <div v-else-if="error" class="alert alert-error">
          Greška pri učitavanju.
        </div>
        <div v-else>
          <div class="flex items-start justify-between">
            <div>
              <h1 class="text-2xl font-semibold flex gap-2">
                {{ data?.title || 'Fishing sesija' }}
                <span :class="statusClass(sessionOverall)" class="badge">{{ sessionOverall }}</span>
              </h1>

              <!-- MOJA ODLUKA (legacy review mehanizam – ostaje) -->
              <div v-if="myReview && myReview.status === 'pending'" class="card bg-base-100 shadow">
                <div class="card-body space-y-2">
                  <h2 class="text-lg font-semibold">
                    Moja odluka za sesiju
                  </h2>
                  <p class="opacity-70 text-sm">
                    Tvoja nominacija:
                    <span :class="statusClass(myReview.status)" class="badge">{{
                      myReview.status
                    }}</span>
                  </p>

                  <label class="label-text">Napomena (opciono)</label>
                  <textarea
                    v-model="note"
                    class="textarea textarea-bordered w-full"
                    placeholder="Dodaj kratku napomenu..."
                    rows="3"
                  />

                  <div class="mt-2 join gap-1">
                    <button
                      :class="{ loading: approveLoading }"
                      class="btn btn-success join-item"
                      @click="approveSession"
                    >
                      Odobri sesiju
                    </button>
                    <button class="btn btn-error join-item" @click="openReject">
                      Odbij sesiju
                    </button>
                  </div>

                  <!-- ConfirmDialog za odbijanje -->
                  <UiConfirmDialog
                    v-model="rejectOpen"
                    :loading="rejecting"
                    :prevent-close="rejecting"
                    cancel-text="Odustani"
                    confirm-text="Potvrdi odbijanje"
                    title="Potvrdi odbijanje sesije"
                    tone="danger"
                    @confirm="confirmReject"
                  >
                    <p class="opacity-80 mb-2">
                      Ova radnja će označiti <b>celu sesiju kao odbijenu</b>. Svi ulovi biće
                      označeni kao <b>rejected</b>.
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

              <!-- GLASOVI RECENZENATA -->
              <div class="card bg-base-100 shadow">
                <div class="card-body space-y-3">
                  <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold">
                      Glasovi recenzenata
                    </h2>
                    <span :class="statusClass(sessionOverall)" class="badge">{{
                      sessionOverall
                    }}</span>
                  </div>

                  <ul class="space-y-2">
                    <li
                      v-for="r in reviews"
                      :key="r.id"
                      class="flex items-center justify-between gap-3"
                    >
                      <div class="flex items-center gap-2">
                        <div class="avatar">
                          <div class="w-7 rounded-full border border-base-300">
                            <img
                              :src="
                                r.reviewer?.avatar_url
                                  || r.reviewer?.profile?.avatar_url
                                  || '/icons/icon-64.png'
                              "
                              alt=""
                            >
                          </div>
                        </div>
                        <div class="leading-tight">
                          <div class="font-medium text-sm">
                            {{
                              r.reviewer?.display_name || r.reviewer?.name || `#${r.reviewer_id}`
                            }}
                          </div>
                          <div v-if="r.note" class="text-xs opacity-70 max-w-[40ch] truncate">
                            “{{ r.note }}”
                          </div>
                        </div>
                      </div>
                      <span :class="statusClass(r.status)" class="badge capitalize">{{
                        r.status
                      }}</span>
                    </li>
                    <li v-if="!reviews.length" class="opacity-70">
                      Nema nominacija za ovu sesiju.
                    </li>
                  </ul>
                </div>
              </div>

              <!-- POTVRDE (session-level confirmations, read-only) -->
              <div class="card bg-base-100 shadow mt-3">
                <div class="card-body space-y-3">
                  <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold">
                      Potvrde (session-level)
                    </h2>
                    <span :class="statusClass(sessionOverall)" class="badge">{{
                      sessionOverall
                    }}</span>
                  </div>
                  <ul class="space-y-2">
                    <li
                      v-for="c in confirmations"
                      :key="c.id"
                      class="flex items-center justify-between gap-3"
                    >
                      <div class="flex items-center gap-2">
                        <div class="avatar">
                          <div class="w-7 rounded-full border border-base-300">
                            <img
                              :src="
                                c.nominee?.avatar_url
                                  || c.nominee?.profile?.avatar_url
                                  || '/icons/icon-64.png'
                              "
                              alt=""
                            >
                          </div>
                        </div>
                        <div class="leading-tight">
                          <div class="font-medium text-sm">
                            {{
                              c.nominee?.display_name || c.nominee?.name || `#${c.nominee_user_id}`
                            }}
                          </div>
                          <div v-if="c.decided_at" class="text-xs opacity-70">
                            {{ new Date(c.decided_at).toLocaleString('sr-RS') }}
                          </div>
                        </div>
                      </div>
                      <span :class="statusClass(c.status as any)" class="badge capitalize">
                        {{ c.status }}
                      </span>
                    </li>
                    <li v-if="!confirmations.length" class="opacity-70">
                      Nema potvrda za ovu sesiju.
                    </li>
                  </ul>
                </div>
              </div>

              <div class="opacity-75 flex flex-wrap mt-1">
                <FishingCatchesTimeBadge :iso="data?.started_at" :with-time="true" />
                <span v-if="data?.location_name" class="badge badge-ghost">{{
                  data.location_name
                }}</span>
                <span v-if="data?.group?.name" class="badge badge-ghost">{{
                  data.group.name
                }}</span>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <span v-if="data?.status" class="badge badge-outline">{{ data.status }}</span>
              <button
                v-if="data?.status === 'open'"
                class="btn btn-error btn-sm"
                @click="closeOpen = true"
              >
                Zatvori sesiju
              </button>

              <SessionCloseDialog
                v-if="data?.id"
                v-model="closeOpen"
                :group-id="data.group?.id"
                :session-id="data.id"
                @closed="onClosed"
              />
            </div>
          </div>

          <div v-if="(data?.photos?.length || 0) > 0" class="mt-3 grid grid-cols-3 gap-2">
            <div
              v-for="(p, idx) in (data?.photos ?? []).slice(0, 3)"
              :key="idx"
              class="aspect-video rounded-xl overflow-hidden border border-base-300"
            >
              <img
                :src="p.url"
                alt=""
                class="w-full h-full object-cover"
                loading="lazy"
              >
            </div>
          </div>

          <div class="divider">
            Ulov
          </div>

          <div class="overflow-x-auto">
            <table class="table">
              <thead>
                <tr>
                  <th>Vrsta</th>
                  <th class="text-right">
                    Kom
                  </th>
                  <th class="text-right">
                    Težina (kg)
                  </th>
                  <th class="text-right">
                    Najveća (kg)
                  </th>
                  <th>Korisnik</th>
                  <th>Status</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in data?.catches || []" :key="row.id">
                  <td>{{ row.species_label || row.species || row.species_name || '-' }}</td>
                  <td class="text-right">
                    {{ Number(row.count || 0) }}
                  </td>
                  <td class="text-right">
                    {{ Number(row.total_weight_kg || 0).toFixed(1) }}
                  </td>
                  <td class="text-right">
                    {{ Number(row.biggest_single_kg || 0).toFixed(1) }}
                  </td>
                  <td>
                    <div class="flex items-center gap-2">
                      <div class="avatar">
                        <div class="w-6 rounded-full overflow-hidden border border-base-300">
                          <img :src="row.user?.profile?.avatar_url || '/icons/icon-64.png'">
                        </div>
                      </div>
                      <span class="text-sm">{{ row.user?.display_name || row.user?.name }}</span>
                    </div>
                  </td>
                  <td>
                    <span
                      :class="{
                        'badge-warning': row.status === 'pending',
                        'badge-success': row.status === 'approved',
                        'badge-error': row.status === 'rejected',
                      }"
                      class="badge"
                    >
                      {{ row.status }}
                    </span>
                  </td>
                  <td class="text-right">
                    <NuxtLink :to="`/catches/${row.id}`" class="btn btn-ghost btn-xs">
                      Detalji ulova
                    </NuxtLink>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <SessionLocationCard
          v-if="data?.id"
          v-model="coords"
          :session-id="data.id"
          :editable="canEditLocation"
          :auto-save="true"
          title="Lokacija sesije"
        />
      </div>
    </div>
  </div>
</template>
