export type ThemeMode = "light" | "dark" | "system";
export type CatchStatus = "pending" | "approved" | "rejected";
export type SpeciesRow = { label: string; cnt: number; total_kg: number };
export type Role = "owner" | "mod" | "member" | string;
export type Coords = { lat: number | null; lng: number | null };
export type MapSession = {
  id: number;
  title?: string;
  started_at?: string;
  latitude?: number | null;
  longitude?: number | null;
};

export type Badge = {
  code: string;
  title: string;
  desc?: string;
  unlocked: boolean;
  value?: number;
};

export type ConfirmationStatus = "pending" | "approved" | "rejected" | "changes_requested";

export type Profile = {
  id: number;
  user_id: number;
  display_name?: string | null;
  birth_year?: number | null;
  location?: string | null;
  favorite_species?: string | null;
  gear?: string | null;
  bio?: string | null;
  avatar_url?: string | null;
  settings?: { theme?: ThemeMode; [k: string]: any } | null;
  theme?: ThemeMode | null;
  created_at?: string;
  updated_at?: string;
};

// frontendApp/types/api.ts

/** Primitives */
export type ID = number;
export type ISODate = string;
export type Kg = number;

/** Status enums */
export type SessionStatus = "open" | "closed";
export type RSVP = "yes" | "undecided" | "no";

/** Pagination (naš “flat” oblik) */
export type PaginationMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

export type ApiList<T> = {
  items: T[];
  meta?: PaginationMeta;
};

/** Laravel paginator raw oblik (kada dođe direktno iz API-ja) */
export type LaravelPagination<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
};

/** Pomagač: laravel -> naš ApiList<T> */
export function mapLaravel<T>(p: LaravelPagination<T>): ApiList<T> {
  const { current_page, last_page, per_page, total } = p;
  return { items: p.data, meta: { current_page, last_page, per_page, total } };
}

/** User / Profile / Group (lite) */
export type UserProfileLite = {
  id: ID;
  user_id: ID;
  display_name?: string;
  avatar_path?: string;
  avatar_url?: string;
};

export type UserLite = {
  id: ID;
  name: string;
  display_name?: string;
  avatar_url?: string | null;
  profile?: UserProfileLite | null;
};

export type SessionReviewStatus = "pending" | "approved" | "rejected";

export type SessionReview = {
  id: ID;
  session_id: ID;
  reviewer_id: ID;
  status: SessionReviewStatus;
  note?: string | null;
  reviewer?: UserLite;
  created_at?: string;
  updated_at?: string;
};

export type GroupLite = {
  id: ID;
  name: string;
  season_year?: number;
  role?: string; // pivot role
};

/** Species */
export type SpeciesItem = {
  id?: number;
  key?: string;
  code?: string;
  slug?: string;
  name_sr?: string;
  label?: string;
};

/** Photos (FE koristi url) */
export type PhotoLite = {
  id: ID;
  url: string;
  ord?: number | null;
};

/** Event (lite) */
export type EventLite = {
  id: ID;
  title: string;
  start_at?: ISODate;
  location_name?: string;
  latitude?: number | null;
  longitude?: number | null;
  status?: string;
};

/** Catch confirmation */
export type CatchConfirmation = {
  id: number;
  catch_id: number;
  confirmed_by: number;
  status: ConfirmationStatus;
  note?: string;
  created_at?: ISODate;
  updated_at?: ISODate;
};

/** FishingCatch */
export type FishingCatch = {
  id: ID;
  group_id: ID;
  user_id: ID;
  event_id?: ID | null;
  session_id?: ID | null;

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
};

/** FishingSession (lite vs full) */
export type FishingSessionLite = {
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
};

export type NewCatchPayload = {
  group_id: ID;
  species?: string;
  species_id?: number;
  species_name?: string;
  count: number;
  total_weight_kg?: Kg;
  biggest_single_kg?: Kg;
  note?: string;
  season_year?: number;
  session_id?: ID | null;
  event_id?: ID | null;
  caught_at?: ISODate;
};

export type SessionListParams = {
  search?: string;
  group_id?: ID;
  user_id?: ID;
  season_year?: number;
  status?: SessionStatus;
  page?: number;
  per_page?: number;
  include?: string; // npr. "catches.user,photos"
};

export type FishingSession = {
  catches?: FishingCatch[];
  reviews?: SessionReview[];
} & FishingSessionLite;

/** /v1/me payload */
export type Me = {
  id: ID;
  name: string;
  email: string;
  profile?: UserProfileLite;
  groups: GroupLite[];
};

export type GroupMember = {
  id: number;
  name?: string;
  display_name?: string;
  avatar_url?: string;
  profile?: {
    avatar_url?: string;
    display_name?: string;
  };
};

