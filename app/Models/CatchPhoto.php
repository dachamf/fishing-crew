<?php

// app/Models/CatchPhoto.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CatchPhoto extends Model
{
    protected $table = 'catch_photos';
    protected $fillable = ['catch_id','path','disk','ord'];
    protected $appends = ['url'];

    public function catch(): BelongsTo
    {
        return $this->belongsTo(FishingCatch::class, 'catch_id');
    }

    public function getUrlAttribute(): string
    {
        return \Storage::disk($this->disk ?: 'public')->url($this->path);
    }
}
