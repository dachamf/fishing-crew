import type { EventItem, Rsvp } from "~/types/api";

export function useEvents() {
  const items = ref<EventItem[]>([]);
  const loading = ref(false);

  async function fetchUpcoming(limit = 3, groupId?: number) {
    loading.value = true;
    try {
      const { data } = await useFetch<EventItem[]>("/api/v1/events", {
        query: { from: "today", limit, include: "my_rsvp", group_id: groupId },
        credentials: "include",
      });
      items.value = data.value || [];
    }
    finally {
      loading.value = false;
    }
  }

  async function rsvp(eventId: number, status: Rsvp) {
    const ev = items.value.find(e => e.id === eventId);
    const prev = structuredClone(ev?.my_rsvp ?? null);

    if (ev)
      ev.my_rsvp = { status }; // optimistic

    try {
      const res = await $fetch<{ my_rsvp?: { status: Rsvp } }>(`/api/v1/events/${eventId}/rsvp`, {
        method: "POST",
        body: { status },
        credentials: "include",
      });
      if (ev)
        ev.my_rsvp = res.my_rsvp ?? { status };
      useToast().success("RSVP sačuvan ✓");
    }
    catch (e) {
      if (ev)
        ev.my_rsvp = prev;
      useToast().error(toErrorMessage(e) || "Greška pri RSVP-u");
    }
  }

  return { items, loading, fetchUpcoming, rsvp };
}
