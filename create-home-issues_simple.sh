#!/usr/bin/env bash
set -euo pipefail

# Detect OWNER/REPO (ili preuzmi iz env-a)
OWNER="${OWNER:-}"
REPO="${REPO:-}"
ASSIGNEE="${ASSIGNEE:-}"   # ostavi prazno; ako hoćeš sebe: ASSIGNEE='@me'

if [[ -z "$OWNER" || -z "$REPO" ]]; then
  if gh repo view >/dev/null 2>&1; then
    NWO="$(gh repo view --json nameWithOwner -q .nameWithOwner 2>/dev/null || true)"
    if [[ -n "$NWO" ]]; then
      OWNER="${NWO%/*}"
      REPO="${NWO#*/}"
    else
      echo "Set OWNER and REPO or run inside a gh-linked repo."
      exit 1
    fi
  else
    echo "Set OWNER and REPO or run inside a gh-linked repo."
    exit 1
  fi
fi

REPO_FULL="$OWNER/$REPO"
echo "Target repository: $REPO_FULL"

# Auth check
if ! gh auth status >/dev/null 2>&1; then
  echo "Run: gh auth login"
  exit 1
fi

# Labels (idempotent)
ensure_label() {
  local name="$1" color="$2" desc="${3:-}"
  gh label create "$name" --repo "$REPO_FULL" --color "$color" ${desc:+--description "$desc"} >/dev/null 2>&1 || true
}
echo "Ensuring labels…"
ensure_label epic           8E44AD "Epic issue"
ensure_label home           1ABC9C "Homepage / dashboard"
ensure_label backend        E67E22 "Backend"
ensure_label frontend       3498DB "Frontend"
ensure_label design         2ECC71 "Design / UX"
ensure_label priority-high  E74C3C "High priority"
ensure_label priority-med   F1C40F "Medium priority"

# Temp dir sa kratkim imenom u trenutnom folderu (da izbegnemo /var/folders/... gigantske putanje)
TMP_DIR=".gh-issue-bodies-$$"
mkdir -p "$TMP_DIR"

write_body() {
  local file="$1"; shift
  cat > "$TMP_DIR/$file"
}

# --- TELA ISSUE-A (kratki fajlovi) ---
write_body "epic.md" <<'MD'
This EPIC covers the entire Home (dashboard) with 12 sections. Each section has brief acceptance criteria (AC). We implement iteratively (Phases 1–4). We’ll link sub-issues as they are created.

1) Greeting + Quick actions
2) Active session
3) “Needs my decision”
4) My season (snapshot)
5) Upcoming events (RSVP)
6) Recent activity (feed)
7) Mini-leaderboard
8) Map of recent sessions
9) Species trends
10) Badges / goals
11) Weather hint (lite)
12) Admin mini-panel

Global:
- Skeleton per card, empty states with CTA
- Lazy includes & short caching
- Mobile-first, good a11y
MD

write_body "p1.md" <<'MD'
Phase 1 (MVP):
1) Greeting + Quick actions
2) Active session
3) “Needs my decision” (bell + mini-list)
4) My season (snapshot)

BE:
- GET /v1/me
- GET /v1/sessions?status=open&user_id=me&include=photos,catches_count
- GET /v1/sessions/assigned-to-me?per_page=5&include=user,catches_count
- GET /v1/stats/season?group_id=G&year=Y&scope=me

AC:
- Skeleton while loading
- CTA depends on open session
- Bell count accurate; “View all” works
- Snapshot shows zeros when empty
MD

write_body "p2.md" <<'MD'
Phase 2:
5) Upcoming events (RSVP)
6) Recent activity (feed)
7) Mini-leaderboard (Top 5 + Biggest)

BE:
- GET /v1/events?from=today&limit=3&include=my_rsvp
- POST /v1/events/{id}/rsvp
- GET /v1/activity?group_id=G&limit=10
- GET /v1/leaderboard?group_id=G&year=Y&limit=5&include=user

AC:
- Optimistic RSVP + toast
- Feed sorted by time; links work
- Mini-LB ranking correct; link to /leaderboard
MD

write_body "p3.md" <<'MD'
Phase 3:
8) Map of recent sessions (pins)
9) Species trends (top 5 bar)
10) Badges / goals (list)

