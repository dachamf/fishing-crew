<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CatchPhoto extends Model
{
    protected $table = 'catch_photos';

    protected $fillable = [
        'catch_id',
        'path',
        'disk',
        'ord',
        'format',
        'width',
        'height',
        'taken_at',
        'gps_lat',
        'gps_lng',
        'exif',
    ];

    protected $casts = [
        'taken_at' => 'datetime',
        'exif'     => 'array',
        'gps_lat'  => 'float',
        'gps_lng'  => 'float',
        'width'    => 'int',
        'height'   => 'int',
    ];

    protected $appends = ['url', 'urls', 'exposure_display'];

    /* Relations */
    public function catch(): BelongsTo
    {
        return $this->belongsTo(FishingCatch::class, 'catch_id');
    }

    /* Helpers */
    public function getDisk(): string
    {
        return $this->disk ?: 'public';
    }

    public function originalPath(): ?string
    {
        return $this->path;
    }

    public function getUrlAttribute(): ?string
    {
        if (!$this->path) return null;
        return Storage::disk($this->getDisk())->url($this->path);
    }

    public function baseDir(): ?string
    {
        if (!$this->path) return null;
        return \dirname($this->path);
    }

    public function variantPath(string $size, bool $webp = false): ?string
    {
        $base = $this->baseDir();
        if (!$base) return null;
        $ext = $webp ? 'webp' : 'jpg';
        return "{$base}/{$size}.{$ext}";
    }

    public function variantUrl(string $size, bool $preferWebp = false): ?string
    {
        $disk = $this->getDisk();
        $pathWebp = $this->variantPath($size, true);
        $pathJpg  = $this->variantPath($size, false);

        if ($preferWebp && $pathWebp && Storage::disk($disk)->exists($pathWebp)) {
            return Storage::disk($disk)->url($pathWebp);
        }
        if ($pathJpg && Storage::disk($disk)->exists($pathJpg)) {
            return Storage::disk($disk)->url($pathJpg);
        }
        return $this->url;
    }

    public function getUrlsAttribute(): array
    {
        $out = [];
        $variants = (array) config('photos.variants', ['sm' => 320, 'md' => 800, 'lg' => 1600]);
        foreach (array_keys($variants) as $key) {
            $out[$key] = $this->variantUrl($key);
        }
        return $out;
    }

    public function getExposureDisplayAttribute(): ?string
    {
        $v = $this->attributes['exposure_time_s'] ?? null;
        if ($v === null) return null;
        $v = (float) $v;
        if ($v >= 1) return rtrim(rtrim(number_format($v, 2), '0'), '.') . 's';
        $den = max(1, (int) round(1 / $v));
        return '1/' . $den;
    }

    /* Scopes */
    public function scopeOrdered($q)
    {
        return $q->orderByRaw('taken_at IS NULL, taken_at ASC')
            ->orderByRaw('ord IS NULL, ord ASC')
            ->orderBy('id');
    }

    public function scopeForSession($q, int $sessionId)
    {
        return $q->whereHas('catch', fn($c) => $c->where('session_id', $sessionId));
    }
}
