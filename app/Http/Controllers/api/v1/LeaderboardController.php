<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\FishingCatch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaderboardController extends Controller
{
    public function index(Request $r)
    {
        $groupId = (int) $r->query('group_id');
        $season  = (int) $r->query('season_year', now()->year);

        // 1) Bazni agregat po useru
        $base = FishingCatch::query()
            ->where('group_id', $groupId)
            ->where('season_year', $season)
            ->where('status', 'approved')
            ->selectRaw('
                user_id,
                COUNT(*)                         AS catches_total,
                COALESCE(SUM(`count`), 0)        AS pieces_total,
                COALESCE(SUM(total_weight_kg),0) AS weight_total,
                COALESCE(MAX(biggest_single_kg),0) AS biggest
            ')
            ->groupBy('user_id');

        // 2) Spoljašnji upit nad subquery-jem + join na users/profiles
        $q = DB::query()->fromSub($base, 'L')
            ->join('users', 'users.id', '=', 'L.user_id')
            ->leftJoin('profiles', 'profiles.user_id', '=', 'users.id')
            ->select([
                'L.user_id',
                'L.catches_total',
                'L.pieces_total',
                'L.weight_total',
                'L.biggest',
                'users.id as u_id',
                'users.name',
                DB::raw('profiles.display_name'),
                DB::raw('profiles.avatar_path'),
            ]);

        // sorting (dozvoli samo poznate kolone)
        $sort = $r->query('sort', 'weight_total');
        $dir  = strtolower($r->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['weight_total', 'catches_total', 'pieces_total', 'biggest'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'weight_total';
        }

        $q->orderBy($sort, $dir)->orderBy('L.user_id');

        $perPage = min(100, (int) $r->query('per_page', 50));
        $page    = $q->paginate($perPage);

        // 3) Upakuj user objekat u svaki red radi FE očekivanja
        $page->getCollection()->transform(function ($row) {
            $row->user = [
                'id'           => (int) $row->u_id,
                'name'         => $row->name,
                'display_name' => $row->display_name ?? null,
                // prilagodi ako praviš full URL od avatar_path
                'avatar_url'   => $row->avatar_path ? asset('storage/'.$row->avatar_path) : null,
            ];
            unset($row->u_id, $row->name, $row->display_name, $row->avatar_path);
            return $row;
        });

        return response()->json([
            'items' => $page->items(),
            'meta'  => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
            ],
        ]);
    }
}
