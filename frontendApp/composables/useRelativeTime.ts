export function useRelativeTime() {
  const rtf = new Intl.RelativeTimeFormat("sr-RS", { numeric: "auto" });
  return (iso?: string | Date | null) => {
    if (!iso)
      return "";
    const t = typeof iso === "string" ? new Date(iso).getTime() : iso.getTime();
    const diffMs = t - Date.now();
    const minutes = Math.round(diffMs / 60000);
    if (Math.abs(minutes) < 60)
      return rtf.format(minutes, "minute");
    const hours = Math.round(minutes / 60);
    if (Math.abs(hours) < 24)
      return rtf.format(hours, "hour");
    const days = Math.round(hours / 24);
    return rtf.format(days, "day");
  };
}
