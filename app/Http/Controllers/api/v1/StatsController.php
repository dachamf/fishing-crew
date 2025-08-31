<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\FishingCatch;
use App\Models\FishingSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

    public function speciesTop(Request $r)
    {
        $gid     = (int) $r->query('group_id');
        $year    = (int) $r->query('year', now()->year);
        $limit   = min(50, (int) $r->query('limit', 5));
        $scopeMe = ($r->query('scope') === 'me');
        $userId  = $scopeMe ? (int) $r->user()->id : null;

        $catchTable   = (new FishingCatch)->getTable();      // npr. "catches"
        $sessionTable = (new FishingSession)->getTable();    // npr. "fishing_sessions"

        // 1) Dinamički sklopi kandidate za labelu
        $labelParts = [];

        // prvo probaj iz povezane "species" tabele, ako postoji
        $joinSpecies = Schema::hasColumn($catchTable, 'species_id') && Schema::hasTable('species');
        if ($joinSpecies) {
            foreach (['label','name_sr','name','code'] as $col) {
                if (Schema::hasColumn('species', $col)) {
                    $labelParts[] = "sp.$col";
                }
            }
        }

        // zatim fallback kolone iz "catches" tabele (uzmi samo one koje stvarno postoje)
        foreach (['species_name','species','species_code','species_key'] as $col) {
            if (Schema::hasColumn($catchTable, $col)) {
                $labelParts[] = "c.$col";
            }
        }

        // ako baš ništa ne postoji, koristi literal
        $labelSql = $labelParts
            ? 'COALESCE(' . implode(', ', $labelParts) . ", 'Unknown')"
            : "'Unknown'";

        // 2) Upit
        $q = DB::table("$catchTable as c")
            ->join("$sessionTable as s", 's.id', '=', 'c.session_id')
            ->when($joinSpecies, fn($qq) => $qq->leftJoin('species as sp', 'sp.id', '=', 'c.species_id'))
            ->when($gid, fn($qq) => $qq->where('s.group_id', $gid))
            ->when($userId, fn($qq) => $qq->where('s.user_id', $userId))
            ->whereYear('s.started_at', $year)
            ->selectRaw("
            {$labelSql} AS label,
            COUNT(*) AS cnt,
            SUM(COALESCE(c.total_weight_kg, 0)) AS total_kg
        ")
            // radi i sa ONLY_FULL_GROUP_BY jer grupišemo po istom izrazu:
            ->groupBy(DB::raw($labelSql))
            ->orderByDesc('cnt')
            ->limit($limit);

        $rows = $q->get();

        return response()->json($rows);
    }
}
