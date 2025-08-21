<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

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

    public function groups()
    {
        return $this->belongsToMany(Group::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function catches(): HasMany
    {
        // Model se zove FishingCatch, tabela je 'catches' (definisano u modelu)
        // Foreign key je 'user_id' (default), pa ne moramo da ga navodimo eksplicitno.
        return $this->hasMany(FishingCatch::class, 'user_id');
    }

    // (opciono) samo odobreni ulovi
    public function approvedCatches(): HasMany
    {
        return $this->hasMany(FishingCatch::class, 'user_id')->where('status', 'approved');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }
}
