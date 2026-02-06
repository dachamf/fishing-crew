<?php
namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\FishingCatch;
use App\Models\FishingSession;
use App\Services\SessionReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FishingSessionController extends Controller
{
    public function __construct(private SessionReviewService $reviewSvc) {}
    /**
     * @param Request $r
     * @return JsonResponse
     */
    public function index(Request $r)
    {
        $q = FishingSession::query()
            ->withCount('catches')
            ->with([
                'user:id,name',
                'user.profile:id,user_id,display_name,avatar_path',
            ]);

        if ((string)$r->query('whereHasCatches', '') === '1') {
            $q->whereHas('catches');
        }

        // user_id=me alias
        if ($r->filled('user_id')) {
            $uid = $r->user_id === 'me' || $r->user_id === 'self'
                ? $r->user()->id
                : (int)$r->user_id;
            $q->where('user_id', $uid);
        }

        if ($r->filled('group_id')) $q->where('group_id', (int)$r->group_id);
        if ($r->filled('status')) $q->where('status', $r->status);
        if ($r->filled('season_year')) $q->where('season_year', (int)$r->season_year);

        if ($r->filled('from') || $r->filled('to')) {
            $from = $r->filled('from') ? \Carbon\Carbon::parse($r->from) : null;
            $to = $r->filled('to') ? \Carbon\Carbon::parse($r->to) : null;
            $q->between($from, $to);
        }

        if ($s = trim((string)$r->query('search', ''))) {
            $q->where(function ($w) use ($s) {
                $w->where('title', 'like', "%{$s}%");
            });
        }

        // include=photos,catches,catches.user,event,confirmations,confirmations.nominee
        $include = collect(explode(',', (string)$r->query('include', '')))
            ->map(fn($i) => trim($i))->filter()->values();

        $allowed = ['catches', 'catches.user', 'event', 'photos', 'confirmations', 'confirmations.nominee'];
        $include = $include->filter(fn($rel) => in_array($rel, $allowed))->values();

        $with = [];

        // photos (limit 3)
        if ($include->contains('photos')) {
            $with['catchPhotos'] = fn($qq) => $qq->orderByDesc('id')->limit(3);
        }

        if ($include->contains('catches.user')) {
            $with['catches'] = fn($qq) => $qq->orderByDesc('caught_at')->orderByDesc('id')
                ->with(['user:id,name', 'user.profile:id,user_id,display_name,avatar_path']);
        } elseif ($include->contains('catches')) {
            $with['catches'] = fn($qq) => $qq->orderByDesc('caught_at')->orderByDesc('id');
        }

        if ($include->contains('event')) {
            $with[] = 'event:id,title,start_at';
        }

        // confirmations (BEZ token kolone u payloadu)
        if ($include->contains('confirmations.nominee')) {
            $with['confirmations'] = function ($qq) {
                $qq->select('id', 'session_id', 'nominee_user_id', 'status', 'decided_at', 'created_at', 'updated_at')
                    ->with(['nominee:id,name', 'nominee.profile:id,user_id,display_name,avatar_path']);
            };
        } elseif ($include->contains('confirmations')) {
            $with['confirmations'] = function ($qq) {
                $qq->select('id', 'session_id', 'nominee_user_id', 'status', 'decided_at', 'created_at', 'updated_at');
            };
        }

        $only = collect(explode(',', (string)$r->query('only', '')))
            ->map(fn($i) => trim($i))->filter()->values();

        if ($only->isNotEmpty()) {
            $select = ['id'];
            if ($only->contains('coords')) {
                $select[] = 'latitude';
                $select[] = 'longitude';
            }
            if ($only->contains('title')) {
                $select[] = 'title';
                $select[] = 'started_at';
            }
            if ($only->contains('status')) {
                $select[] = 'status';
            }
            if ($only->contains('group_id')) {
                $select[] = 'group_id';
            }
            $q->select(array_values(array_unique($select)));
        }

        $q->latest('started_at')->latest('id');

        $perPage = min(100, (int)($r->query('limit', $r->query('per_page', 20))));

        return response()->json($q->with($with)->paginate($perPage));
    }


    /**
     * Display the specified fishing session resource with optional related data.
     */
    public function show(Request $r, FishingSession $session)
    {
        $this->authorize('view', $session);

        // 1) Dozvoljeni include-ovi (dodate confirmations.*)
        $allowed = [
            'catches', 'catches.user',
            'event',
            'group',
            'photos', // alias → catchPhotos
            'reviews', 'reviews.reviewer',
            'confirmations', 'confirmations.nominee',
        ];

        // 2) Parsiraj ?include=...
        $include = collect(explode(',', (string)$r->query('include', '')))
            ->map(fn($s) => trim($s))
            ->filter()
            ->filter(fn($rel) => in_array($rel, $allowed, true))
            ->unique()
            ->values();

        // 3) Uvek učitaj vlasnika (light)
        $base = [
            'user:id,name',
            'user.profile:id,user_id,display_name,avatar_path',
        ];

        // 4) Uslovne relacije
        $with = [];

        if ($include->contains('catches.user')) {
            $with['catches'] = fn($qq) => $qq
                ->orderByDesc('caught_at')->orderByDesc('id')
                ->with([
                    'user:id,name',
                    'user.profile:id,user_id,display_name,avatar_path',
                ]);
        } elseif ($include->contains('catches')) {
            $with['catches'] = fn($qq) => $qq
                ->orderByDesc('caught_at')->orderByDesc('id');
        }

        if ($include->contains('event')) {
            // ograniči kolone
            $with['event'] = fn($qq) => $qq->select('id', 'title', 'start_at');
        }

        if ($include->contains('group')) {
            $with['group'] = fn($qq) => $qq->select('id', 'name', 'season_year');
        }

        if ($include->contains('reviews.reviewer')) {
            $with['reviews'] = fn($qq) => $qq->with([
                'reviewer:id,name',
                'reviewer.profile:id,user_id,display_name,avatar_path',
            ]);
        } elseif ($include->contains('reviews')) {
            $with[] = 'reviews';
        }

        // confirmations (bez tokena)
        if ($include->contains('confirmations.nominee')) {
            $with['confirmations'] = function ($qq) {
                $qq->select('id', 'session_id', 'nominee_user_id', 'status', 'decided_at', 'created_at', 'updated_at')
                    ->with([
                        'nominee:id,name',
                        'nominee.profile:id,user_id,display_name,avatar_path',
                    ]);
            };
        } elseif ($include->contains('confirmations')) {
            $with['confirmations'] = function ($qq) {
                $qq->select('id', 'session_id', 'nominee_user_id', 'status', 'decided_at', 'created_at', 'updated_at');
            };
        }

        // alias "photos" → relacija catchPhotos; možeš ograničiti broj
        if ($include->contains('photos')) {
            $with['catchPhotos'] = fn($qq) => $qq->orderByDesc('id')->limit(12);
        }

        // 5) Eager-load + count
        $session->load(array_merge($base, $with));
        $session->loadCount('catches');

        return response()->json($session);
    }

    /**
     * Handles the creation of a new fishing session for a user.
     */
    public function store(Request $r)
    {
        // 1) zabrani više od jedne OPEN sesije
        $hasOpen = \App\Models\FishingSession::query()
            ->where('user_id', $r->user()->id)
            ->where('status', 'open')
            ->exists();

        if ($hasOpen) {
            return response()->json([
                'message' => 'Već imaš otvorenu sesiju. Zatvori je pre nego što kreiraš novu.'
            ], 409);
        }

        $data = $r->validate([
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'title' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'started_at' => ['nullable', 'date'],
            'season_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $session = new FishingSession($data);
        $session->user_id = $r->user()->id;
        $session->status = 'open';
        $session->started_at = $data['started_at'] ?? now();
        $session->season_year = $data['season_year'] ?? (int)($session->started_at?->format('Y'));

        $hasOpen = FishingSession::query()
            ->where('user_id', $r->user()->id)
            ->where('status', 'open')
            ->exists();

        if ($hasOpen) {
            return response()->json([
                'message' => 'Već imaš otvorenu sesiju. Zatvori je pre nove.'
            ], 422);
        }

        $session->save();

        return response()->json($session, 201);
    }

    public function update(Request $r, FishingSession $session)
    {
        $this->authorize('update', $session);

        $data = $r->validate([
            'title' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'location_name' => ['nullable', 'string', 'max:160'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(['open', 'closed'])],
        ]);

        // 2) Ako se status menja u OPEN, opet proveri jedinstvenost
        if (($data['status'] ?? null) === 'open') {
            $hasOtherOpen = \App\Models\FishingSession::query()
                ->where('user_id', $r->user()->id)
                ->where('status', 'open')
                ->where('id', '!=', $session->id)
                ->exists();

            if ($hasOtherOpen) {
                return response()->json([
                    'message' => 'Već imaš drugu otvorenu sesiju.'
                ], 409);
            }
        }

        $session->fill($data)->save();
        return response()->json($session->fresh());
    }

    /**
     * Closes an open fishing session for a user.
     * @throws \Throwable
     */
    public function close(Request $r, FishingSession $session)
    {
        $this->authorize('update', $session);
        \DB::transaction(function () use ($session) {
            $session->update([
                'status' => 'closed',
                'ended_at' => $session->ended_at ?? now(),
            ]);
            // uskladiti sa poslovnim pravilom: catches → pending
            FishingCatch::where('session_id', $session->id)
                ->update(['status' => 'pending']);
        });
        return response()->json([
            'message' => 'Session closed',
            'session' => $session->fresh(),
        ]);
    }

    /**
     * @param Request $r
     * @param FishingSession $session
     * @return JsonResponse
     * @throws \Throwable
     */
    public function closeAndNominate(Request $r, FishingSession $session): JsonResponse
    {
        $this->authorize('update', $session);

        // 1) Validacija – reviewer_ids je opcioni niz
        $data = $r->validate([
            'reviewer_ids' => ['array'], // više NIJE required
            'reviewer_ids.*' => ['integer', 'exists:users,id'],
        ]);

        // Normalizacija: uniq, int, bez null, bez vlasnika (ako želiš)
        $reviewerIds = collect($data['reviewer_ids'] ?? [])
            ->filter(fn($id) => $id !== null)
            ->map(fn($id) => (int)$id)
            ->unique()
            ->values();

        // 2) (Opcionalno) osiguraj da su svi članovi grupe – samo ako ima nominacija
        if ($session->group_id && $reviewerIds->isNotEmpty()) {
            $memberIds = \DB::table('group_user')
                ->where('group_id', $session->group_id)
                ->pluck('user_id')
                ->all();

            $invalid = $reviewerIds->diff($memberIds)->all();
            if (!empty($invalid)) {
                return response()->json([
                    'message' => 'Neki izabrani korisnici nisu članovi grupe.'
                ], 422);
            }
        }

        \DB::transaction(function () use ($session) {
            // 3) Zatvori sesiju bez obzira na nominacije
            $session->update([
                'status' => 'closed',
                'ended_at' => $session->ended_at ?? now(),
            ]);

            // 4) Svi ulovi iz sesije prelaze u pending (ako već nisu)
            FishingCatch::where('session_id', $session->id)
                ->update(['status' => 'pending']);
        });

        // 5) Nominacije (novi flow: session confirmations + token link)
        if ($reviewerIds->isNotEmpty()) {
            $this->reviewSvc->nominate(
                $session,
                $reviewerIds->all(),
                fn($s, $c) => rtrim(config('app.frontend_url'), '/')."/sessions/{$s->id}?token={$c->plain_token}"
            );
        }

        return response()->json([
            'message' => $reviewerIds->isNotEmpty()
                ? 'Sesija zatvorena. Poslati zahtevi za potvrdu.'
                : 'Sesija zatvorena bez nominacija.',
            'n_reviewers' => $reviewerIds->count(),
            'session' => $session->fresh(),
        ]);
    }

    /**
     * @param FishingSession $session
     * @return JsonResponse
     */
    public function destroy(FishingSession $session)
    {
        $this->authorize('delete', $session);
        $session->delete();
        return response()->json(['message' => 'Session deleted']);
    }
}
