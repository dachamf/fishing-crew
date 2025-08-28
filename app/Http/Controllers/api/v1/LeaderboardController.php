<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\FishingCatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaderboardController extends Controller
{
    // GET /v1/leaderboard?group_id=G&year=Y&limit=5&include=user
    public function index(Request $r)
    {
        $gid   = (int) $r->query('group_id');
        $year  = (int) $r->query('year', now()->year);
        $limit = min(50, (int) $r->query('limit', 5));

        // metrika: total_weight + broj ulova + biggest_single
        $q = FishingCatch::query()
            ->select([
                'fishing_catches.user_id',
                DB::raw('SUM(COALESCE(fishing_catches.total_weight_kg, fishing_catches.weight_kg)) AS total_weight_kg'),
                DB::raw('COUNT(*) AS catches_count'),
                DB::raw('MAX(COALESCE(fishing_catches.weight_kg, 0)) AS biggest_single_kg'),
            ])
            ->join('fishing_sessions as s', 's.id', '=', 'fishing_catches.session_id')
            ->when($gid, fn($qq) => $qq->where('s.group_id', $gid))
            ->whereYear('s.started_at', $year)
            ->groupBy('fishing_catches.user_id')
            ->orderByDesc('total_weight_kg');

        // ONLY_FULL_GROUP_BY safe: selektujemo i grupišemo striktno po navedenim poljima

        $rows = $q->limit($limit)->get();

        if (str_contains((string)$r->query('include',''), 'user')) {
            $rows->load(['user:id,name', 'user.profile:id,user_id,display_name,avatar_path']);
        }

        // biggest overall (po pojedinačnom ulovu)
        $biggest = FishingCatch::query()
            ->select(['id','user_id','session_id',
                DB::raw('COALESCE(weight_kg, total_weight_kg) as weight_kg')])
            ->join('fishing_sessions as s', 's.id','=','fishing_catches.session_id')
            ->when($gid, fn($qq) => $qq->where('s.group_id', $gid))
            ->whereYear('s.started_at', $year)
            ->orderByDesc(DB::raw('COALESCE(weight_kg, total_weight_kg)'))
            ->first();

        if ($biggest) {
            $biggest->load(['user:id,name', 'user.profile:id,user_id,display_name,avatar_path']);
        }

        return response()->json([
            'top'     => $rows,
            'biggest' => $biggest,
        ]);
    }
}
