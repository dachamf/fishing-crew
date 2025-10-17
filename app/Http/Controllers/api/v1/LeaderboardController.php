<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\FishingCatch;
use App\Models\FishingSession;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaderboardController extends Controller
{

    /**
     * Fetches user data related to the fishing season, including profile information,
     * session counts, weight, total catches, and the biggest catch for the given season year.
     *
     * @param Request $request The incoming HTTP request object containing the optional season_year.
     *
     * @return JsonResponse A JSON response containing the processed user data.
     */
    public function index(Request $request)
    {
        $season = (int) $request->input('season_year', now()->year);

        $query = User::query()
            ->with('profile')
            ->withCount(['sessions as sessions_total' => function($q) use ($season) {
                $q->where('season_year', $season);
            }])
            ->withSum(['catches as weight_total' => function($q) use ($season) {
                $q->where('season_year', $season);
            }], 'total_weight_kg')
            ->withSum(['catches as pieces_total' => function($q) use ($season) {
                $q->where('season_year', $season);
            }], 'count')
            ->addSelect([
                'biggest' => \DB::table('catches')
                    ->selectRaw('MAX(biggest_single_kg)')
                    ->whereColumn('catches.user_id', 'users.id')
                    ->where('season_year', $season)
            ]);

        // 🚫 Bez group_id filtera u single-tenant modu — SingleGroupScope već radi
        // if (!config('tenant.single.enabled')) { ... where group_id ... }

        $items = $query->get()->map(fn($u) => [
            'user'           => ['id' => $u->id, 'name' => $u->name, 'display_name' => optional($u->profile)->display_name, 'avatar_url' => optional($u->profile)->avatar_url],
            'sessions_total' => (int) ($u->sessions_total ?? 0),
            'catches_count'  => (int) ($u->catches_count ?? 0),
            'pieces_total'   => (int) ($u->pieces_total ?? 0),
            'weight_total'   => (float) ($u->weight_total ?? 0.0),
            'biggest'        => (float) ($u->biggest ?? 0.0),
        ]);

        return response()->json(['items' => $items]);
    }

}
