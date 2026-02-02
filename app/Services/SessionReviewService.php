<?php

namespace App\Services;

use App\Events\SessionFinalized;
use App\Models\FishingSession;
use App\Models\SessionConfirmation;
use App\Notifications\OwnerSessionConfirmationUpdated;
use App\Notifications\OwnerSessionFinalized;
use App\Notifications\SessionConfirmationsRequested; // postoji u tvom repo-u
use App\Notifications\SessionReviewTokenLink;        // NOVO (vidi ispod)
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SessionReviewService
{
    /**
     * @param  FishingSession $session
     * @param  array<int> $nomineeUserIds
     * @param  callable(FishingSession, SessionConfirmation):string|null $reviewUrlBuilder
     */
    public function nominate(FishingSession $session, array $nomineeUserIds, ?callable $reviewUrlBuilder = null): void
    {
        foreach ($nomineeUserIds as $uid) {
            $plainToken = bin2hex(random_bytes(32));
            $conf = SessionConfirmation::firstOrCreate(
                ['session_id' => $session->id, 'nominee_user_id' => $uid],
                ['status' => 'pending', 'token' => hash('sha256', $plainToken)],
            );

            if (!$conf->wasRecentlyCreated) {
                continue;
            }

            // Token URL ka FE (approve/reject bez login-a) - koristi plain token
            $conf->plain_token = $plainToken;
            $url = $reviewUrlBuilder
                ? $reviewUrlBuilder($session, $conf)
                : rtrim(config('app.frontend_url'), '/')."/sessions/{$session->id}?token={$plainToken}";

            // Light lista ulova (za email preview)
            $items = $session->catches()
                ->get(['id','species','species_name','count','total_weight_kg','caught_at'])
                ->map(fn($c) => [
                    'id'               => $c->id,
                    'species'          => $c->species_label ?? $c->species ?? $c->species_name ?? '-',
                    'count'            => $c->count,
                    'total_weight_kg'  => $c->total_weight_kg,
                    'caught_at'        => $c->caught_at,
                ])->all();

            // Pošalji postojeću notifikaciju sa listom...
            $conf->nominee?->notify(new SessionConfirmationsRequested($session, $items));
            // ... + poseban token link (da bude sigurno u mailu)
            $conf->nominee?->notify(new SessionReviewTokenLink($session, $url));
        }
    }

    /**
     * @param FishingSession $session
     * @param SessionConfirmation $conf
     * @param string $decision
     * @param $actor
     * @param bool $silent
     * @return void
     */
    public function confirm(
        FishingSession $session,
        SessionConfirmation $conf,
        string $decision,
        $actor,
        bool $silent = false
    ): void
    {
        if ($conf->status !== 'pending') {
            return;
        }

        $conf->update([
            'status'     => $decision, // 'approved' | 'rejected'
            'decided_at' => now(),
        ]);

        if (!$silent && $session->user) {
            $url = rtrim(config('app.frontend_url'), '/')."/sessions/{$session->id}";
            $session->user->notify(new OwnerSessionConfirmationUpdated(
                $session,
                $actor,
                $decision,
                $url
            ));
        }

        $this->maybeFinalize($session);
    }

    /**
     * Finalizes the given fishing session based on its confirmations.
     *
     * This method evaluates the confirmations of the fishing session. If there are no confirmations,
     * no finalization is performed. If there are pending confirmations, the method waits for them to settle.
     *
     * Once all confirmations are finalized, the method determines the final result as either 'approved'
     * or 'rejected' based on the status of the confirmations. The status of associated catches is updated,
     * and the session is finalized with the determined result. Finally, a notification is sent to the session's
     * user if available.
     *
     * @param FishingSession $session The fishing session to potentially finalize.
     *
     * @return void
     */
    public function maybeFinalize(FishingSession $session): void
    {
        $session->load('confirmations');

        // ništa za finalizaciju
        if ($session->confirmations()->count() === 0) return;

        // čekamo dok ne nestane pending
        if ($session->confirmations->where('status', 'pending')->count() > 0) return;

        $final = $session->confirmations->contains(fn($c) => $c->status === 'rejected')
            ? 'rejected'
            : 'approved';

        DB::transaction(function () use ($session, $final) {
            // bulk update ulova
            $session->catches()->update(['status' => $final]);

            // finalizuj sesiju
            $session->forceFill([
                'finalized_at' => now(),
                'final_result' => $final,
            ])->save();
        });

        try {
            event(new SessionFinalized($session, $final)); // NEW event
        } catch (\Throwable $e) {
            // no-op (da ne blokira flow)
        }

        if ($session->user) {
            $url = rtrim(config('app.frontend_url'), '/')."/sessions/{$session->id}";
            $session->user->notify(new OwnerSessionFinalized($session, $final));
        }
    }
}
