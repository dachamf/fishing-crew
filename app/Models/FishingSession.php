<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

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

    protected $appends = ['photos'];

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

    public function reviews(): HasMany
    {
        return $this->hasMany(SessionReview::class, 'session_id');
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

    public function catchPhotos(): HasManyThrough
    {
        return $this->hasManyThrough(
            CatchPhoto::class,   // target
            FishingCatch::class, // through
            'session_id',            // FK na FishingCatch ka sessions
            'catch_id',                      // FK na CatchPhoto ka catches
            'id',                            // lokalni ključ na sessions
            'id'                             // lokalni ključ na catches
        );
    }

    public function getPhotosAttribute(): array
    {
        // napomena: ovo pravi 1 upit po sesiji; OK za liste do ~20 sesija
        return $this->catchPhotos()
            ->latest('id')     // ili ->orderBy('ord')
            ->take(3)
            ->get()
            ->map(fn($p) => ['id'=>$p->id, 'url'=>$p->url])
            ->all();
    }
}
