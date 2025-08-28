<?php
namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\FishingCatch;
use App\Models\FishingSession;
use App\Models\SessionConfirmation;
use Illuminate\Http\Request;

class ActivityController extends Controller
{

    public function index(Request $r)
    {
        $limit = min(50, (int)$r->query('limit', 10));
        $gid   = $r->query('group_id');

        // 1) nove sesije
        $sessions = FishingSession::query()
            ->when($gid, fn($q) => $q->where('group_id', (int)$gid))
            ->latest('created_at')->limit($limit)->get()
            ->map(fn($s) => [
                'type' => 'session_opened',
                'id'   => $s->id,
                'at'   => $s->created_at,
                'title'=> $s->title ?? 'Nova sesija',
                'by'   => ['id'=>$s->user_id],
                'url'  => "/sessions/{$s->id}",
            ]);

        // 2) ulovi
        $catches = FishingCatch::query()
            ->when($gid, fn($q) => $q->whereHas('session', fn($qq)=>$qq->where('group_id',(int)$gid)))
            ->latest('created_at')->limit($limit)->get()
            ->map(fn($c) => [
                'type' => 'catch_added',
                'id'   => $c->id,
                'at'   => $c->created_at,
                'title'=> $c->species_label ?? $c->species ?? 'Ulov',
                'by'   => ['id'=>$c->user_id],
                'url'  => "/catches/{$c->id}",
            ]);

        // 3) review odluke (session-level)
        $reviews = SessionConfirmation::query()
            ->when($gid, fn($q) => $q->whereHas('session', fn($qq)=>$qq->where('group_id',(int)$gid)))
            ->whereNotNull('decided_at')
            ->latest('decided_at')->limit($limit)->get()
            ->map(fn($c) => [
                'type'   => $c->status === 'approved' ? 'session_approved' : 'session_rejected',
                'id'     => $c->session_id,
                'at'     => $c->decided_at,
                'title'  => 'Review odluka',
                'by'     => ['id'=>$c->nominee_user_id],
                'url'    => "/sessions/{$c->session_id}",
            ]);

        // merge + sort + slice
        $feed = collect()->merge($sessions)->merge($catches)->merge($reviews)
            ->sortByDesc('at')->values()->take($limit);

        return response()->json($feed);
    }
}
