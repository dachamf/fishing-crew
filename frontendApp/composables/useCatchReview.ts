import type { FishingCatch } from "~/types/api";

export function useCatchReview() {
  const { $api } = useNuxtApp() as any;

  const assignedToMe = async (page = 1, perPage = 20) => {
    const res = await $api.get("/v1/review/assigned", {
      params: { page, per_page: perPage },
    });
    const payload = res.data ?? {};
    const items = payload.data ?? payload.items ?? payload;
    const meta = payload.meta ?? null;
    return { items: items as FishingCatch[], meta };
  };

  const confirm = (catchId: number, status: "approved" | "rejected", note?: string) =>
    $api.post(`/v1/catches/${catchId}/confirmations`, { status, note });

  const withdraw = (catchId: number) => $api.post(`/v1/catches/${catchId}/confirmations/withdraw`);

  return {
    assignedToMe,
    confirm,
    withdraw,
  };
}