export type CloseAndNominatePayload = {
  reviewer_ids: number[];
};

export type LeaderboardItem = {
  rank: number;
  user: {
    id: number;
    name?: string;
    display_name?: string;
    avatar_url?: string | null;
  } | null;
  catches_count: number;
  pieces_total: number;
  weight_total: number;
  biggest: number;
  sessions_total: number;
  avg_piece_kg: number;
};

export type LeaderboardResponse = {
  items: LeaderboardItem[];
  meta: {
    total: number;
    page: number;
    per_page: number;
    order_by: string;
  };
};

export type FeedItem = {
  type: string;
  id: number;
  at: string;
  title: string;
  by?: { id: number };
  url: string;
};

export type Rsvp = "yes" | "undecided" | "no";
export type EventItem = {
  id: number;
  title: string;
  start_at: string;
  my_rsvp?: { status: Rsvp } | null;
};

export type Row = {
  user_id: number;
  total_weight_kg: number;
  catches_count: number;
  biggest_single_kg: number;
  user?: UserLite;
};
export type Biggest = {
  id: number;
  user_id: number;
  session_id: number;
  weight_kg: number;
  user?: UserLite;
};

export type CatchItem = {
  id: number;
  session_id: number | null;
  group_id: number;
  user_id: number;
  species?: { id: number; name_sr: string; name_latin?: string };
  species_id?: number | null;
  count: number;
  total_weight_kg?: number | null;
  biggest_single_kg?: number | null;
  caught_at?: string | null;
  note?: string | null;
  status: "pending" | "approved" | "rejected";
  photos?: string[];
  session?: any;
};

export type WeatherSummary = {
  temp_c?: number | null;
  temp_min_c?: number | null;
  temp_max_c?: number | null;
  wind_kph?: number | null;
  wind_gust_kph?: number | null;
  wind_dir?: string | null;
  precip_mm?: number | null;
  condition?: string | null;
  icon_url?: string | null;
  icon_name?: string | null;
  when_iso?: string | null;
  source?: string | null;
  is_day?: boolean | null;
};

export type HomeMe = {
  id: number;
  name?: string | null;
  display_name?: string | null;
  avatar_url?: string | null;
  roles?: string[];
};

export type HomeOpenSession = {
  id: number;
  title?: string | null;
  started_at?: string | null;
  catches_count?: number;
  photos?: PhotoLite[];
  latitude?: number | null; // BE dopuna u secOpenSession
  longitude?: number | null; // —
};

export type HomeAssignedItem = {
  id: number;
  title?: string | null;
  started_at?: string | null;
  catches_count?: number;
  user?: {
    id: number;
    name?: string | null;
    display_name?: string | null;
    avatar_url?: string | null;
  };
};
export type HomeAssigned = { items: HomeAssignedItem[]; meta: { total: number } };

export type HomeSeasonStats = {
  sessions: number;
  catches: number;
  total_weight_kg: number;
  biggest_single_kg: number;
};

export type HomeEvent = {
  id: number;
  title?: string | null;
  start_at?: string | null;
  my_rsvp?: {
    status: Rsvp;
  } | null;
};
export type ActivityType
  = | "catch_added"
    | "session_opened"
    | "session_approved"
    | "session_rejected";
export type HomeActivityItem = {
  id: number;
  type: ActivityType;
  ref_id?: number | null;
  user_id?: number | null;
  created_at?: string | null;
  meta?: { url?: string } | null;
};

export type HomeMiniLeaderboardRow = {
  user: {
    id: number;
    name?: string | null;
    display_name?: string | null;
    avatar_url?: string | null;
  };
  catches_count: number;
  total_weight_kg: number;
  biggest_single_kg: number;
};
export type HomeMiniLeaderboard = {
  weightTop: HomeMiniLeaderboardRow[];
  biggestTop: HomeMiniLeaderboardRow[];
};

export type HomeMapPoint = {
  id: number;
  title?: string | null;
  latitude: number;
  longitude: number;
  started_at?: string | null;
};

export type HomeSpeciesTrend = { label: string; cnt: number; total_kg: number };

export type HomeAchievement = {
  id: number;
  key?: string;
  title?: string;
  unlocked_at?: string | null;
  meta?: Record<string, any>;
};

export type HomeAdmin = { canManage: boolean; shortcuts: { label: string; href: string }[] };

export type HomePayload = Partial<{
  me: HomeMe;
  open_session: HomeOpenSession | null;
  assigned: HomeAssigned;
  season_stats: HomeSeasonStats;
  events: HomeEvent[];
  activity: HomeActivityItem[];
  mini_leaderboard: HomeMiniLeaderboard;
  map: HomeMapPoint[];
  species_trends: HomeSpeciesTrend[];
  achievements: HomeAchievement[];
  admin: HomeAdmin;
}>;
