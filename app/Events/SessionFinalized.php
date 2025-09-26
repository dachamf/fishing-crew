<?php

namespace App\Events;

use App\Models\FishingSession;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SessionFinalized
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public FishingSession $session,
        public string $result // 'approved' | 'rejected'
    ) {}
}
