<?php
namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\CatchConfirmation;
use App\Models\FishingCatch;
use App\Models\FishingSession;
use App\Models\User;
use App\Notifications\CatchConfirmationRequested;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FishingSessionController extends Controller
{
    /**
     * @param Request $r
     * @return JsonResponse
     */
    public function index(Request $r) {
        $q = FishingSession::query()
            ->withCount('catches')
            ->with([
                'user:id,name',
                'user.profile:id,user_id,display_name,avatar_path',
            ]);
        $has = $r->query('has_catches'); // "1" | "true" | "any" | "mine"
        if ($has === 'mine') {
            if ($uid = optional($r->user())->id) {
                $q->whereHas('catches', fn($qq) => $qq->where('user_id', $uid));
            }
        } elseif ($r->boolean('has_catches') || $has === 'any' || $has === '1' || $has === 'true') {
            $q->has('catches');
        }

        if ($r->filled('group_id'))    $q->where('group_id', (int)$r->group_id);
        if ($r->filled('user_id'))     $q->where('user_id', (int)$r->user_id);
        if ($r->filled('status'))      $q->where('status', $r->status);
        if ($r->filled('season_year')) $q->where('season_year', (int)$r->season_year);
        if ($r->filled('from') || $r->filled('to')) {
            $from = $r->filled('from') ? \Carbon\Carbon::parse($r->from) : null;
            $to   = $r->filled('to')   ? \Carbon\Carbon::parse($r->to)   : null;
            $q->between($from, $to);
        }

        if ($s = trim((string) $r->query('search', ''))) {
            $q->where(function ($w) use ($s) {
                $w->where('title', 'like', "%{$s}%");
            });
        }

        // NEW: include param sa white-listom i limitom fotki (max 3)
        $include = collect(explode(',', (string) $r->query('include', '')))
            ->map(fn ($i) => trim($i))
            ->filter()
            ->values();

        $allowed = ['photos', 'catches', 'catches.user', 'event'];
        $with = [];

        if ($include->contains('catches.user')) {
            $with['catches'] = fn ($qq) => $qq->orderByDesc('caught_at')->orderByDesc('id')
                ->with(['user:id,name', 'user.profile:id,user_id,display_name,avatar_path']);
        } elseif ($include->contains('catches')) {
            $with['catches'] = fn ($qq) => $qq->orderByDesc('caught_at')->orderByDesc('id');
        }
        if ($include->contains('event')) {
            $with[] = 'event:id,title,start_at';
        }

        if (!empty($with)) {
            $q->with($with);
        }

        $q->latest('started_at')->latest('id');

        $perPage = min(100, (int) $r->query('per_page', 20));
        return response()->json($q->paginate($perPage));
    }

    /**
     * Display the specified fishing session resource with optional related data.
     *
     * This method authorizes the viewing of a specific fishing session and processes
     * the `include` query parameter to dynamically load related resources.
     *
     * Supported `include` query parameters:
     * - `catches.user`: Includes related catches with user details and user profiles.
     * - `catches`: Includes related catches ordered by `caught_at` and `id`.
     * - `event`: Includes event details such as `id`, `title`, and `start_at`.
     *
     * Base relationships always loaded:
     * - `user:id,name`
     * - `user.profile:id,user_id,display_name,avatar_path`
     *
     * @param FishingSession $session The fishing session instance to display.
     * @return JsonResponse JSON response containing the session data
     *                                      with the requested relationships.
     */
    public function show(FishingSession $session) {
        $this->authorize('view', $session);

        // NEW: podrži iste include-ove i ovde
        $include = collect(explode(',', (string) request()->query('include', '')))
            ->map(fn ($i) => trim($i))
            ->filter()
            ->values();

        $base = [
            'user:id,name',
            'user.profile:id,user_id,display_name,avatar_path',
        ];

        $with = [];
        if ($include->contains('catches.user')) {
            $with['catches'] = fn ($qq) => $qq->orderByDesc('caught_at')->orderByDesc('id')
                ->with(['user:id,name', 'user.profile:id,user_id,display_name,avatar_path']);
        } elseif ($include->contains('catches')) {
            $with['catches'] = fn ($qq) => $qq->orderByDesc('caught_at')->orderByDesc('id');
        }
        if ($include->contains('event')) {
            $with[] = 'event:id,title,start_at';
        }

        $session->load(array_merge($base, $with));

        return response()->json($session);
    }

    /**
     * Handles the creation of a new fishing session for a user.
     *
     * Validates incoming request data, checks if the user already has
     * an open session, and creates a new session if none exist.
     * Returns a conflict response if a session is already open.
     *
     * @param Request $r The HTTP request instance.
     *
     * @return JsonResponse A JSON response containing the newly created session
     *                                       data or an error message.
     */
    public function store(Request $r) {
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
            'group_id'   => ['nullable','integer','exists:groups,id'],
            'event_id'   => ['nullable','integer','exists:events,id'],
            'title'      => ['nullable','string','max:100'],
            'latitude'   => ['nullable','numeric','between:-90,90'],
            'longitude'  => ['nullable','numeric','between:-180,180'],
            'started_at' => ['nullable','date'],
            'season_year'=> ['nullable','integer','min:2000','max:2100'],
        ]);

        $session = new FishingSession($data);
        $session->user_id = $r->user()->id;
        $session->status  = 'open';
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

    public function update(Request $r, FishingSession $session) {
        $this->authorize('update', $session);

        $data = $r->validate([
            'title'      => ['nullable','string','max:100'],
            'latitude'   => ['nullable','numeric','between:-90,90'],
            'longitude'  => ['nullable','numeric','between:-180,180'],
            'started_at' => ['nullable','date'],
            'ended_at'   => ['nullable','date'],
            'status'     => ['nullable', Rule::in(['open','closed'])],
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
     *
     * Authorizes the request for the provided session, updates the session's status
     * to 'closed', sets the ending timestamp if it is not already set, and saves the changes.
     * Returns a JSON response containing a success message and the updated session data.
     *
     * @param Request $r The HTTP request instance.
     * @param FishingSession $session The fishing session to be closed.
     *
     * @return JsonResponse A JSON response with a success message
     *                                       and the fresh session data.
     */
    public function close(Request $r, FishingSession $session) {
        $this->authorize('update', $session);
        $session->update([
            'status'   => 'closed',
            'ended_at' => $session->ended_at ?? now(),
        ]);
        return response()->json(['message'=>'Session closed','session'=>$session->fresh()]);
    }


    /**
     * @param Request $r
     * @param FishingSession $session
     * @return JsonResponse
     * @throws \Throwable
     */
    public function closeAndNominate(Request $r, FishingSession $session) {
        $this->authorize('update', $session); // owner/mod prema tvojoj policy

        $data = $r->validate([
            'reviewer_ids'   => ['required','array','min:1'],
            'reviewer_ids.*' => ['integer','exists:users,id'],
        ]);

        // (opciono) osiguraj da su svi u istoj grupi:
        if ($session->group_id) {
            $memberIds = \DB::table('group_user')
                ->where('group_id', $session->group_id)
                ->pluck('user_id')->all();

            $invalid = array_diff($data['reviewer_ids'], $memberIds);
            if (!empty($invalid)) {
                return response()->json([
                    'message' => 'Neki izabrani korisnici nisu članovi grupe.'
                ], 422);
            }
        }

        $created = 0;

        DB::transaction(function() use ($session, $data, &$created) {
            // 1) zatvori sesiju
            $session->update([
                'status'   => 'closed',
                'ended_at' => $session->ended_at ?? now(),
            ]);

            // 2) za svaki ulov iz sesije, kreiraj pending potvrde
            $catches = FishingCatch::query()
                ->where('session_id', $session->id)
                ->get(['id']);

            foreach ($catches as $catch) {
                foreach ($data['reviewer_ids'] as $uid) {
                    // firstOrCreate zbog unique(catch_id, confirmed_by)
                    $conf = CatchConfirmation::firstOrCreate(
                        ['catch_id' => $catch->id, 'confirmed_by' => $uid],
                        ['status' => 'pending']
                    );
                    if ($conf->wasRecentlyCreated) {
                        $created++;

                        //Notify reviewer
                        $reviewer = User::find($uid);
                        if($reviewer) {
                            $reviewer->notify(new CatchConfirmationRequested($catch, $session));
                        }
                    }
                }
            }
        });

        return response()->json([
            'message' => 'Sesija zatvorena i poslati zahtevi za potvrdu.',
            'created' => $created,
            'session' => $session->fresh(),
        ]);
    }



    /**
     * @param FishingSession $session
     * @return JsonResponse
     */
    public function destroy(FishingSession $session) {
        $this->authorize('delete', $session);
        $session->delete();
        return response()->json(['message'=>'Session deleted']);
    }
}
