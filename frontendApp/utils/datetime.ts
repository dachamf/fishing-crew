// utils/datetime.ts

/** Bezbedno parsiranje u Date. Prihvata Date | ISO string | broj (ms). */
export function toDateSafe(input?: Date | string | number | null): Date | undefined {
  if (input == null)
    return undefined;
  const d = input instanceof Date ? new Date(input.getTime()) : new Date(input);
  return Number.isNaN(d.getTime()) ? undefined : d;
}

/** "YYYY-MM-DDTHH:mm" za <input type="datetime-local"> (lokalna vremenska zona) */
export function toDatetimeLocal(d: Date | string): string {
  const x = toDateSafe(d);
  if (!x)
    return "";
  const pad = (n: number) => String(n).padStart(2, "0");
  // Sve je u *lokalnom* vremenu korisnika (što input očekuje)
  const y = x.getFullYear();
  const M = pad(x.getMonth() + 1);
  const D = pad(x.getDate());
  const h = pad(x.getHours());
  const m = pad(x.getMinutes());
  return `${y}-${M}-${D}T${h}:${m}`;
}

/**
 * Pretvara vrednost iz <input type="datetime-local"> (lokalno) u ISO (UTC).
 * Ako dobije undefined/prazno, vraća undefined umesto "Invalid Date".
 */
export function datetimeLocalToISO(s?: string): string | undefined {
  if (!s)
    return undefined;
  const d = new Date(s); // tumači se kao lokalno vreme
  return Number.isNaN(d.getTime()) ? undefined : d.toISOString(); // ISO je uvek UTC
}

/** Stabilan prikaz za SSR: formatira u UTC da SSR/CSR budu isti. */
export function isoToDisplayUTC(iso?: string | Date | null, locale = "sr-RS"): string {
  const d = toDateSafe(iso || undefined);
  if (!d)
    return "—";
  try {
    return new Intl.DateTimeFormat(locale, {
      dateStyle: "medium",
      timeStyle: "short",
      timeZone: "UTC",
    }).format(d);
  }
  catch {
    return "—";
  }
}

/** Lokalni prikaz (koristi se tek posle hydration-a). */
export function isoToDisplayLocal(iso?: string | Date | null, locale = "sr-RS"): string {
  const d = toDateSafe(iso || undefined);
  if (!d)
    return "—";
  try {
    return new Intl.DateTimeFormat(locale, {
      dateStyle: "medium",
      timeStyle: "short",
    }).format(d);
  }
  catch {
    return "—";
  }
}

/** Trenutno vreme kao ISO (UTC). Korisno za default vrednosti. */
export function nowISO(): string {
  return new Date().toISOString();
}

/** True ako je datum validan. */
export function isValidDate(input?: Date | string | number | null): boolean {
  const d = toDateSafe(input);
  return !!d;
}
