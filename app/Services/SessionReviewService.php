<?php

namespace App\Services;

use App\Mail\SessionFinalizedMail;
use App\Mail\SessionReviewActionMail;
use App\Mail\SessionReviewRequestMail;
use App\Models\FishingSession;
use App\Models\SessionConfirmation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SessionReviewService
{
    public function nominate(FishingSession $session, array $nomineeUserIds, ?callable $reviewUrlBuilder = null): void
    {
        foreach ($nomineeUserIds as $uid) {
            $conf = SessionConfirmation::firstOrCreate([
                'session_id' => $session->id,
                'nominee_user_id' => $uid,
            ], [
                'status' => 'pending',
                'token' => Str::random(48),
            ]);

            if ($conf->wasRecentlyCreated) {
                $url = $reviewUrlBuilder ? $reviewUrlBuilder($session, $conf) : url("/sessions/{$session->id}/review?token={$conf->token}");
                Mail::to($conf->nominee)->queue(new SessionReviewRequestMail($session, $conf, $url));
            }
        }
    }

    public function confirm(
        FishingSession $session,
        SessionConfirmation $conf,
        string $decision,
        $actor, bool
    $silent = false
    ): void
    {
        if ($conf->status !== 'pending') return;

        $conf->update([
            'status' => $decision, // 'approved' | 'rejected'
            'decided_at' => now(),
        ]);
        $url = url("/sessions/{$session->id}/");
        if (!$silent) {
            Mail::to($session->user)->queue(new SessionReviewActionMail(
                $session,
                $actor,
                $decision,
                $url,
            ));
        }

        $this->maybeFinalize($session);
    }

    public function maybeFinalize(FishingSession $session): void
    {
        $session->load('confirmations');

        if ($session->confirmations()->count() === 0) return; // ništa za finalizaciju

        $pending = $session->confirmations->where('status', 'pending')->count();
        if ($pending > 0) return;

        $final = $session->confirmations->contains(fn($c) => $c->status === 'rejected') ? 'rejected' : 'approved';

        DB::transaction(function () use ($session, $final) {
            // bulk update ulova
            $session->catches()->update(['status' => $final]);

            // finalizuj sesiju
            $session->forceFill([
                'finalized_at' => now(),
                'final_result' => $final,
            ])->save();
        });
        $url = url("/sessions/{$session->id}/");
        Mail::to($session->user)->queue(new SessionFinalizedMail($session, $url));
    }
}
