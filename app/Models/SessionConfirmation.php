<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionConfirmation extends Model
{
    protected $fillable = ['session_id', 'nominee_user_id', 'status', 'decided_at', 'token'];
    protected $casts = ['decided_at' => 'datetime'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(FishingSession::class, 'session_id');
    }

    public function nominee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nominee_user_id');
    }

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }
}
