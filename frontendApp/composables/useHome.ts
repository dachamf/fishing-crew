import type { MaybeRef } from "vue";

import { computed, unref } from "vue";

import type { HomePayload } from "~/types/api";

export const HOME_DEFAULT_INCLUDE = [
  "me",
  "open_session",
  "assigned",
  "season_stats",
  "events",
  "activity",
  "mini_leaderboard",
  "map",
  "species_trends",
  "achievements",
  "admin",
] as const;

export function homeKey(
  groupId: number | null | undefined,
  year: number,
  include?: string[] | string,
) {
  const inc = Array.isArray(include) ? include.join(",") : include || HOME_DEFAULT_INCLUDE.join(",");
  return `home:${groupId ?? 0}:${year}:${inc}`;
}

export function useHome(params: {
  groupId: MaybeRef<number | null | undefined>;
  year: MaybeRef<number>;
  include?: string[];
}) {
  const { $api } = useNuxtApp() as any;

  const gid = computed(() => unref(params.groupId) ?? null);
  const yr = computed(() => unref(params.year));
  const includeStr = computed(() =>
    (params.include?.length ? params.include : HOME_DEFAULT_INCLUDE).join(","),
  ); // 👈

  const key = computed(() => homeKey(gid.value, yr.value, includeStr.value)); // 👈

  const { data, pending, error, refresh } = useAsyncData<HomePayload>(
    key, // stabilan ključ
    async () => {
      const res = await $api.get("/v1/home", {
        params: { group_id: gid.value ?? undefined, year: yr.value, include: includeStr.value },
        withCredentials: true,
      });
      return res.data as HomePayload;
    },
    {
      server: true,
      lazy: false,
      watch: [gid, yr, includeStr],
      // (opciono) smanji “stale” period da RSVP brže dođe na Home i bez ručnog refresh-a
      // staleTime: 10_000,
      // dedupe: true,
    },
  );

  return { data, pending, error, refresh, key };
}
