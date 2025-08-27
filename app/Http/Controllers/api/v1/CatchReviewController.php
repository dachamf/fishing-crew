<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\FishingCatch;
use App\Models\CatchConfirmation;
use App\Models\User;
use App\Notifications\CatchChangeRequested;
use App\Notifications\CatchConfirmationUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatchReviewController extends Controller
{
    /**
     * @throws \Throwable
     */
    public function nominate(Request $r, $id) {
        $catch = FishingCatch::with('confirmations')->findOrFail($id);
        $this->authorize('update', $catch);

        $data = $r->validate([
            'reviewer_ids' => ['required','array','min:1'],
            'reviewer_ids.*' => ['integer','exists:users,id'],
        ]);

        $existing = $catch->confirmations()->pluck('confirmed_by')->all();

        // filtriraj: unique, bez vlasnika
        $ids = collect($data['reviewer_ids'])
            ->map(fn($i)=>(int)$i)->unique()
            ->reject(fn($uid) => $uid === (int)$catch->user_id);

        // (opciono) validiraj da su u istoj grupi kao ulov
        $validIds = $ids->when(true, function($c) use ($catch) {
            return User::whereIn('id', $c->all())
                ->whereHas('groups', fn($g) => $g->where('groups.id', $catch->group_id))
                ->pluck('id');
        });

        $toCreate = array_values(array_diff($validIds->all(), $existing));

        DB::transaction(function() use ($catch, $toCreate) {
            foreach ($toCreate as $uid) {
                CatchConfirmation::create([
                    'catch_id' => $catch->id,
                    'confirmed_by' => $uid,
                    'status' => 'pending',
                ]);
                // TODO: optional notifikacija
            }
        });

        return response()->json($catch->fresh()->load('confirmations'));
    }


    public function confirm(Request $r, $id) {
        $catch = FishingCatch::with('confirmations')->findOrFail($id);

        if ((int)$catch->user_id === (int)$r->user()->id) {
            abort(403, 'Vlasnik ne može potvrditi sopstveni ulov.');
        }

        $data = $r->validate([
            'status' => ['required','in:approved,rejected'],
            'note'   => ['nullable','string','max:500'],
        ]);

        $conf = $catch->confirmations()
            ->where('confirmed_by', $r->user()->id)
            ->firstOrFail(); // samo nominovani

        if ($conf->status !== 'pending') {
            abort(422, 'Ova potvrda je već obrađena.');
        }

        $conf->update(['status'=>$data['status'],'note'=>$data['note'] ?? null]);

        $statuses = $catch->confirmations()->pluck('status');  // Collection<string>
        if ($statuses->contains('rejected')) {
            $catch->update(['status' => 'rejected']);
        } elseif ($statuses->count() > 0 && $statuses->every(fn($s) => $s === 'approved')) {
            $catch->update(['status' => 'approved']);
        } else {
            $catch->update(['status' => 'pending']);
        }

        // obavesti vlasnika ulova
        $owner = $catch->user()->first();
        if ($owner) {
            $owner->notify(new CatchConfirmationUpdated($catch, $conf));
        }

        return response()->json($catch->fresh()->load('confirmations'));
    }


    public function requestChange(Request $r, $id) {
        $catch = FishingCatch::with('confirmations')->findOrFail($id);

        $data = $r->validate([
            'suggested' => ['required','array'], // npr. {count:2,total_weight_kg:1.5}
            'note'      => ['nullable','string','max:500'],
        ]);

        $conf = $catch->confirmations()->where('confirmed_by',$r->user()->id)->firstOrFail();
        $conf->update([
            'status' => 'changes_requested',
            'suggested_payload' => $data['suggested'],
            'note' => $data['note'] ?? null,
        ]);

        $owner = $catch->user()->first();
        if ($owner) {
            $owner->notify(new CatchChangeRequested($catch, $conf));
        }

        return response()->json($catch->fresh()->load('confirmations'));
    }

    // “Ulovi koji čekaju mene”
    public function assignedToMe(Request $r) {
        $q = FishingCatch::query()
            ->whereHas('confirmations', fn($x)=>$x->where('confirmed_by',$r->user()->id)->where('status','pending'))
            ->with(['user:id,name,avatar_url','photos','confirmations']);

        return response()->json($q->orderByDesc('caught_at')->paginate(20));
    }
}
