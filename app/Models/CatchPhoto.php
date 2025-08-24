<?php

// app/Models/CatchPhoto.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatchPhoto extends Model
{
    protected $fillable = ['catch_id', 'path', 'disk', 'ord'];

    public function catch(): BelongsTo
    {
        return $this->belongsTo(FishingCatch::class, 'catch_id');
    }
}
