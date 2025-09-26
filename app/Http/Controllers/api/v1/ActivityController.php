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
        $limit = min(50, (int) $r->query('limit', 10));
        $gid   = $r->query('group_id');

        // 1) nove sesije (session_opened)
        $sessions = FishingSession::query()
            ->when($gid, fn($q) => $q->where('group_id', (int) $gid))
            ->latest('created_at')->limit($limit)->get()
            ->map(fn($s) => [
                'type'  => 'session_opened',
                'id'    => $s->id,
                'at'    => $s->created_at,
                'title' => $s->title ?? 'Nova sesija',
                'by'    => ['id' => $s->user_id],
                'url'   => "/sessions/{$s->id}",
            ]);

        // 2) ulovi (catch_added)
        $catches = FishingCatch::query()
            ->when($gid, fn($q) => $q->whereHas('session', fn($qq) => $qq->where('group_id', (int) $gid)))
            ->latest('created_at')->limit($limit)->get()
            ->map(fn($c) => [
                'type'  => 'catch_added',
                'id'    => $c->id,
                'at'    => $c->created_at,
                'title' => $c->species_label ?? $c->species ?? 'Ulov',
                'by'    => ['id' => $c->user_id],
                'url'   => "/catches/{$c->id}",
            ]);

        // 3) finalizacije (session_approved/session_rejected) – 1 event po sesiji
        $finalizedSessions = FishingSession::query()
            ->when($gid, fn($q) => $q->where('group_id', (int) $gid))
            ->whereNotNull('finalized_at')
            ->latest('finalized_at')->limit($limit)->get();

        $finalized = $finalizedSessions->map(function (FishingSession $s) {
            $type = $s->final_result === 'rejected' ? 'session_rejected' : 'session_approved';
            return [
                'type'  => $type,
                'id'    => $s->id,
                'at'    => $s->finalized_at,
                'title' => $s->title ?? 'Sesija finalizovana',
                'by'    => ['id' => $s->user_id], // vlasnik kao akter
                'url'   => "/sessions/{$s->id}",
            ];
        });

        // ID-evi finalizovanih sesija (da ne dupliramo pojedinačne glasove)
        $finalizedIds = $finalizedSessions->pluck('id')->all();

        // 4) pojedinačne review odluke (token/login) SAMO za ne-finalizovane sesije
        $reviews = SessionConfirmation::query()
            ->when($gid, fn($q) => $q->whereHas('session', fn($qq) => $qq->where('group_id', (int) $gid)))
            ->whereNotNull('decided_at')
            ->when(!empty($finalizedIds), fn($q) => $q->whereNotIn('session_id', $finalizedIds))
            ->latest('decided_at')->limit($limit)->get()
            ->map(fn($c) => [
                'type'  => $c->status === 'rejected' ? 'session_rejected' : 'session_approved',
                'id'    => $c->session_id,
                'at'    => $c->decided_at,
                'title' => 'Review odluka',
                'by'    => ['id' => $c->nominee_user_id],
                'url'   => "/sessions/{$c->session_id}",
            ]);

        // merge + sort + truncate na globalni limit
        $feed = collect()
            ->merge($sessions)
            ->merge($catches)
            ->merge($finalized)
            ->merge($reviews)
            ->sortByDesc('at')
            ->values()
            ->take($limit);

        return response()->json($feed);
    }
}
