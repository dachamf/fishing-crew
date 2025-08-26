<?php
namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\FishingSession;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class FishingSessionController extends Controller
{
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

    public function store(Request $r) {
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

        $session->fill($data)->save();

        return response()->json($session->fresh());
    }

    public function close(Request $r, FishingSession $session) {
        $this->authorize('update', $session);
        $session->update([
            'status'   => 'closed',
            'ended_at' => $session->ended_at ?? now(),
        ]);
        return response()->json(['message'=>'Session closed','session'=>$session->fresh()]);
    }

    public function destroy(FishingSession $session) {
        $this->authorize('delete', $session);
        $session->delete();
        return response()->json(['message'=>'Session deleted']);
    }
}