BE:
- GET /v1/sessions?user_id=me&limit=10&whereHasCatches=1&only=coords,title,id
- GET /v1/stats/species-top?group_id=G&year=Y&scope=me&limit=5
- GET /v1/achievements?scope=me

AC:
- Pin → /sessions/[id]
- Chart tooltip shows count + total kg
- Badges locked/unlocked; tooltip with description
MD

write_body "p4.md" <<'MD'
Phase 4:
11) Weather hint (lite)
12) Admin mini-panel (owner/mod)

BE:
- (Later) /v1/weather/summary?lat=…&lng=…
- GET /v1/me/roles?group_id=G

AC:
- Weather fails gracefully if no data
- Admin card visible only to owner/mod
MD

write_body "backend.md" <<'MD'
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
MD

write_body "ux.md" <<'MD'
Home UX standards:
- Skeleton per card
- SWR/refresh while tab active
- Mobile-first; desktop 2–3 cols
- Empty states with clear CTA
- A11y: aria-labels, badge contrast

AC:
- All loaders consistent
- Empty states not dead-ends
- Decent Lighthouse score
MD

# Helper – kreiraj issue iz fajla, vrati "NUMBER<TAB>URL"
create_from_file() {
  local title="$1"; shift
  local labels_csv="$1"; shift
  local file="$1"

  local args=(issue create --repo "$REPO_FULL" --title "$title" --body-file "$TMP_DIR/$file")
  # labels
  IFS=',' read -r -a labs <<< "$labels_csv"
  for l in "${labs[@]}"; do args+=(--label "$l"); done
  # assignee (ako zelis)
  if [[ -n "${ASSIGNEE}" ]]; then
    args+=(--assignee "$ASSIGNEE")
  fi

  local out url num
  out="$(gh "${args[@]}")"
  url="$(grep -Eo "https://github.com/${OWNER}/${REPO}/issues/[0-9]+" <<<"$out" | head -n1)"
  num="${url##*/}"
  echo -e "${num}\t${url}"
}

echo "Creating issues…"
read -r EPIC_NUM EPIC_URL < <(create_from_file "Home page – master plan (12 sections)" "epic,home,priority-high" "epic.md")
read -r P1_NUM   P1_URL   < <(create_from_file "Home — Phase 1 (MVP)"             "home,frontend,priority-high" "p1.md")
read -r P2_NUM   P2_URL   < <(create_from_file "Home — Phase 2"                   "home,frontend,priority-med"  "p2.md")
read -r P3_NUM   P3_URL   < <(create_from_file "Home — Phase 3"                   "home,frontend,priority-med"  "p3.md")
read -r P4_NUM   P4_URL   < <(create_from_file "Home — Phase 4"                   "home,frontend,priority-med"  "p4.md")
read -r BE_NUM   BE_URL   < <(create_from_file "Backend: /v1/home aggregate"      "backend,home,priority-high"  "backend.md")
read -r UX_NUM   UX_URL   < <(create_from_file "Home: skeletons, SWR & UX"        "design,frontend,home,priority-med" "ux.md")

echo "Linking sub-issues into Epic #$EPIC_NUM…"
gh issue edit "$EPIC_NUM" --repo "$REPO_FULL" --add-body $'\n'"### Sub-issues"$'\n'"- [ ] #$P1_NUM Home — Phase 1"$'\n'"- [ ] #$P2_NUM Home — Phase 2"$'\n'"- [ ] #$P3_NUM Home — Phase 3"$'\n'"- [ ] #$P4_NUM Home — Phase 4"$'\n'"- [ ] #$BE_NUM Backend: /v1/home aggregate"$'\n'"- [ ] #$UX_NUM Home: skeletons, SWR & UX"$'\n'

echo "Done!"
echo "Epic: $EPIC_URL"
echo "Phase 1: $P1_URL"
echo "Phase 2: $P2_URL"
echo "Phase 3: $P3_URL"
echo "Phase 4: $P4_URL"
echo "Backend aggregate: $BE_URL"
echo "UI/UX standards: $UX_URL"

# Po želji obriši temp fajlove:
rm -rf "$TMP_DIR"
