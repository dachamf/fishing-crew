<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id', 'title', 'location_name', 'latitude', 'longitude',
        'start_at', 'description', 'status',
    ];

    protected $casts = [
        'start_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function users(): BelongsToMany
    {
        // event_attendees pivot: rsvp, reason, checked_in_at, rating
        return $this->belongsToMany(User::class, 'event_attendees')
            ->withPivot(['rsvp', 'reason', 'checked_in_at', 'rating'])
            ->withTimestamps();
    }
}
