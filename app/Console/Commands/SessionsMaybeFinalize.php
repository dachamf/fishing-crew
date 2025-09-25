<?php

namespace App\Console\Commands;

use App\Models\FishingSession;
use App\Services\SessionReviewService;
use Illuminate\Console\Command;

class SessionsMaybeFinalize extends Command
{
    protected $signature = 'sessions:maybe-finalize {--limit=500}';
    protected $description = 'Finalize closed sessions where all confirmations are decided.';

    public function handle(SessionReviewService $svc): int
    {
        $limit = (int) $this->option('limit');
        $count = 0;

        FishingSession::query()
            ->where('status', 'closed')
            ->whereHas('confirmations')
            ->whereDoesntHave('confirmations', fn($qq) => $qq->where('status', 'pending'))
            ->when(\Schema::hasColumn('fishing_sessions', 'finalized_at'), fn($qq) => $qq->whereNull('finalized_at'))
            ->orderBy('id')
            ->chunkById(100, function ($chunk) use ($svc, &$count, $limit) {
                foreach ($chunk as $session) {
                    if ($count >= $limit) return false;
                    try {
                        $svc->maybeFinalize($session);
                        $count++;
                    } catch (\Throwable $e) {
                        \Log::warning('sessions:maybe-finalize failed', [
                            'session_id' => $session->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        $this->info("Processed: {$count}");
        return self::SUCCESS;
    }
}
