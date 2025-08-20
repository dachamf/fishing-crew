<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\FishingCatch;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatchesController extends Controller
{

    /**
     * POST /api/catches
     * @param Request $req
     * @return JsonResponse
     */
    public function store(Request $req)
    {
        $data = $req->validate([
            'group_id' => ['required', 'integer', 'exists:groups,id'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'species' => ['nullable', 'string', 'max:255'],
            'count' => ['required', 'integer', 'min:1'],
            'total_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'biggest_single_kg' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        // Event (ako je prosleđen) mora pripadati istoj grupi
        if (!empty($data['event_id'])) {
            $event = Event::find($data['event_id']);
            if (!$event || $event->group_id !== (int)$data['group_id']) {
                return response()->json(['message' => 'Event does not belong to the given group'], 422);
            }
        }

        $catch = FishingCatch::create([
            'group_id' => $data['group_id'],
            'user_id' => $req->user()->id,
            'event_id' => $data['event_id'] ?? null,
            'species' => $data['species'] ?? null,
            'count' => $data['count'],
            'total_weight_kg' => $data['total_weight_kg'] ?? null,
            'biggest_single_kg' => $data['biggest_single_kg'] ?? null,
            'note' => $data['note'] ?? null,
            'status' => 'pending',
        ]);

        // TODO: emitovati notifikaciju grupi/validatorima da potvrde ulov

        return response()->json($catch->fresh(), 201);
    }


    /**
     * POST /api/catches/{catch}/confirm
     * @param Request $req
     * @param FishingCatch $catch
     * @return JsonResponse
     */
    public function confirm(Request $req, FishingCatch $catch)
    {
        $data = $req->validate([
            'status' => ['required', 'in:approved,rejected'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        // Ne možeš potvrditi sopstveni ulov
        if ($catch->user_id === $req->user()->id) {
            return response()->json(['message' => 'You cannot confirm your own catch'], 403);
        }

        // Provera da je potvrđivač član iste grupe (group_user pivot)
        $isMember = DB::table('group_user')->where([
            'group_id' => $catch->group_id,
            'user_id' => $req->user()->id,
        ])->exists();

        if (!$isMember) {
            return response()->json(['message' => 'Only group members can confirm a catch'], 403);
        }

        // Upis/override potvrde (unique: catch_id + confirmed_by)
        $catch->confirmations()->updateOrCreate(
            ['confirmed_by' => $req->user()->id],
            ['status' => $data['status'], 'note' => $data['note'] ?? null]
        );

        // Biznis pravilo: 1 approval ⇒ catch je approved; 1 rejection ⇒ rejected
        // (Po potrebi promeni na “većina”, prag itd.)
        if ($data['status'] === 'approved') {
            $catch->status = 'approved';
        } else {
            $catch->status = 'rejected';
        }
        $catch->save();

        // TODO: trigger re-računavanje bodova (scores), notifikacije

        return response()->json($catch->load(['confirmations']), 200);
    }


    /**
     * GET /api/users/{user}/catches?status=approved|pending|rejected
     * @param Request $req
     * @param int $userId
     * @return mixed
     */
    public function listByUser(Request $req, int $userId)
    {
        $q = FishingCatch::where('user_id', $userId)->latest();

        if ($status = $req->query('status')) {
            $q->where('status', $status);
        }

        return $q->paginate(20);
    }

    /**
     * GET /catches/all
     * @param Request $req
     * @return JsonResponse
     */
    public function listByAll(Request $req): JsonResponse
    {
        $users = User::all();
        $usersCatches = [];
        foreach ($users as $user) {

            // svi ulovi
            $all = $user->catches()->latest()->get();

            // samo odobreni (možeš i sa scope-om ako si ga dodao u model)
            $approved = $user->approvedCatches()->get();

            // statistika
            $totalWeight = $user->approvedCatches()->sum('total_weight_kg');
            $biggest = $user->approvedCatches()->max('biggest_single_kg');

            $usersCatches[$user->name] = [
                'all' => $all,
                'approved' => $approved,
                'total' => $totalWeight,
                'biggest' => $biggest,
            ];

        }

        return response()->json(['catches' => $usersCatches], 200);
    }
}
