<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\FishingCatch;
use App\Models\CatchConfirmation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatchReviewController extends Controller
{
    public function nominate(Request $r, $id) {
        $catch = FishingCatch::with('confirmations')->findOrFail($id);
        $this->authorize('update', $catch); // vlasnik

        $data = $r->validate([
            'reviewer_ids' => ['required','array','min:1'],
            'reviewer_ids.*' => ['integer','exists:users,id'],
        ]);

        // *opciono*: potvrdi da su u istoj grupi
        // $catch->group_id ...

        $existing = $catch->confirmations()->pluck('confirmed_by')->all();
        $toCreate = array_values(array_diff($data['reviewer_ids'], $existing));

        DB::transaction(function() use ($catch, $toCreate) {
            foreach ($toCreate as $uid) {
                CatchConfirmation::create([
                    'catch_id' => $catch->id,
                    'confirmed_by' => $uid,
                    'status' => 'pending',
                ]);
                // ovde pošalji Laravel Notification (database/mail) ako želiš
            }
        });

        return response()->json($catch->fresh()->load('confirmations'));
    }

    public function confirm(Request $r, $id) {
        $catch = FishingCatch::with('confirmations')->findOrFail($id);

        $data = $r->validate([
            'status' => ['required','in:approved,rejected'],
            'note'   => ['nullable','string','max:500'],
        ]);

        $conf = $catch->confirmations()->where('confirmed_by',$r->user()->id)->firstOrFail();
        $conf->update(['status'=>$data['status'],'note'=>$data['note'] ?? null]);

        // (po želji) ako svi 'approved' => označi ulov kao approved
        if ($catch->confirmations()->where('status','!=','approved')->count() === 0) {
            $catch->update(['status'=>'approved']);
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

        // (po želji) notifikacija vlasniku

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
