<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FishingCatch extends Model
{
    protected $table = 'catches';

    protected $fillable = [
        'group_id', 'user_id', 'event_id',
        'species', 'count', 'total_weight_kg', 'biggest_single_kg',
        'note', 'status',
    ];

    protected $casts = [
        'count' => 'integer',
        'total_weight_kg' => 'decimal:3',
        'biggest_single_kg' => 'decimal:3',
    ];

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

    public function confirmations(): HasMany
    {
        return $this->hasMany(CatchConfirmation::class, 'catch_id');
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
}
