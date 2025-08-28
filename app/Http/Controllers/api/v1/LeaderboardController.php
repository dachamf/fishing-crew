<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\FishingCatch;
use App\Models\FishingSession;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaderboardController extends Controller
{
    // GET /v1/leaderboard?group_id=G&year=Y&limit=5&include=user
    public function index(Request $r, ?\App\Models\Group $group = null)
    {
        $gid   = $group?->id ?? (int) $r->query('group_id');
        $year  = (int) $r->query('year', now()->year);
        $limit = min(50, (int) $r->query('limit', 5));

        $catchTable   = (new FishingCatch)->getTable();      // npr. "catches"
        $sessionTable = (new FishingSession)->getTable();    // npr. "fishing_sessions"

        // TOP: zbir po korisniku (ukupna težina), broj ulova i najveći pojedinačni ulov
        $top = DB::table("$catchTable as c")
            ->join("$sessionTable as s", 's.id', '=', 'c.session_id')
            ->when($gid, fn($qq) => $qq->where('s.group_id', $gid))
            ->whereYear('s.started_at', $year)
            ->selectRaw("
            s.user_id AS user_id,
            SUM(COALESCE(c.total_weight_kg, 0)) AS total_weight_kg,
            COUNT(*) AS catches_count,
            MAX(COALESCE(c.total_weight_kg, 0)) AS biggest_single_kg
        ")
            ->groupBy('s.user_id')
            ->orderByDesc('total_weight_kg')
            ->limit($limit)
            ->get();

        // Učitaj korisnike (za mini-LB prikaz)
        $users = User::whereIn('id', $top->pluck('user_id'))
            ->with('profile:id,user_id,display_name,avatar_path')
            ->get()->keyBy('id');

        $top->transform(function ($row) use ($users) {
            $row->user = $users[$row->user_id] ?? null;
            return $row;
        });

        // BIGGEST: najteži pojedinačni ulov (alias kao weight_kg radi FE kompatibilnosti)
        $biggest = DB::table("$catchTable as c")
            ->join("$sessionTable as s", 's.id', '=', 'c.session_id')
            ->when($gid, fn($qq) => $qq->where('s.group_id', $gid))
            ->whereYear('s.started_at', $year)
            ->selectRaw("
            c.id, c.session_id,
            s.user_id AS user_id,
            COALESCE(c.total_weight_kg, 0) AS weight_kg
        ")
            ->orderByDesc(DB::raw('COALESCE(c.total_weight_kg, 0)'))
            ->first();

        if ($biggest) {
            $biggestUser = $users[$biggest->user_id]
                ?? User::with('profile:id,user_id,display_name,avatar_path')->find($biggest->user_id);
            $biggest->user = $biggestUser;
        }

        return response()->json([
            'top'     => $top,
            'biggest' => $biggest,
        ]);
    }
}
