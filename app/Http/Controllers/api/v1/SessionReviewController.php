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

    public function __construct(private SessionReviewService $svc)
    {
    }

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
    public function nominate(Request $request, FishingSession $session)
    {
        $this->authorize('nominate', $session);

        $data = $request->validate([
            'nominees' => 'required|array|min:1',
            'nominees.*' => 'integer|exists:users,id',
        ]);

        $this->svc->nominate($session, $data['nominees'], fn($s,$c) => config('app.frontend_url')."/sessions/{$s->id}?token={$c->token}");

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
    public function confirm(Request $request, FishingSession $session)
    {
        $this->authorize('confirm', $session);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved','rejected'])],
        ]);

        $conf = SessionConfirmation::where('session_id', $session->id)
            ->where('nominee_user_id', $request->user()->id)
            ->firstOrFail();

        $this->svc->confirm($session, $conf, $data['decision'], $request->user());
        return response()->json(['status' => $conf->fresh()->status]);
    }

    public function review(Request $r, FishingSession $session) {
        $data = $r->validate([
            'status' => ['required','in:approved,rejected'],
            'note'   => ['nullable','string','max:500'],
        ]);

        // samo nominovani može da glasa
        $rev = SessionReview::where('session_id',$session->id)
            ->where('reviewer_id',$r->user()->id)
            ->firstOrFail();

        $rev->update(['status'=>$data['status'],'note'=>$data['note'] ?? null]);

        // obavesti vlasnika SVAKI PUT kad neko glasa
        $owner = $session->user()->first();
        $who   = $r->user();
        if ($owner) {
            $owner->notify(new \App\Notifications\OwnerSessionReviewUpdated($session, $rev, $who));
        }

        // finalizacija sesije (mass update ulova) kad se steknu uslovi
        $statuses = $session->reviews()->pluck('status');

        $finalMailEnabled = (bool) env('FC_OWNER_FINAL_SUMMARY_MAIL', false);

        if ($statuses->contains('rejected')) {
            FishingCatch::where('session_id',$session->id)->update(['status'=>'rejected']);
            if ($finalMailEnabled && $owner) {
                $owner->notify(new \App\Notifications\OwnerSessionFinalized($session, 'rejected'));
            }
        } elseif ($statuses->count() > 0 && $statuses->every(fn($s)=>$s==='approved')) {
            FishingCatch::where('session_id',$session->id)->update(['status'=>'approved']);
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
    public function confirmByToken(Request $request, FishingSession $session, string $token)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved','rejected'])],
        ]);

        $conf = SessionConfirmation::where('session_id', $session->id)
            ->where('token', $token)
            ->firstOrFail();

        $this->svc->confirm($session, $conf, $data['decision'], $conf->nominee, silent: false);
        return response()->json(['status' => $conf->fresh()->status]);
    }

    /**
     * @param FishingSession $session
     * @return JsonResponse
     */
    public function finalize(FishingSession $session)
    {
        $this->authorize('finalize', $session);
        app(SessionReviewService::class)->maybeFinalize($session);
        return response()->json(['final_result' => $session->fresh()->final_result, 'finalized_at' => $session->fresh()->finalized_at]);
    }

    /**
     * Retrieve fishing sessions assigned to the currently authenticated user for review.
     * Filters sessions where the user is the reviewer and the review status is pending.
     * Includes count of catches and specific related user details.
     *
     * @param Request $r The incoming HTTP request containing user authentication data.
     * @return JsonResponse A paginated JSON response with the fishing session data.
     */
    public function assignedToMe(Request $r) {
        $q = FishingSession::query()
            ->whereHas('reviews', fn($x)=>$x->where('reviewer_id',$r->user()->id)->where('status','pending'))
            ->withCount('catches')
            ->with([
                'user:id,name',
                'user.profile:id,user_id,display_name,avatar_path'
            ])
            ->latest('started_at')->latest('id');

        return response()->json($q->paginate(20));
    }
}
