import type { Profile, ThemeMode } from "~/types/api";

export function useProfile() {
  const { $api } = useNuxtApp() as any;
  const profile = useState<Profile | null>("profile", () => null);

  function normalize(p: any): Profile {
    const raw = p?.data ?? p ?? {};
    return {
      ...raw,
      theme: raw.theme ?? raw.settings?.theme ?? null,
    } as Profile;
  }

  const avatarBuster = useState<number>("avatarBuster", () => 0);

  async function loadMe() {
    const { data } = await $api.get("/v1/profile/me");
    profile.value = normalize(data);
    return profile.value;
  }

  async function updateProfile(body: Partial<Profile> & { theme?: ThemeMode }) {
    const { data } = await $api.patch("/v1/profile", body);
    profile.value = normalize(data);
    return profile.value;
  }

  async function uploadAvatar(file: File) {
    const fd = new FormData();
    fd.append("avatar", file);
    const { data } = await $api.post("/v1/profile/avatar", fd, {
      headers: { "Content-Type": "multipart/form-data" },
    });
    // očekujemo { avatar_url } ili kompletan profil
    profile.value = data?.avatar_url
      ? ({ ...(profile.value || {}), avatar_url: data.avatar_url } as Profile)
      : normalize(data);
    return profile.value;
  }

  async function deleteAvatar() {
    const { data } = await $api.delete("/v1/profile/avatar");
    profile.value = normalize(data);
    return profile.value;
  }

  async function changePassword(current_password: string, password: string) {
    await $api.patch("/v1/profile/password", {
      current_password,
      password,
      password_confirmation: password,
    });
  }
  return {
    profile,
    loadMe,
    updateProfile,
    uploadAvatar,
    deleteAvatar,
    changePassword,
    avatarBuster,
  };
}
