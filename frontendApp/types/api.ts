export type ThemeMode = 'light' | 'dark'

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
  /** flatten iz API-ja ili iz settings.theme */
  theme?: ThemeMode | null
  created_at?: string
  updated_at?: string
}
