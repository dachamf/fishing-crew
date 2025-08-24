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

        if ($r->filled('group_id'))    $q->where('group_id', (int)$r->group_id);
        if ($r->filled('user_id'))     $q->where('user_id', (int)$r->user_id);
        if ($r->filled('status'))      $q->where('status', $r->status);
        if ($r->filled('season_year')) $q->where('season_year', (int)$r->season_year);
        if ($r->filled('from') || $r->filled('to')) {
            $from = $r->filled('from') ? Carbon::parse($r->from) : null;
            $to   = $r->filled('to')   ? Carbon::parse($r->to)   : null;
            $q->between($from,$to);
        }

        $q->latest('started_at')->latest('id');

        return response()->json($q->paginate(20));
    }

    public function show(FishingSession $session) {
        $this->authorize('view', $session);
        $session->load([
            'user:id,name',
            'user.profile:id,user_id,display_name,avatar_path',
            'catches' => function($q){ $q->orderByDesc('caught_at')->orderByDesc('id'); },
            'catches.user:id,name',
            'event:id,title,start_at',
        ]);
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
            'water_body' => ['nullable','string','max:100'],
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
