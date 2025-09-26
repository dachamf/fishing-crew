<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\FishingCatch;
use App\Models\FishingSession;
use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class HomeController extends Controller
{
    // Default limiti po sekciji (možeš promeniti/parametrizovati kasnije)
    private const LIMIT_EVENTS = 3;
    private const LIMIT_ASSIGNED = 5;
    private const LIMIT_ACTIVITY = 10;
    private const LIMIT_LB = 5;
    private const LIMIT_MAP = 10;
    private const LIMIT_SPECIES = 5;

    /** GET /v1/home?group_id=G&year=Y&include=... */
    public function show(Request $r): JsonResponse
    {
        $user = $r->user();
        $uid = (int)$user->id;
        $group = $r->integer('group_id') ?: null;
        $year = $r->integer('year') ?: (int)now()->year;

        if (!$group) {
            $pivot = DB::table('group_user')
                ->where('user_id', $uid)->first();
            if ($pivot) {
                $group = $pivot->group_id;
            }
        }

        $allowed = [
            'me', 'open_session', 'assigned', 'season_stats',
            'events', 'activity', 'mini_leaderboard', 'map',
            'species_trends', 'achievements', 'admin',
        ];

        $inc = collect(explode(',', (string)$r->query('include', '')))
            ->map(fn($s) => trim($s))
            ->filter(fn($s) => in_array($s, $allowed, true))
            ->unique()->values()->all();

        // Keš ključ: user + group + year + include lista (sortirana)
        $cacheKey = $this->cacheKey($uid, $group, $year, $inc);
        $ttl = now()->addSeconds(45); // 30–60s; uzeli smo 45s

        $payload = Cache::remember($cacheKey, $ttl, function () use ($uid, $group, $year, $inc, $user) {
            $out = [];

            if (in_array('me', $inc, true)) {
                $out['me'] = $this->secMe($user, $group);
            }
            if (in_array('open_session', $inc, true)) {
                $out['open_session'] = $this->secOpenSession($uid);
            }
            if (in_array('assigned', $inc, true)) {
                $out['assigned'] = $this->secAssigned($uid, self::LIMIT_ASSIGNED);
            }
            if (in_array('season_stats', $inc, true)) {
                $out['season_stats'] = $this->secSeasonStats($uid, $group, $year);
            }
            if (in_array('events', $inc, true)) {
                $out['events'] = $this->secEvents($uid, $group, self::LIMIT_EVENTS);
            }
            if (in_array('activity', $inc, true)) {
                $out['activity'] = $this->secActivity($group, self::LIMIT_ACTIVITY);
            }
            if (in_array('mini_leaderboard', $inc, true)) {
                $out['mini_leaderboard'] = $this->secMiniLeaderboard($group, $year, self::LIMIT_LB);
            }
            if (in_array('map', $inc, true)) {
                $out['map'] = $this->secMap($uid, self::LIMIT_MAP);
            }
            if (in_array('species_trends', $inc, true)) {
                $out['species_trends'] = $this->secSpeciesTrends($uid, $group, $year, self::LIMIT_SPECIES);
            }
            if (in_array('achievements', $inc, true)) {
                $out['achievements'] = $this->secAchievements($uid);
            }
            if (in_array('admin', $inc, true)) {
                $out['admin'] = $this->secAdmin($uid, $group);
            }

            return $out;
        });

        return response()->json($payload);
    }

    private function cacheKey(int $uid, ?int $group, int $year, array $inc): string
    {
        sort($inc);
        $incKey = implode('|', $inc);
        return "home:uid={$uid}:g=" . ($group ?? 0) . ":y={$year}:inc={$incKey}";
    }

    /* ---------------------- Sekcije ---------------------- */

    /** me { id, name, display_name, avatar_url, roles[] } */
    private function secMe($user, ?int $group): array
    {
        $profile = $user->profile()->select(['id', 'user_id', 'display_name', 'avatar_path'])->first();
        $display = $profile->display_name ?? $user->name ?? null;
        $avatarUrl = $profile?->avatar_url ?? ($profile?->avatar_path ? Storage::disk('s3')->url($profile->avatar_path)
            :
            null);

        $roles = [];
        if ($group) {
            $pivot = DB::table('group_user')
                ->where('group_id', $group)->where('user_id', $user->id)
                ->first();
            if ($pivot) {
                $role = strtolower(trim((string)($pivot->role ?? '')));
                if ($role === 'owner') {
                    $roles = ['owner', 'member'];
                } elseif (in_array($role, ['admin', 'moderator', 'mod'], true)) {
                    $roles = ['mod', 'member'];
                } else {
                    $roles = ['member'];
                }
            }
        }

        return [
            'id' => (int)$user->id,
            'name' => $user->name,
            'display_name' => $display,
            'avatar_url' => $avatarUrl,
            'roles' => $roles,
        ];
    }

    /** open_session { id, title, started_at, photos[], catches_count } */
    private function secOpenSession(int $uid): ?array
    {
        $s = FishingSession::query()
            ->withCount('catches')
            ->with(['catchPhotos' => fn($q) => $q->orderByDesc('id')->limit(3)])
            ->where('user_id', $uid)->where('status', 'open')
            ->latest('started_at')->latest('id')
            ->first();

        if (!$s) return null;

        return [
            'id' => (int)$s->id,
            'title' => $s->title,
            'started_at' => $s->started_at,
            'catches_count' => (int)($s->catches_count ?? 0),
            'latitude' => $s->latitude,
            'longitude' => $s->longitude,
            'photos' => collect($s->catchPhotos ?? [])->map(fn($p) => [
                'id' => (int)$p->id, 'url' => $p->url, 'ord' => $p->ord ?? null
            ])->values(),
        ];
    }

    /** assigned { items:[...], meta{total} } (pending za mene) */
    private function secAssigned(int $uid, int $limit): array
    {
        $base = FishingSession::query()
            ->select('fishing_sessions.*')
            ->join('session_reviews as sr', 'sr.session_id', '=', 'fishing_sessions.id')
            ->where('sr.reviewer_id', $uid)
            ->where('sr.status', 'pending');

        $total = (clone $base)->count(DB::raw('distinct fishing_sessions.id'));

        $items = $base->withCount('catches')
            ->with([
                'user:id,name',
                'user.profile:id,user_id,display_name,avatar_path',
            ])
            ->orderByDesc('fishing_sessions.started_at')->orderByDesc('fishing_sessions.id')
            ->limit($limit)
            ->get();

        return [
            'items' => $items->map(function ($s) {
                return [
                    'id' => (int)$s->id,
                    'title' => $s->title,
                    'started_at' => $s->started_at,
                    'catches_count' => (int)($s->catches_count ?? 0),
                    'user' => [
                        'id' => (int)$s->user?->id,
                        'name' => $s->user?->name,
                        'display_name' => $s->user?->profile?->display_name ?? $s->user?->name,
                        'avatar_url' => $s->user?->profile?->avatar_url ?? ($s->user?->profile?->avatar_path ? asset('storage/' . $s->user->profile->avatar_path) : null),
                    ],
                ];
            })->values(),
            'meta' => ['total' => (int)$total],
        ];
    }

    /** season_stats { sessions, catches, total_weight_kg, biggest_single_kg } */
    private function secSeasonStats(int $uid, ?int $group, int $year): array
    {
        // broj sesija (po useru, grupi, godini)
        $sessions = FishingSession::query()
            ->when($group, fn($q) => $q->where('group_id', $group))
            ->where('user_id', $uid)
            ->whereYear('started_at', $year)
            ->count();

        // agregati po ulovima (iz sesija)
        $agg = DB::table('catches as c')
            ->join('fishing_sessions as s', 's.id', '=', 'c.session_id')
            ->when($group, fn($q) => $q->where('s.group_id', $group))
            ->where('s.user_id', $uid)
            ->whereYear('s.started_at', $year)
            ->selectRaw('COUNT(*) as catches, SUM(COALESCE(c.total_weight_kg,0)) as total_weight_kg, MAX(COALESCE(c.biggest_single_kg,0)) as biggest_single_kg')
            ->first();

        return [
            'sessions' => (int)$sessions,
            'catches' => (int)($agg->catches ?? 0),
            'total_weight_kg' => (float)($agg->total_weight_kg ?? 0.0),
            'biggest_single_kg' => (float)($agg->biggest_single_kg ?? 0.0),
        ];
    }

    /** events [...] (next N, include my_rsvp) */
    private function secEvents(int $uid, ?int $group, int $limit): array
    {
        if (!class_exists(Event::class)) return [];

        $q = Event::query()
            ->when($group, fn($qq) => $qq->where('group_id', $group))
            ->whereDate('start_at', '>=', now()->toDateString())
            ->orderBy('start_at')
            ->limit($limit);

        // Napomena: ako imaš relaciju myRsvp → može with(['myRsvp'=>...])
        $items = $q->get(['id', 'title', 'start_at', 'group_id']);

        // my_rsvp iz pivot tabele (event_user ili events_rsvps)
        $rsvps = DB::table('event_rsvps')
            ->whereIn('event_id', $items->pluck('id'))
            ->where('user_id', $uid)
            ->pluck('status', 'event_id'); // status: yes/no/undecided

        return $items->map(function ($e) use ($rsvps) {
            return [
                'id' => (int)$e->id,
                'title' => $e->title,
                'start_at' => $e->start_at,
                'my_rsvp' => $rsvps[$e->id] ?? null,
            ];
        })->values()->all();
    }

    /** activity [...] (ako nema tabele, vrati prazan niz) */
    private function secActivity(?int $group, int $limit): array
    {
        // 1) Otvorene sesije
        $sessions = DB::table('fishing_sessions as s')
            ->when($group, fn($q) => $q->where('s.group_id', $group))
            ->select('s.id', 's.user_id', 's.title', 's.created_at')
            ->latest('s.created_at')
            ->limit($limit)
            ->get()
            ->map(fn($s) => [
                'id'         => (int) $s->id,           // id feed stavke (može biti isto kao ref_id)
                'type'       => 'session_opened',
                'ref_id'     => (int) $s->id,           // sesija
                'user_id'    => (int) $s->user_id,
                'created_at' => $s->created_at,
                'meta'       => [
                    'title' => $s->title ?: 'Nova sesija',
                    'url'   => "/sessions/{$s->id}",
                ],
            ]);

        // 2) Novi ulovi (sa nazivom vrste)
        $catches = DB::table('catches as c')
            ->join('fishing_sessions as s', 's.id', '=', 'c.session_id')
            ->leftJoin('species as sp', 'sp.id', '=', 'c.species_id')
            ->when($group, fn($q) => $q->where('s.group_id', $group))
            ->selectRaw("
            c.id,
            c.user_id,
            c.created_at,
            s.id as session_id,
            COALESCE(NULLIF(TRIM(sp.name_sr),''), NULLIF(TRIM(sp.name_latin),''), NULLIF(TRIM(sp.slug),''), 'Ulov') as species_label,
            COALESCE(c.total_weight_kg,0) as total_weight_kg
        ")
            ->latest('c.created_at')
            ->limit($limit)
            ->get()
            ->map(fn($c) => [
                'id'         => (int) $c->id,
                'type'       => 'catch_added',
                'ref_id'     => (int) $c->id,          // ulov
                'user_id'    => (int) $c->user_id,
                'created_at' => $c->created_at,
                'meta'       => [
                    'title'   => (string) $c->species_label,
                    'session' => (int) $c->session_id,
                    'url'     => "/catches/{$c->id}",
                    'weight'  => (float) $c->total_weight_kg,
                ],
            ]);

        // 3) Odluke na nivou sesije (approved / rejected)
        $reviewTable = Schema::hasTable('session_reviews')
            ? 'session_reviews'
            : (Schema::hasTable('session_confirmations') ? 'session_confirmations' : null);

        $reviews = collect();

        if ($reviewTable) {
            $hasDecidedAt = Schema::hasColumn($reviewTable, 'decided_at');
            // actor: reviewer_id (reviews) ili nominee_user_id (confirmations)
            $actorCol = Schema::hasColumn($reviewTable, 'reviewer_id')
                ? 'r.reviewer_id'
                : (Schema::hasColumn($reviewTable, 'nominee_user_id') ? 'r.nominee_user_id' : '0');

            $decidedCol = $hasDecidedAt ? 'r.decided_at' : 'r.updated_at';

            $reviews = DB::table($reviewTable . ' as r')
                ->join('fishing_sessions as s', 's.id', '=', 'r.session_id')
                ->when($group, fn($q) => $q->where('s.group_id', $group))
                ->whereIn('r.status', ['approved', 'rejected'])
                ->selectRaw("
            r.session_id,
            r.status,
            {$decidedCol} as decided_at,
            {$actorCol} as actor_id
        ")
                ->orderByDesc('decided_at')
                ->limit($limit)
                ->get()
                ->map(function ($r) {
                    $type = $r->status === 'approved' ? 'session_approved' : 'session_rejected';
                    return [
                        'id'         => (int) $r->session_id,   // id stavke u feed-u
                        'type'       => $type,
                        'ref_id'     => (int) $r->session_id,
                        'user_id'    => (int) $r->actor_id,
                        'created_at' => $r->decided_at,
                        'meta'       => [
                            'url'    => "/sessions/{$r->session_id}",
                            'status' => $r->status,
                        ],
                    ];
                });
        }
        // Merge + sort + limit
        return $sessions
            ->merge($catches)
            ->merge($reviews)
            ->sortByDesc('created_at')
            ->take($limit)
            ->values()
            ->all();
    }

    /** mini_leaderboard { weightTop:[...], biggestTop:[...] } */
    private function secMiniLeaderboard(?int $group, int $year, int $limit): array
    {
        $base = DB::table('catches as c')
            ->join('fishing_sessions as s', 's.id', '=', 'c.session_id')
            ->when($group, fn($q) => $q->where('s.group_id', $group))
            ->whereYear('s.started_at', $year)
            ->groupBy('s.user_id');

        $weightTop = (clone $base)
            ->selectRaw('s.user_id, SUM(COALESCE(c.total_weight_kg,0)) as total_weight_kg, COUNT(*) as catches_count, MAX(COALESCE(c.biggest_single_kg,0)) as biggest_single_kg')
            ->orderByDesc('total_weight_kg')
            ->limit($limit)
            ->get();

        $biggestTop = (clone $base)
            ->selectRaw('s.user_id, MAX(COALESCE(c.biggest_single_kg,0)) as biggest_single_kg, SUM(COALESCE(c.total_weight_kg,0)) as total_weight_kg, COUNT(*) as catches_count')
            ->orderByDesc('biggest_single_kg')
            ->limit($limit)
            ->get();

        // Učitaj korisnike
        $uids = collect($weightTop)->pluck('user_id')->merge(collect($biggestTop)->pluck('user_id'))->unique()->values();
        $users = DB::table('users as u')
            ->leftJoin('profiles as p', 'p.user_id', '=', 'u.id')
            ->whereIn('u.id', $uids)
            ->get(['u.id', 'u.name', 'p.display_name', 'p.avatar_path']);

        $byId = $users->keyBy('id');

        $mapUser = function ($row) use ($byId) {
            $u = $byId->get($row->user_id);
            return [
                'user' => [
                    'id' => (int)$row->user_id,
                    'name' => $u->name ?? null,
                    'display_name' => $u->display_name ?? $u->name ?? null,
                    'avatar_url' => $u->avatar_path ?
                        Storage::disk('s3')->url($u->avatar_path) :
                        null,
                ],
                'catches_count' => (int)($row->catches_count ?? 0),
                'total_weight_kg' => (float)($row->total_weight_kg ?? 0.0),
                'biggest_single_kg' => (float)($row->biggest_single_kg ?? 0.0),
            ];
        };

        return [
            'weightTop' => collect($weightTop)->map($mapUser)->values(),
            'biggestTop' => collect($biggestTop)->map($mapUser)->values(),
        ];
    }

    /** map [...] (poslednje sesije sa koordinatama; samo moje) */
    private function secMap(int $uid, int $limit): array
    {
        $items = FishingSession::query()
            ->where('user_id', $uid)
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->whereHas('catches')
            ->orderByDesc('started_at')->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'title', 'latitude', 'longitude', 'started_at']);

        return $items->map(fn($s) => [
            'id' => (int)$s->id,
            'title' => $s->title,
            'latitude' => (float)$s->latitude,
            'longitude' => (float)$s->longitude,
            'started_at' => $s->started_at,
        ])->values()->all();
    }

    /** species_trends [...] (top 5 label/cnt/total_kg za mene) */
    private function secSpeciesTrends(int $uid, ?int $group, int $year, int $limit): array
    {
        $rows = DB::table('catches as c')
            ->join('fishing_sessions as s', 's.id', '=', 'c.session_id')
            ->leftJoin('species as sp', 'sp.id', '=', 'c.species_id') // ← ključna promena
            ->when($group, fn($q) => $q->where('s.group_id', $group))
            ->where('s.user_id', $uid)
            ->whereYear('s.started_at', $year)
            ->selectRaw("
                COALESCE(
                    NULLIF(TRIM(sp.name_sr), ''),
                    NULLIF(TRIM(sp.name_latin), ''),
                    NULLIF(TRIM(sp.slug), ''),
                    'Unknown'
                ) as label,
                COUNT(*) as cnt,
                SUM(COALESCE(c.total_weight_kg, 0)) as total_kg
            ")
            ->groupBy('label')
            ->orderByDesc('cnt') //-- top po broju ulova(možeš promeniti u total_kg)
            ->limit($limit)
            ->get();

        return $rows->map(fn($r) => [
            'label' => $r->label,
            'cnt' => (int)$r->cnt,
            'total_kg' => (float)$r->total_kg,
        ])->values()->all();
    }

    private function secAchievements(int $uid): array
    {
        $catchTable   = (new FishingCatch)->getTable();
        $sessionTable = (new FishingSession)->getTable();

        // --- Personal Best (najveći pojedinačni ulov)
        $pbRow = DB::table("$catchTable as c")
            ->join("$sessionTable as s", 's.id', '=', 'c.session_id')
            ->where('s.user_id', $uid)
            ->selectRaw('COALESCE(c.total_weight_kg,0) as kg, c.created_at')
            ->orderByDesc('kg')
            ->limit(1)
            ->first();

        $pb        = (float)($pbRow->kg ?? 0);
        $pbUnlockedAt = $pb > 0 ? ($pbRow->created_at ?? now()) : null;

        // --- Streak (bar 2 dana zaredom sa sesijom)
        $dates = DB::table("$sessionTable as s")
            ->where('s.user_id', $uid)
            ->orderBy('started_at')
            ->pluck('started_at')
            ->map(fn($d) => Carbon::parse($d)->toDateString())
            ->unique()
            ->values();

        $streakUnlockedAt = null;
        for ($i = 1; $i < $dates->count(); $i++) {
            $prev = Carbon::parse($dates[$i - 1]);
            $curr = Carbon::parse($dates[$i]);
            if ($prev->diffInDays($curr) === 1) {
                // “otključano” na drugi dan u nizu
                $streakUnlockedAt = $curr->startOfDay()->toDateTimeString();
                break;
            }
        }

        // --- Night owl (sesija start 22–05)
        $nightAt = DB::table("$sessionTable as s")
            ->where('s.user_id', $uid)
            ->whereRaw("HOUR(s.started_at) >= 22 OR HOUR(s.started_at) < 5")
            ->orderBy('started_at')
            ->value('started_at');

        $hasNight = !empty($nightAt);

        // Mapiraj u Home FE format
        return [
            [
                'id'           => 1,
                'key'          => 'pb',
                'title'        => 'Lični rekord',
                'unlocked_at'  => $pbUnlockedAt,
                'meta'         => [
                    'desc'  => 'Najveći pojedinačni ulov (kg)',
                    'value' => round($pb, 2),
                ],
            ],
            [
                'id'           => 2,
                'key'          => 'streak2',
                'title'        => 'Dani u nizu',
                'unlocked_at'  => $streakUnlockedAt, // null ako nije ostvareno
                'meta'         => [
                    'desc' => 'Dve sesije zaredom (dani)',
                ],
            ],
            [
                'id'           => 3,
                'key'          => 'nightowl',
                'title'        => 'Noćni čuvar',
                'unlocked_at'  => $hasNight ? $nightAt : null,
                'meta'         => [
                    'desc' => 'Sesija noću (22–05)',
                ],
            ],
        ];
    }


    /** admin { canManage, shortcuts } */
    private function secAdmin(int $uid, ?int $group): array
    {
        $roles = [];
        if ($group) {
            $pivot = DB::table('group_user')
                ->where('group_id', $group)->where('user_id', $uid)->first();
            if ($pivot) {
                $role = strtolower(trim((string)($pivot->role ?? '')));
                if ($role === 'owner') {
                    $roles = ['owner', 'member'];
                } elseif (in_array($role, ['admin', 'moderator', 'mod'], true)) {
                    $roles = ['mod', 'member'];
                } else {
                    $roles = ['member'];
                }
            }
        }
        $canManage = in_array('owner', $roles, true) || in_array('mod', $roles, true);

        return [
            'canManage' => $canManage,
            'shortcuts' => $canManage ? [
                ['label' => 'Članovi', 'href' => $group ? "/groups/{$group}" : "/groups"],
                ['label' => 'Događaji', 'href' => "/events"],
                ['label' => 'Odobrenja', 'href' => "/sessions/assigned-to-me"],
            ] : [],
        ];
    }
}
