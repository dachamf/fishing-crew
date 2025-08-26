export type ThemeMode = 'light' | 'dark' | 'system'

export interface Profile {
  id: number
  user_id: number
  display_name?: string | null
  birth_year?: number | null
  location?: string | null
  favorite_species?: string | null
  gear?: string | null
  bio?: string | null
  avatar_url?: string | null
  settings?: { theme?: ThemeMode; [k: string]: any } | null
  theme?: ThemeMode | null
  created_at?: string
  updated_at?: string
}

// frontendApp/types/api.ts

/** Primitives */
export type ID = number;
export type ISODate = string;
export type Kg = number;

/** Status enums */
export type CatchStatus = 'pending' | 'approved' | 'rejected';
export type SessionStatus = 'open' | 'closed';
export type RSVP = 'yes' | 'undecided' | 'no';

/** Pagination (naš “flat” oblik) */
export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface ApiList<T> {
  items: T[];
  meta?: PaginationMeta;
}

/** Laravel paginator raw oblik (kada dođe direktno iz API-ja) */
export interface LaravelPagination<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
}

/** Pomagač: laravel -> naš ApiList<T> */
export function mapLaravel<T>(p: LaravelPagination<T>): ApiList<T> {
  const { current_page, last_page, per_page, total } = p;
  return { items: p.data, meta: { current_page, last_page, per_page, total } };
}

/** User / Profile / Group (lite) */
export interface UserProfileLite {
  id: ID;
  user_id: ID;
  display_name?: string;
  avatar_path?: string;
  avatar_url?: string;
}

export interface UserLite {
  id: ID;
  name: string;
  display_name?: string;
  avatar_url?: string;
  profile?: UserProfileLite;
}

export interface GroupLite {
  id: ID;
  name: string;
  season_year?: number;
  role?: string; // pivot role
}

/** Species */
export interface SpeciesItem {
  id?: number
  key?: string
  code?: string
  slug?: string
  name_sr?: string
  label?: string
}

/** Photos (FE koristi url) */
export interface PhotoLite {
  id: ID;
  url: string;
}

/** Event (lite) */
export interface EventLite {
  id: ID;
  title: string;
  start_at?: ISODate;
  location_name?: string;
  latitude?: number | null;
  longitude?: number | null;
  status?: string;
}

/** Catch confirmation */
export interface CatchConfirmation {
  id: ID;
  catch_id: ID;
  confirmed_by: ID;
  status: 'approved' | 'rejected';
  note?: string;
  created_at?: ISODate;
}

/** FishingCatch */
export interface FishingCatch {
  id: ID;
  group_id: ID;
  user_id: ID;
  event_id?: ID | null;
  fishing_session_id?: ID | null;

  // species različiti izvori + normalized label
  species?: string | { name?: string };
  species_name?: string;
  species_id?: number;
  species_label?: string;

  count: number;
  total_weight_kg?: Kg;
  biggest_single_kg?: Kg;
  note?: string;
  status: CatchStatus;
  season_year?: number;
  caught_at?: ISODate;

  user?: UserLite;
  group?: GroupLite;
  session?: FishingSessionLite;
  confirmations?: CatchConfirmation[];
}

/** FishingSession (lite vs full) */
export interface FishingSessionLite {
  id: ID;
  title?: string;
  started_at?: ISODate;
  ended_at?: ISODate | null;
  location_name?: string;
  latitude?: number | null;
  longitude?: number | null;
  status: SessionStatus;
  season_year?: number;
  water_body?: string;

  user?: UserLite;
  group?: GroupLite;
  event?: EventLite;

  // accessor “photos” (prve 3 fotke preko ulova)
  photos?: PhotoLite[];
}


export interface NewCatchPayload {
  group_id: ID
  species?: string
  species_id?: number
  species_name?: string
  count: number
  total_weight_kg?: Kg
  biggest_single_kg?: Kg
  note?: string
  season_year?: number
  session_id?: ID | null
  event_id?: ID | null
  caught_at?: ISODate
}

export interface SessionListParams {
  search?: string
  group_id?: ID
  user_id?: ID
  season_year?: number
  status?: SessionStatus
  page?: number
  per_page?: number
  include?: string // npr. "catches.user,photos"
}

export interface FishingSession extends FishingSessionLite {
  catches?: FishingCatch[]; // kada se traži include=catches(.user)
}

/** /v1/me payload */
export interface Me {
  id: ID;
  name: string;
  email: string;
  groups: GroupLite[];
}

export interface GroupMember {
  id: number;
  name?: string;
  display_name?: string;
  avatar_url?: string;
  profile?: {
    avatar_url?: string;
    display_name?: string
  }
}
