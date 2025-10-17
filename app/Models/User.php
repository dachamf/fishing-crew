<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $appends = ['display_name', 'avatar_path'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** Profil (avatar/display_name) */
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class, 'user_id');
    }

    /** Sve sesije korisnika */
    public function sessions(): HasMany
    {
        // tabela je `fishing_sessions`, FK je user_id
        return $this->hasMany(FishingSession::class, 'user_id');
    }

    /** Svi ulovi korisnika */
    public function catches(): HasMany
    {
        // tabela je `catches`, FK je user_id
        return $this->hasMany(FishingCatch::class, 'user_id');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    // (opciono) samo odobreni ulovi
    public function approvedCatches(): HasMany
    {
        return $this->hasMany(FishingCatch::class, 'user_id')->where('status', 'approved');
    }

    public function attendingEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_attendees', 'user_id', 'event_id')
            ->withPivot(['rsvp'])
            ->withTimestamps();
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_attendees', 'user_id', 'event_id')
            ->withPivot(['rsvp','reason','checked_in_at','rating'])
            ->withTimestamps();
    }

    public function getDisplayNameAttribute(): string
    {
        return optional($this->profile)->display_name ?? $this->name;
    }

    public function getAvatarPathAttribute(): string
    {
        return optional($this->profile)->avatar_path ?? '';
    }

}
