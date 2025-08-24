<?php
namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\FishingCatch;
use App\Models\FishingSession;
use Illuminate\Http\Request;

class SessionCatchController extends Controller
{
    // POST /sessions/{session}/catches/stack
    public function upsert(Request $r, FishingSession $session)
    {
        $this->authorize('update', $session);

        $data = $r->validate([
            'species_id'=> ['required','integer','exists:species,id'],
            'count'     => ['nullable','integer','min:1'],
            'weight_kg' => ['nullable','numeric','min:0'],
            'caught_at' => ['nullable','date'],
            'note'      => ['nullable','string','max:500'],
        ]);

        $count = (int)($data['count'] ?? 1);
        $w     = (float)($data['weight_kg'] ?? 0);

        $catch = FishingCatch::firstOrCreate([
            'session_id' => $session->id,
            'user_id'    => $r->user()->id,
            'group_id'   => $session->group_id,
            'event_id'   => $session->event_id,
            'species_id' => $session->species_id,
        ], [
            'count'            => 0,
            'total_weight_kg'  => 0,
            'biggest_single_kg'=> null,
            'status'           => 'pending',
            'season_year'      => $session->season_year,
            'caught_at'        => $data['caught_at'] ?? now(),
            'note'             => $data['note'] ?? null,
        ]);

        $catch->increment('count', $count);
        if ($w > 0) {
            $catch->total_weight_kg   = ($catch->total_weight_kg ?? 0) + $w;
            $catch->biggest_single_kg = max($catch->biggest_single_kg ?? 0, $w);
        }
        if (!empty($data['caught_at'])) $catch->caught_at = $data['caught_at'];
        if (!empty($data['note'])) {
            $catch->note = trim(($catch->note ? $catch->note."\n" : '').$data['note']);
        }
        $catch->save();

        return response()->json($catch->load('user:id,name,avatar_url'));
    }
}
