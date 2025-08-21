<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Profile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'display_name',
        'birth_year',
        'location',
        'favorite_species',
        'gear',
        'bio',
        'avatar_path',
        'settings',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'birth_year' => 'integer',
            'settings' => 'array',
        ];
    }

    protected function avatarUrl(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->avatar_path) {
                return null;
            }
            try {
                return Storage::disk('s3')->temporaryUrl($this->avatar_path, now()->addMinute(60));
            } catch (\Throwable) {
                return Storage::disk('s3')->url($this->avatar_path);
            }
        });
    }
    protected $appends = ['avatar_url'];

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path ? Storage::disk('s3')->url($this->avatar_path) : null;
    }
}
