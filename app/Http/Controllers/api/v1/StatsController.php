<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function mySeason(Request $r)
    {
        $user = $r->user();
        $groupId = (int) $r->query('group_id');
        $year    = (int) ($r->query('year') ?? now()->year);

        // Guard: mora grupa
        if (!$groupId) {
            return response()->json([
                'message' => 'Parametar group_id je obavezan.',
            ], 422);
        }

        // Sessions (moje, u grupi, za godinu)
        $sessions = DB::table('fishing_sessions')
            ->where('group_id', $groupId)
            ->where('user_id', $user->id)
            ->where('season_year', $year)
            ->count();

        // Catches (moji, odobreni)
        $base = DB::table('catches')
            ->where('group_id', $groupId)
            ->where('user_id', $user->id)
            ->where('season_year', $year)
            ->where('status', 'approved');

        // Catches (moji, neodobreni)
        $baseUnapprovedCatches = DB::table('catches')
            ->where('group_id', $groupId)
            ->where('user_id', $user->id)
            ->where('season_year', $year)
            ->where('status', '!=','approved');

        // Approved Catches
        $catches = (clone $base)->count();
        $total   = (clone $base)->sum('total_weight_kg') ?? 0;
        $biggest = (clone $base)->max('biggest_single_kg') ?? 0;

        // Unapproved catches
        $catchesUnapproved = (clone $baseUnapprovedCatches)->count();
        $totalUnapproved = (clone $baseUnapprovedCatches)->sum('total_weight_kg') ?? 0;
        $biggestUnapproved = (clone $baseUnapprovedCatches)->max('biggest_single_kg') ?? 0;

        return response()->json([
            'sessions'           => (int) $sessions,
            'catches'            => (int) $catches,
            'total_weight_kg'    => (float) $total,
            'biggest_single_kg'  => (float) $biggest,
            'catches_unapproved'    => (int) $catchesUnapproved,
            'total_weight_kg_unapproved'    => (float) $totalUnapproved,
            'biggest_single_kg_unapproved'  => (float) $biggestUnapproved,
            'group_id'           => $groupId,
            'season_year'        => $year,
        ]);
    }
}
