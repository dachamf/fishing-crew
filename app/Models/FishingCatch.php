<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FishingCatch extends Model
{
    protected $table = 'catches';

    protected $fillable = [
        'group_id', 'user_id', 'event_id', 'session_id',
        'species_id', 'count', 'total_weight_kg', 'biggest_single_kg',
        'note', 'status', 'caught_at', 'season_year',
    ];

    protected $casts = [
        'count' => 'integer',
        'total_weight_kg' => 'decimal:3',
        'biggest_single_kg' => 'decimal:3',
        'caught_at' => 'datetime',
        'season_year' => 'integer',
    ];

    public function photos(): HasMany
    {
        return $this->hasMany(CatchPhoto::class, 'catch_id')->orderBy('ord');
    }

    public function confirmations(): HasMany
    {
        return $this->hasMany(CatchConfirmation::class, 'catch_id');
    }

    // Relationships
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(FishingSession::class, 'session_id');
    }

    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }

    // Scopes
    public function scopeApproved($q)
    {
        return $q->where('status', 'approved');
    }

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    public function scopeSeason($q, $y)
    {
        if ($y) $q->where('season_year', $y);
    }

    public function scopeBetween($q, $from, $to)
    {
        if ($from) $q->where('caught_at', '>=', $from);
        if ($to) $q->where('caught_at', '<=', $to);
    }

    protected static function booted(): void
    {
        static::creating(function (self $c) {
            // auto season iz datuma ili iz sesije
            if (!$c->season_year) {
                $when = $c->caught_at ?? now();
                $c->season_year = (int)$when->format('Y');
            }
            if (!$c->group_id && $c->session_id) {
                $c->group_id = optional($c->session)->group_id;
            }
        });
    }
}
