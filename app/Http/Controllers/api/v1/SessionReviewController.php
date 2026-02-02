<?php
namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\FishingCatch;
use App\Models\FishingSession;
use App\Models\SessionConfirmation;
use App\Models\SessionReview;
use App\Services\SessionReviewService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SessionReviewController extends Controller
{
    public function __construct(private SessionReviewService $svc) {}

    /**
     * Handles the nomination of users for a specific fishing session.
     *
     * This method authorizes the user for the nomination action on the fishing session,
     * validates the incoming request data to ensure nominees are provided and are valid user IDs,
     * and then processes the nomination using the service layer.
     * Generates a link for the session including the token information.
     *
     * @param Request $request The HTTP request instance containing input data.
     * @param FishingSession $session The fishing session instance for which nominees are being added.
     * @return JsonResponse A JSON response indicating successful operation.
     *
     * @throws AuthorizationException If the user is not authorized to perform the action.
     * @throws ValidationException If the validation of request data fails.
     */
    public function nominate(Request $request, FishingSession $session): JsonResponse
    {
        $this->authorize('nominate', $session);

        $data = $request->validate([
            'nominees'   => 'required|array|min:1',
            'nominees.*' => 'integer|exists:users,id',
        ]);

        $this->svc->nominate(
            $session,
            $data['nominees'],
            fn($s, $c) => rtrim(config('app.frontend_url'), '/')."/sessions/{$s->id}?token={$c->plain_token}"
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Handle the confirmation of a fishing session decision.
     *
     * This method authorizes the current user to perform the action, validates the
     * incoming request data, retrieves the relevant session confirmation record, and
     * updates the session confirmation status based on the provided decision.
     *
     * @param Request $request The HTTP request object containing input data.
     * @param FishingSession $session The fishing session being confirmed.
     * @return JsonResponse Returns a JSON response with the updated session confirmation status.
     *
     * @throws AuthorizationException If the user is not authorized to perform this action.
     * @throws ValidationException If the validation of the input data fails.
     * @throws ModelNotFoundException If the session confirmation record cannot be found.
     */
    public function confirm(Request $request, FishingSession $session): JsonResponse
    {
        $this->authorize('confirm', $session);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
        ]);

        $conf = SessionConfirmation::where('session_id', $session->id)
            ->where('nominee_user_id', $request->user()->id)
            ->firstOrFail();

        $this->svc->confirm($session, $conf, $data['decision'], $request->user());
        return response()->json(['status' => $conf->fresh()->status]);
    }

    /**
     * Handles the review process for a fishing session.
     *
     * This method validates the review data, updates the session review with the provided status and note,
     * notifies the owner about the update, and finalizes the status of the related fishing catches based on
     * the aggregated review statuses. Notifications for finalization are optional and depend on configuration.
     *
     * @param Request $r The HTTP request instance containing review data.
     * @param FishingSession $session The fishing session being reviewed.
     *
     * @return JsonResponse The JSON-formatted response containing the updated session with its reviews.
     */
    public function review(Request $r, FishingSession $session): JsonResponse
    {
        $data = $r->validate([
            'status' => ['required', 'in:approved,rejected'],
            'note'   => ['nullable', 'string', 'max:500'],
        ]);

        // legacy review flow (session_reviews tabela)
        $rev = SessionReview::where('session_id', $session->id)
            ->where('reviewer_id', $r->user()->id)
            ->firstOrFail();

        $rev->update(['status' => $data['status'], 'note' => $data['note'] ?? null]);

        // per-action owner notify (legacy)
        if ($owner = $session->user()->first()) {
            $owner->notify(new \App\Notifications\OwnerSessionReviewUpdated($session, $rev, $r->user()));
        }

        // finalization (legacy session_reviews logika)
        $statuses = $session->reviews()->pluck('status');

        $finalMailEnabled = (bool) env('FC_OWNER_FINAL_SUMMARY_MAIL', false);

        if ($statuses->contains('rejected')) {
            FishingCatch::where('session_id', $session->id)->update(['status' => 'rejected']);
            if ($finalMailEnabled && $owner) {
                $owner->notify(new \App\Notifications\OwnerSessionFinalized($session, 'rejected'));
            }
        } elseif ($statuses->count() > 0 && $statuses->every(fn($s) => $s === 'approved')) {
            FishingCatch::where('session_id', $session->id)->update(['status' => 'approved']);
            if ($finalMailEnabled && $owner) {
                $owner->notify(new \App\Notifications\OwnerSessionFinalized($session, 'approved'));
            }
        }

        return response()->json($session->fresh()->load('reviews'));
    }

    /**
     * Handle the confirmation of a fishing session decision using a token.
     *
     * This method validates the incoming request data, retrieves the relevant session
     * confirmation record using the provided token, and updates the session confirmation
     * status based on the given decision.
     *
     * @param Request $request The HTTP request object containing input data.
     * @param FishingSession $session The fishing session being confirmed.
     * @param string $token The unique token associated with the session confirmation.
     * @return JsonResponse Returns a JSON response with the updated session confirmation status.
     *
     * @throws ValidationException If the validation of the input data fails.
     * @throws ModelNotFoundException If the session confirmation record cannot be found using the token.
     */
    public function confirmByToken(Request $request, FishingSession $session, string $token): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved','rejected'])],
        ]);

        $conf = SessionConfirmation::findByPlainToken($session->id, $token);
        if (!$conf) {
            abort(404);
        }

        $this->svc->confirm($session, $conf, $data['decision'], $conf->nominee, silent: false);
        return response()->json(['status' => $conf->fresh()->status]);
    }

    /**
     * Finalizes the provided fishing session.
     *
     * This method ensures the user is authorized to finalize the session, invokes the service to handle
     * potential finalization logic, and returns a JSON response with updated session information, including
     * the final result and timestamp of finalization.
     *
     * @param FishingSession $session The fishing session to be finalized.
     *
     * @return JsonResponse The JSON-formatted response containing the session's final result and finalization timestamp.
     */
    public function finalize(FishingSession $session): JsonResponse
    {
        $this->authorize('finalize', $session);
        $this->svc->maybeFinalize($session);
        $fresh = $session->fresh();
        return response()->json([
            'final_result' => $fresh->final_result,
            'finalized_at' => $fresh->finalized_at,
        ]);
    }

    /**
     * Retrieves fishing sessions assigned to the authenticated user for review or confirmation.
     *
     * This method queries fishing sessions where the authenticated user has pending confirmation statuses,
     * includes the count of related catches, and eager loads user-related data (profile and name) as well as
     * detailed confirmation records belonging to the user. The results are paginated and sorted by the most
     * recent session start date and ID.
     *
     * @param Request $r The HTTP request instance, used to identify the authenticated user.
     *
     * @return JsonResponse The paginated list of fishing sessions assigned to the user in JSON format.
     */
    public function assignedToMe(Request $r): JsonResponse
    {
        $q = FishingSession::query()
            ->whereHas('confirmations', fn($c) =>
            $c->where('nominee_user_id', $r->user()->id)->where('status', 'pending')
            )
            ->withCount('catches')
            ->with([
                'user:id,name',
                'user.profile:id,user_id,display_name,avatar_path',
                'confirmations' => fn($qq) => $qq
                    ->select('id','session_id','nominee_user_id','status','decided_at','created_at','updated_at')
                    ->where('nominee_user_id', $r->user()->id),
            ])
            ->latest('started_at')->latest('id');

        return response()->json($q->paginate(20));
    }

    /**
     * Retrieves the count of pending confirmations assigned to the authenticated user.
     *
     * This method calculates the total number of fishing session confirmations that are in a pending
     * state and assigned to the currently authenticated user based on their ID.
     *
     * @param Request $r The HTTP request instance containing the authenticated user information.
     *
     * @return JsonResponse The JSON-formatted response containing the total count of pending confirmations.
     */
    public function assignedCount(Request $r): JsonResponse
    {
        $total = FishingSession::query()
            ->whereHas('confirmations', fn($c) =>
            $c->where('nominee_user_id', $r->user()->id)->where('status', 'pending')
            )
            ->count();

        return response()->json(['total_pending' => $total]);
    }
}
