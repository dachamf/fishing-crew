export async function useEventAttendees(
  eventLike: number | { id: number },
  rsvp?: "yes" | "undecided" | "no",
) {
  const { $api } = useNuxtApp() as any;
  const id = typeof eventLike === "number" ? eventLike : Number(eventLike?.id);
  return $api.get(`/v1/events/${id}/attendees`, { query: rsvp ? { rsvp } : undefined });
}
