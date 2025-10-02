export default defineEventHandler(async (event) => {
  const q = String(getQuery(event).q ?? "").trim();
  const limit = Number(getQuery(event).limit ?? 8);
  const countrycodes = String(getQuery(event).countrycodes ?? "rs");
  if (q.length < 3)
    return [];

  const url = new URL("https://nominatim.openstreetmap.org/search");
  url.searchParams.set("format", "jsonv2");
  url.searchParams.set("addressdetails", "1");
  url.searchParams.set("q", q);
  url.searchParams.set("limit", String(Math.min(Math.max(limit, 1), 10)));
  if (countrycodes)
    url.searchParams.set("countrycodes", countrycodes);

  const res = await fetch(url.toString(), {
    headers: {
      "User-Agent": "FishermenCrew/1.0 (contact: admin@fishermen.app)",
      "Accept": "application/json",
    },

    signal: AbortSignal.timeout?.(5000),
  });
  if (!res.ok)
    throw createError({ statusCode: res.status, statusMessage: "Nominatim unreachable" });

  const data = (await res.json()) as Array<{
    place_id: number;
    display_name: string;
    lat: string;
    lon: string;
    type?: string;
    class?: string;
  }>;

  // 👇 normalizacija
  return data.map(d => ({
    id: d.place_id,
    label: d.display_name,
    lat: Number(d.lat),
    lon: Number(d.lon),
    type: d.type,
    cls: d.class,
  }));
});
