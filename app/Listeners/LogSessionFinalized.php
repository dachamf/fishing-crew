<?php

namespace App\Listeners;

use App\Events\SessionFinalized;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LogSessionFinalized implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(SessionFinalized $event): void
    {
        // Ako nema tabelu, ne rušimo flow
        if (!Schema::hasTable('activities')) {
            return;
        }

        $session = $event->session;
        $type = $event->result === 'approved' ? 'session_approved' : 'session_rejected';

        DB::table('activities')->insert([
            'type'       => $type,
            'ref_id'     => $session->id,
            'user_id'    => $session->user_id,
            'meta'       => json_encode([
                'url'   => rtrim(config('app.frontend_url'), '/')."/sessions/{$session->id}",
                'title' => $session->title,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
