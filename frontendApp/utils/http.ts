export function toErrorMessage(err: any): string {
  const r = err?.response;
  const data = r?.data ?? err;
  const status = r?.status ?? err?.status;

  // Laravel 422: { message, errors: { field: [msg...] } }
  if (status === 422 && data?.errors) {
    const msgs = Object.values<string[]>(data.errors as any).flat();
    return (msgs.length ? msgs.join(" · ") : data.message) || "Validation failed";
  }
  // ostali slučajevi
  return data?.message || err?.message || "Unexpected error";
}
