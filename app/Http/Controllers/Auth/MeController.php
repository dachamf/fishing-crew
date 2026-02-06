<?php
namespace App\Http\Controllers\Auth;


use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MeController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user()->load([
            'profile',
            'groups:id,name,season_year',
        ]);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email'=> $user->email,
            'profile' => $user->profile,
            'groups' => $user->groups->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'season_year' => $g->season_year,
                'role' => $g->pivot->role ?? 'member',
            ]),
        ]);
    }

    /**
     * Determines and returns the roles of a user in a specific group.
     *
     * This function validates the group ID from the incoming request,
     * checks the association of the authenticated user with the group,
     * and identifies the user's roles in the group including owner,
     * moderator, or member. If no roles are found, access is denied.
     *
     * @param Request $r The HTTP request instance containing the 'group_id'.
     *
     * @return JsonResponse A JSON response containing the group ID and the user's roles.
     */
    public function roles(Request $r): JsonResponse
    {
        $data = $r->validate([
            'group_id' => ['required','integer','exists:groups,id'],
        ]);

        $gid = (int) $data['group_id'];
        $uid = (int) $r->user()->id;

        // Pročitaj pivot red (članstvo + rola)
        $pivot = DB::table('group_user')
            ->where('group_id', $gid)
            ->where('user_id', $uid)
            ->first();

        $roles = [];

        if ($pivot) {
            $role = strtolower(trim((string) ($pivot->role ?? '')));

            if ($role === 'owner') {
                $roles = ['owner', 'member'];
            }
            elseif (in_array($role, ['admin','moderator','mod'], true)) {
                $roles = ['mod', 'member'];
            }
            else {
                // bilo šta drugo (uklj. prazno) → član bez privilegija
                $roles = ['member'];
            }
        } else {
            // nije član grupe → nema rola
            $roles = [];
        }

        return response()->json([
            'group_id' => $gid,
            'roles'    => $roles,
        ]);
    }
}
