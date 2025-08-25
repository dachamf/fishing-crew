<?php
namespace App\Http\Controllers\Auth;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

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
            'groups' => $user->groups->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'season_year' => $g->season_year,
                'role' => $g->pivot->role ?? 'member',
            ]),
        ]);
    }
}
