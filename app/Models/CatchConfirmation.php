<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatchConfirmation extends Model
{
    protected $fillable = [
        'catch_id',
        'confirmed_by',
        'status',
        'note',
        'suggested_payload'
    ];
    protected $casts = [
        'suggested_payload' => 'array',
    ];

    public function catch(): BelongsTo
    {
        return $this->belongsTo(FishingCatch::class, 'catch_id');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
