<?php

namespace App\Models;

use App\Enums\ConfirmationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionConfirmation extends Model
{
    protected $fillable = ['session_id', 'nominee_user_id', 'status', 'decided_at', 'token'];

    protected $casts = [
        'decided_at' => 'datetime',
        'status'     => ConfirmationStatus::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (!$m->token) {
                $plain = self::generateUniqueToken();
                $m->plain_token = $plain;
                $m->token = hash('sha256', $plain);
            }
        });
    }

    /**
     * Transient plain token (available only right after creation).
     */
    public ?string $plain_token = null;

    public function session(): BelongsTo
    {
        return $this->belongsTo(FishingSession::class, 'session_id');
    }

    public function nominee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nominee_user_id');
    }

    /** Postojeći scope – ostaje */
    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    /** Dodatni scope-ovi (korisno za assigned listu i lookup-e) */
    public function scopeForSession($q, int $sessionId)
    {
        return $q->where('session_id', $sessionId);
    }

    public function scopeForNominee($q, int $userId)
    {
        return $q->where('nominee_user_id', $userId);
    }

    public static function generateUniqueToken(): string
    {
        return bin2hex(random_bytes(32)); // 64 hex chars
    }

    /**
     * Find a confirmation by plain (unhashed) token.
     */
    public static function findByPlainToken(int $sessionId, string $plainToken): ?self
    {
        return static::where('session_id', $sessionId)
            ->where('token', hash('sha256', $plainToken))
            ->first();
    }
}
