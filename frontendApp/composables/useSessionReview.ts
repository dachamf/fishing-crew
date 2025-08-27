export function useSessionReview() {
  const { $api } = useNuxtApp() as any;

  const review = (sessionId: number, status: "approved" | "rejected", note?: string) =>
    $api.post(`/v1/sessions/${sessionId}/review`, { status, note });

  const assignedToMe = (page = 1) => $api.get("/v1/sessions/assigned-to-me", { params: { page } });

  return { review, assignedToMe };
}
