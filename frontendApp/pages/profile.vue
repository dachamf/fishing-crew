<script setup lang="ts">
import { toErrorMessage } from "~/utils/http";

const { mode, setTheme, resolved } = useTheme();
const {
  profile,
  updateProfile,
  uploadAvatar,
  deleteAvatar,
  changePassword,
  avatarBuster
} = useProfile();
const { success, error } = useToast();

const saving = ref(false);
const passSaving = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
const avatarBusy = ref(false);
const current = ref('');
const next = ref('');
const next2 = ref('');
const busy = ref(false);

const placeholder = 'https://placehold.co/96x96?text=Avatar'
const avatarUrl = computed(() => {
  const url = profile.value?.avatar_url;
  if (!url) return placeholder;
  const sep = url.includes('?') ? '&' : '?';
  return `${url}${sep}v=${avatarBuster.value}`;
});

// Forma profila (prilagodi polja onome što API očekuje)
const form = reactive({
  display_name: '',
  bio: ''
});

watch(profile, (p) => {
  form.display_name = p?.display_name ?? '';
  form.bio = p?.bio ?? '';
}, {
  immediate: true,
});

// SAVE PROFILE
async function saveProfile() {
  try {
    saving.value = true;
    await updateProfile({
      display_name: form.display_name,
      bio: form.bio,
      /* + ostala polja */
    });
    success('Profile updated');
  } catch (e: any) {
    error(toErrorMessage(e));
  } finally {
    saving.value = false;
  }
}

// Avatar
async function onPickAvatar(e: Event) {
  const f = (e.target as HTMLInputElement).files?.[0];
  if (!f) return;
  avatarBusy.value = true;
  try {
    await uploadAvatar(f);
    success('Avatar updated');
  } catch (e: any) {
    error(toErrorMessage(e));
  } finally {
    avatarBusy.value = false;
  }
}

async function onDeleteAvatar() {
  avatarBusy.value = true;
  try {
    await deleteAvatar();
    success('Avatar removed');
  } catch (e: any) {
    error(toErrorMessage(e));
  } finally {
    avatarBusy.value = false;
  }
}

// Password
async function onChangePassword() {
  if (next.value !== next2.value) return error('Passwords do not match');
  passSaving.value = true;
  try {
    await changePassword(current.value, next.value);
    current.value = next.value = next2.value = '';
    success('Password updated');
  } catch (e: any) {
    error(toErrorMessage(e));
  } finally {
    passSaving.value = false ;
  }
}

async function choose(t:'light'|'dark'|'system') {
  busy.value = true
  try { await setTheme(t) } finally { busy.value = false }
}
</script>

<template>
  <div class="max-w-3xl mx-auto p-6 space-y-8">
    <h1 class="text-2xl font-semibold">Profile</h1>

    <!-- Avatar -->
    <section class="card bg-base-100 shadow p-4">
      <h2 class="font-medium mb-3 text-info">Avatar</h2>
      <div class="flex items-center gap-4">
        <img
          :key="avatarUrl"
          :src="avatarUrl"
          class="w-24 h-24 rounded-full object-cover border"
          alt="avatar"
        />
        <div class="flex gap-2">
          <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="onPickAvatar" />
          <button class="btn btn-primary" :disabled="avatarBusy" @click="fileInput?.click()">Upload</button>
          <button class="btn" :disabled="avatarBusy" @click="onDeleteAvatar">Remove</button>
        </div>
      </div>
    </section>

    <!--    Tema (izmena tema) -->
    <section class="card bg-base-100 shadow p-4">
      <h2 class="font-medium mb-3">Tema</h2>
      <div class="flex items-center gap-3">
        <button class="btn" :class="mode==='light' ? 'btn-primary' : 'btn-ghost'" :disabled="busy" @click="choose('light')">Light</button>
        <button class="btn" :class="mode==='dark'  ? 'btn-primary' : 'btn-ghost'" :disabled="busy" @click="choose('dark')">Dark</button>
        <button class="btn" :class="mode==='system'? 'btn-primary' : 'btn-ghost'" :disabled="busy" @click="choose('system')">System</button>
        <span class="opacity-70 text-sm ml-2">Trenutno: {{ mode }} ({{ mode==='system' ? '→ ' + (resolved) : 'fixed' }})</span>
      </div>
    </section>

    <!-- Profile info -->
    <section class="card bg-base-100 shadow p-4">
      <h2 class="font-medium mb-3">Profile info</h2>
      <div class="grid md:grid-cols-2 gap-4">
        <label class="form-control">
          <span class="label-text text-info">Display name</span>
          <input v-model="form.display_name" class="input input-bordered" placeholder="Nickname" />
        </label>
        <label class="form-control md:col-span-2">
          <span class="label-text text-info">Bio</span><br>
          <textarea v-model="form.bio" class="textarea textarea-bordered" rows="3" />
        </label>
      </div>
      <div class="mt-4">
        <button class="btn btn-primary" :disabled="saving" @click="saveProfile">
          {{ saving ? 'Saving...' : 'Save' }}
        </button>
      </div>
    </section>

    <!-- Password -->
    <section class="card bg-base-100 shadow p-4">
      <h2 class="font-medium mb-3">Change password</h2>
      <div class="grid md:grid-cols-3 gap-4">
        <label class="form-control">
          <span class="label-text">Current password</span>
          <input v-model="current" type="password" class="input input-bordered" />
        </label>
        <label class="form-control">
          <span class="label-text">New password</span>
          <input v-model="next" type="password" class="input input-bordered" />
        </label>
        <label class="form-control">
          <span class="label-text">Repeat new password</span>
          <input v-model="next2" type="password" class="input input-bordered" />
        </label>
      </div>
      <div class="mt-4">
        <button class="btn btn-outline" :disabled="passSaving" @click="onChangePassword">
          {{ passSaving ? 'Saving...' : 'Update password' }}
        </button>
      </div>
    </section>+
  </div>
</template>
