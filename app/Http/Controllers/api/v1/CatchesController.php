<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use App\Http\Requests\{CatchStoreRequest, CatchUpdateRequest};
use App\Models\{FishingCatch, Event};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatchesController extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $memberGroupIds = $request->user()->groups()->pluck('groups.id');
        $q = FishingCatch::query()
            ->with([
                'user:id,name',
                'user.profile:id,user_id,display_name,avatar_path',
                'group:id,name',
                'session:id,group_id,title,started_at,ended_at,location_name', // naziv tvog modela relacije
                'session.photos', // do 3 fotke na FE ionako se režu
            ])
            ->whereIn('group_id', $memberGroupIds);

        // po defaultu - samo moj ulov
        $q->where('user_id', $request->user()->id);
        if ($gid = $request->integer('group_id')) {
            $q->where('group_id', $gid);
        }

        // opcioni filteri:
        if ($request->filled('status')) $q->where('status', $request->status);
        if ($request->filled('from')) $q->where('caught_at', '>=', Carbon::parse($request->from));
        if ($request->filled('to')) $q->where('caught_at', '<=', Carbon::parse($request->to));

        $q->latest('caught_at')->latest('id');

        return response()->json($q->paginate(20));
    }

    /**
     * @param FishingCatch $catch
     * @return JsonResponse
     */
    public function show(FishingCatch $catch, $id)
    {
        $catch = FishingCatch::with(['photos', 'confirmations', 'user:id,name', 'user.profile:id,user_id,avatar_path', 'event:id,title'])->findOrFail($id);
        $this->authorize('view', $catch); // po potrebi
        return response()->json($catch);
    }

    /**
     * Handles the creation of a new fishing catch resource.
     *
     * This method validates the incoming request, prepares the required data,
     * and stores the fishing catch record in the database within a transaction.
     * Additional data fields like `group_id`, `caught_at`, and `season_year`
     * are determined based on the provided input or related entities such as
     * events or groups.
     *
     * @param Request $r
     * @return JsonResponse The JSON response containing the created fishing catch data.
     */

    public function store(CatchStoreRequest $r)
    {
        $v = $r->validated();

        $catch = new FishingCatch($v);
        $catch->user_id = $r->user()->id;
        $catch->status = 'pending';
        $catch->caught_at = $v['caught_at'] ?? now();
        $catch->season_year = $v['season_year'] ?? (int)($catch->caught_at?->format('Y'));
        $catch->save();

        return response()->json($catch->load([
            'user:id,name',
            'user.profile:id,user_id,display_name,avatar_path',
            'photos', 'confirmations'
            ]
        ), 201);
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
     * @param Request $r
     * @param $id
     * @return JsonResponse The JSON response containing the updated fishing catch data.
     */

    public function update(CatchUpdateRequest $r, $id)
    {
        $c = FishingCatch::with('confirmations')->findOrFail($id);
        $this->authorize('update', $c);

        $v = $r->validated();

        $c->fill(array_filter($v, fn($x) => !is_null($x)))->save();

        // svaka izmena resetuje SVE pending/approve u pending (po želji)
        $c->confirmations()->update(['status' => 'pending', 'suggested_payload' => null]);

        return response()->json($c->fresh()->load(['photos', 'confirmations']));
    }

    /**
     * Handles the deletion of a fishing catch resource.
     *
     * This method removes the specified fishing catch record from the database.
     * Authorization is enforced through a policy to ensure proper permissions
     * such as ownership or administrative rights.
     *
     * @param Request $r
     * @param $id
     * @return JsonResponse An HTTP 204 no content response upon successful deletion.
     */

    public function destroy(Request $r, $id)
    {
        $c = FishingCatch::findOrFail($id);
        $this->authorize('delete', $c);
        $c->delete();
        return response()->json(['message' => 'Deleted']);
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
        $to = $req->query('to');

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
        $from = $req->query('from');
        $to = $req->query('to');

        $q = FishingCatch::query()
            ->where('user_id', $req->user()->id)
            ->with('event', 'group')
            ->season($year)
            ->between($from, $to)
            ->latest('caught_at');

        return response()->json(['data' => $q->paginate(20)]);
    }
}
