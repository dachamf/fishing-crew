<?php
namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\FishingCatch;
use App\Models\FishingSession;
use App\Models\SessionReview;
use Illuminate\Http\Request;

class SessionReviewController extends Controller
{
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

    public function assignedToMe(Request $r) {
        $q = FishingSession::query()
            ->whereHas('reviews', fn($x)=>$x->where('reviewer_id',$r->user()->id)->where('status','pending'))
            ->withCount('catches')
            ->with(['user:id,name','user.profile:id,user_id,display_name,avatar_path'])
            ->latest('started_at')->latest('id');

        return response()->json($q->paginate(20));
    }
}
