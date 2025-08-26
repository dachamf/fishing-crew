export function toDatetimeLocal(d: Date | string) {
  const x = typeof d === "string" ? new Date(d) : d;
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${x.getFullYear()}-${pad(x.getMonth() + 1)}-${pad(x.getDate())}T${pad(x.getHours())}:${pad(x.getMinutes())}`;
}

export function datetimeLocalToISO(s?: string) {
  return s ? new Date(s).toISOString() : undefined;
}
