<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use App\Http\Requests\{CatchStoreRequest, CatchUpdateRequest};
use App\Models\{FishingCatch, Event, Group};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class CatchesController extends Controller
{
    /**
     * Handles the creation of a new fishing catch resource.
     *
     * This method validates the incoming request, prepares the required data,
     * and stores the fishing catch record in the database within a transaction.
     * Additional data fields like `group_id`, `caught_at`, and `season_year`
     * are determined based on the provided input or related entities such as
     * events or groups.
     *
     * @param CatchStoreRequest $req The validated request containing the data for the fishing catch.
     * @return JsonResponse The JSON response containing the created fishing catch data.
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If the provided `event_id` does not correspond to an existing event.
     * @throws \Throwable If there is an issue during the database transaction.
     */
    public function store(CatchStoreRequest $req)
    {
        $data = $req->validated();
        $data['user_id'] = $req->user()->id;

        // Ako postoji event → preuzmi group_id iz eventa (override)
        $event = null;
        if (!empty($data['event_id'])) {
            $event = Event::findOrFail($data['event_id']);
            $data['group_id'] = $event->group_id;
        }

        // caught_at: prosleđen || start_at eventa || sada
        $caughtAt = $data['caught_at'] ?? ($event?->start_at?->toDateTimeString()) ?? now()->toDateTimeString();
        $data['caught_at'] = $caughtAt;

        // season_year: prosleđen || group.season_year || YEAR(caught_at)
        $groupSeason = optional(Group::find($data['group_id']))->season_year;
        $data['season_year'] = $data['season_year']
            ?? $groupSeason
            ?? (int) \Carbon\Carbon::parse($caughtAt)->year;

        $catch = DB::transaction(fn() => FishingCatch::create($data));

        return response()->json(['data' => $catch], 201);
    }

    /**
     * Retrieves and displays the specified fishing catch resource.
     *
     * This method ensures that the authenticated user has the permission to
     * view the provided fishing catch resource. It also eagerly loads related
     * entities, such as the user, group, event, and confirmations along with
     * the confirmer details, to optimize data retrieval and reduce database queries.
     *
     * @param FishingCatch $catch The fishing catch instance to be displayed.
     * @return JsonResponse The JSON response containing the fishing catch data along with its related entities.
     * @throws AuthorizationException If the authenticated user is not authorized to view the resource.
     */
    public function show(FishingCatch $catch)
    {
        // policy: view
        return response()->json(['data' => $catch->load('user','group','event','confirmations.confirmer')]);
    }

    /**
     * Handles the update of an existing fishing catch resource.
     *
     * This method processes the incoming request, updates the fishing catch record,
     * and recalculates fields like `season_year` if necessary. Specifically,
     * if the `caught_at` field is modified and `season_year` is not explicitly
     * provided, it recalculates `season_year` based on the related group's season
     * or the year derived from the new `caught_at` date.
     *
     * @param CatchUpdateRequest $req The validated request containing the updated data for the fishing catch.
     * @param FishingCatch $catch The fishing catch model instance to be updated.
     * @return JsonResponse The JSON response containing the updated fishing catch data.
     * @throws \Throwable If there is an issue during the save process or the field calculations.
     */
    public function update(CatchUpdateRequest $req, FishingCatch $catch)
    {
        $data = $req->validated();

        // Ako korisnik menja caught_at, re-izračunaj season_year (osim ako ga eksplicitno pošalje)
        if (array_key_exists('caught_at', $data) && !array_key_exists('season_year', $data)) {
            $groupSeason = optional($catch->group)->season_year;
            $data['season_year'] = $groupSeason ?? (int) Carbon::parse($data['caught_at'])->year;
        }

        $catch->fill($data)->save();

        return response()->json(['data' => $catch->fresh()]);
    }

    /**
     * Handles the deletion of a fishing catch resource.
     *
     * This method removes the specified fishing catch record from the database.
     * Authorization is enforced through a policy to ensure proper permissions
     * such as ownership or administrative rights.
     *
     * @param Request $req The incoming request object.
     * @param FishingCatch $catch The fishing catch instance to be deleted.
     * @return Response An HTTP 204 no content response upon successful deletion.
     * @throws AuthorizationException If the policy denies the action.
     */
    public function destroy(Request $req, FishingCatch $catch)
    {
        // policy: delete (vlasnik/admin)
        $catch->delete();
        return response()->noContent();
    }

    /**
     * Retrieves a paginated list of fishing catches associated with a specific event.
     *
     * This method fetches catches linked to the given event, including their related user data,
     * and returns the results ordered by the most recent catches.
     *
     * @param Request $req The incoming HTTP request instance.
     * @param Event $event The event model instance for which catches are being retrieved.
     * @return JsonResponse The JSON response containing the paginated list of catches.
     */
    public function byEvent(Request $req, Event $event)
    {
        $year = $req->integer('season_year');
        $from = $req->query('from'); // ISO
        $to   = $req->query('to');

        $q = $event->catches()->with('user')
            ->season($year)
            ->between($from, $to)
            ->latest('caught_at');

        return response()->json(['data' => $q->paginate(20)]);
    }

    /**
     * Retrieves a paginated list of fishing catches for the authenticated user.
     *
     * This method queries the `FishingCatch` model to fetch records associated
     * with the authenticated user's ID. It also includes relationships such as
     * `event` and `group` and orders the results based on the latest entries.
     * The results are paginated by 20 entries per page.
     *
     * @param Request $req The incoming request instance, used to identify the authenticated user.
     * @return JsonResponse The JSON response containing the paginated fishing catch data.
     */
    public function mine(Request $req)
    {
        $year = $req->integer('season_year');
        $from = $req->query('from'); $to = $req->query('to');

        $q = FishingCatch::query()
            ->where('user_id', $req->user()->id)
            ->with('event','group')
            ->season($year)
            ->between($from, $to)
            ->latest('caught_at');

        return response()->json(['data' => $q->paginate(20)]);
    }
}
