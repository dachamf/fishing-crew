<?php
namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\{FishingCatch, CatchConfirmation, Score};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatchesConfirmationController extends Controller
{
    /**
     * Handles the storage of a fishing catch confirmation, validating the request data
     * and updating the confirmation status based on user input. The function ensures
     * appropriate authorization, processes database transactions, and recalculates
     * scores if necessary.
     *
     * @param Request $req The HTTP request containing the confirmation details.
     * @param FishingCatch $catch The fishing catch instance being confirmed.
     *
     * @return JsonResponse A JSON response indicating the outcome of the operation.
     *
     * @throws AuthorizationException If the user is not authorized for the action.
     * @throws ValidationException|\Throwable If the request data fails validation.
     */
    public function store(Request $req, FishingCatch $catch)
    {
        $this->authorize('confirm', $catch);

        $data = $req->validate([
            'status' => ['required', Rule::in(['approved','rejected'])],
            'note'   => ['nullable','string','max:500'],
        ]);

        DB::transaction(function() use ($catch,$req,$data){
            CatchConfirmation::updateOrCreate(
                ['catch_id'=>$catch->id,'confirmed_by'=>$req->user()->id],
                ['status'=>$data['status'],'note'=>$data['note'] ?? null]
            );

            // jednostavna logika: ako postoji bar 1 "rejected" -> rejected,
            // inače ako postoji bar 1 "approved" -> approved
            $hasRejected = $catch->confirmations()->where('status','rejected')->exists();
            $hasApproved = $catch->confirmations()->where('status','approved')->exists();

            $catch->status = $hasRejected ? 'rejected' : ($hasApproved ? 'approved' : 'pending');
            $catch->save();

            if ($catch->status === 'approved') {
                $this->recalcScoresFor($catch);
            }
        });

        return response()->json(['message' => 'Saved.']);
    }

    /**
     * Recalculates the scores for a specific fishing catch by updating or creating
     * the corresponding score record in the database. This function determines the
     * current season, calculates points based on activity and weight, and updates
     * the largest single catch if applicable.
     *
     * @param FishingCatch $catch The fishing catch instance for which scores are recalculated.
     *
     * @return void
     */
    protected function recalcScoresFor(FishingCatch $catch): void
    {
        $season = $catch->season_year ?? optional($catch->group)->season_year ?? now()->year;

        $row = Score::firstOrCreate(
            ['group_id'=>$catch->group_id,'user_id'=>$catch->user_id,'season_year'=>$season],
            []
        );

        // primer bodovanja (odredi po pravilniku):
        $activity = 1; // svaka prijava
        $weight   = (int) round(($catch->total_weight_kg ?? 0) * 10); // npr. 0.1 poeni po 10g
        $row->activity_points += $activity;
        $row->weight_points   += $weight;
        $row->total_points     = $row->activity_points + $row->weight_points;

        if ($catch->biggest_single_kg !== null) {
            $row->biggest_single_kg = max((float)($row->biggest_single_kg ?? 0), (float)$catch->biggest_single_kg);
        }

        $row->save();
    }
}
