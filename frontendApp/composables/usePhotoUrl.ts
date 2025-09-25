// composables/usePhotoUrl.ts
// FE stil: double quotes, ; na kraju linije, CamelCase fajl.

type SizeKey = "sm" | "md" | "lg" | undefined;

export function usePhotoUrl() {
  /**
   * build() vraća URL za <img :src="...">.
   * Podrazumeva API rutu /api/photos/{id}?size=...
   * Ako ipak želiš direktno Storage URL (photo.url) — vidi komentar ispod.
   */
  const build = (photo: { id: number; url?: string }, size?: SizeKey) => {
    if (!photo?.id) {
      return "";
    }
    const q = size ? `?size=${size}` : "";
    return `/api/photos/${photo.id}${q}`;

    // Alternativa: koristi direktan storage URL iz photo.url + query param
    // if (!photo?.url) return "";
    // return size ? `${photo.url}?size=${size}` : photo.url;
  };

  /**
   * Korisno za srcset (responsive images).
   */
  const srcset = (photo: { id: number }, keys: SizeKey[] = ["sm", "md", "lg"]) => {
    const entries = keys
      .filter(Boolean)
      .map(
        k =>
          `/api/photos/${photo.id}?size=${k} ${k === "sm" ? "320w" : k === "md" ? "800w" : "1600w"}`,
      );
    return entries.join(", ");
  };

  return { build, srcset };
}
