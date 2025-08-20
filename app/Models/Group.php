<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'season_year'];

    // Relacije
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role') // owner, moderator, member
            ->withTimestamps();
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    // Helpers
    public function isMember(int $userId): bool
    {
        return $this->users()->where('users.id', $userId)->exists();
    }

    public function roleOf(int $userId): ?string
    {
        $row = $this->users()->where('users.id', $userId)->first();

        return $row?->pivot?->role;
    }

    public function isOwner(int $userId): bool
    {
        return $this->users()
            ->where('users.id', $userId)
            ->wherePivot('role', 'owner')
            ->exists();
    }

    public function isModeratorOrOwner(int $userId): bool
    {
        return $this->users()
            ->where('users.id', $userId)
            ->whereIn('group_user.role', ['owner', 'moderator'])
            ->exists();
    }
}
