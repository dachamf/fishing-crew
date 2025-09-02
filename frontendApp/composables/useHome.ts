import type { HomePayload } from "~/types/api";

export function useHome(params: {
  groupId: number | null | undefined;
  year: number;
  include?: string[];
}) {
  const { $api } = useNuxtApp() as any;

  const includeList = computed(() =>
    (params.include && params.include.length
      ? params.include
      : [
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
        ]
    ).join(","),
  );

  const key = computed(() => `home:${params.groupId ?? 0}:${params.year}:${includeList.value}`);

  const { data, pending, error, refresh } = useAsyncData<HomePayload>(
    key,
    async () => {
      const res = await $api.get("/v1/home", {
        params: {
          group_id: params.groupId ?? undefined,
          year: params.year,
          include: includeList.value,
        },
        withCredentials: true,
      });
      return res.data as HomePayload;
    },
    { server: false, watch: [() => params.groupId, () => params.year, includeList] },
  );

  return { data, pending, error, refresh };
}
