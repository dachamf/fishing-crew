export type EventDTO = {
  id: number;
  group_id: number;
  title: string;
  location_name?: string | null;
  latitude?: number | null;
  longitude?: number | null;
  start_at: string; // ISO
  description?: string | null;
  status: string;
  created_at: string;
};
