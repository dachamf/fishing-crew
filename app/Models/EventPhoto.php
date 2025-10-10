<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class EventPhoto extends Model
{
    protected $fillable = ['event_id','user_id','path','urls'];
    protected $casts = ['urls' => 'array'];
    protected $appends = ['url'];

    public function getUrlAttribute(): ?string {
        return $this->path ? Storage::disk('s3')->url($this->path) : null;
    }

    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function user(): BelongsTo  { return $this->belongsTo(User::class); }
}
