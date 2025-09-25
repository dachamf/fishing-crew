export function useSessionReview() {
  const { $api } = useNuxtApp() as any;

  const review = (sessionId: number, status: "approved" | "rejected", note?: string) =>
    $api.post(`/v1/sessions/${sessionId}/review`, { status, note });

  const assignedToMe = async (page = 1, perPage = 5) => {
    const res = await $api.get("/v1/sessions/assigned-to-me", {
      params: { page, per_page: perPage },
    });
    const payload = res.data ?? {};
    const items = payload.data ?? payload.items ?? payload;
    const meta = payload.meta ?? null;
    return { items, meta };
  };

  /** NEW: potvrda preko tokena (bez login-a) */
  const confirmByToken = (sessionId: number, token: string, decision: "approved" | "rejected") =>
    $api.post(`/v1/sessions/${sessionId}/confirm/${token}`, { decision });

  return {
    review,
    assignedToMe,
    confirmByToken, // ⬅️ export
  };
}
