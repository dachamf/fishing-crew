<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FishingSession extends Model
{
    protected $fillable = [
        'group_id',
        'user_id',
        'event_id',
        'title',
        'latitude',
        'longitude',
        'started_at',
        'ended_at',
        'status',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function catches(): HasMany
    {
        return $this->hasMany(FishingCatch::class, 'session_id');
    }

    /* Scopes */
    public function scopeOpen($q)
    {
        return $q->where('status', 'open');
    }

    public function scopeClosed($q)
    {
        return $q->where('status', 'closed');
    }

    public function scopeSeason($q, $y)
    {
        if ($y) $q->where('season_year', $y);
    }

    public function scopeBetween($q, $from, $to)
    {
        if ($from) $q->where('started_at', '>=', $from);
        if ($to) $q->where('started_at', '<=', $to);
    }
}
