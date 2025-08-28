Goal: aggregate endpoint for Home sections via include param.

GET /v1/home?group_id=G&year=Y&include=me,open_session,assigned,season_stats,events,activity,mini_leaderboard,map,species_trends,achievements,admin

Fields:
- me { id, name, display_name, avatar_url, roles[] }
- open_session { id, title, started_at, photos[], catches_count }
- assigned { items:[{id,title,started_at,catches_count,user{…}}], meta{total} }
- season_stats { sessions, catches, total_weight_kg, biggest_single_kg }
- events [...]
- activity [...]
- mini_leaderboard { weightTop:[...], biggestTop:[...] }
- map [...]
- species_trends [...]
- achievements [...]
- admin { canManage, shortcuts }

AC:
- Lazy include per section
- Limits (events 3, assigned 5…)
- Cache 30–60s (key: user, group, year, include)
- Tests for include combos
