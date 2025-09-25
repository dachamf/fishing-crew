<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int         $id
 * @property int         $catch_id
 * @property string|null $path        // npr. photos/{session_id}/{photo_id}/orig.jpg
 * @property string|null $disk        // npr. "public"
 * @property int|null    $ord
 * @property string|null $format      // "jpg", "png", "heic"...
 * @property int|null    $width
 * @property int|null    $height
 * @property \Carbon\Carbon|null $taken_at
 * @property float|null  $gps_lat
 * @property float|null  $gps_lng
 * @property array|null  $exif        // raw exif json (ako si preimenovao exif_json -> exif, cast radi)
 *
 * @property-read string|null $url
 * @property-read array       $urls
 * @property-read string|null $exposure_display
 */
class CatchPhoto extends Model
{
    protected $table = 'catch_photos';

    // Dodaj ovde polja koja puniš iz upload/exif job-a
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
        'exif',        // ako polje u bazi još uvek glasi exif_json, cast radi samo kad preimenuješ kolonu
    ];

    protected $casts = [
        'taken_at' => 'datetime',
        'exif'     => 'array',
        'gps_lat'  => 'float',
        'gps_lng'  => 'float',
        'width'    => 'int',
        'height'   => 'int',
    ];

    // Automatski vraćamo i original URL i bundlovane varijante
    protected $appends = ['url', 'urls', 'exposure_display'];

    /** ───────────────────────────────── Relations ───────────────────────────────── */

    public function catch(): BelongsTo
    {
        return $this->belongsTo(FishingCatch::class, 'catch_id');
    }

    /** ─────────────────────────────── Accessors/Helpers ─────────────────────────── */

    public function getDisk(): string
    {
        return $this->disk ?: 'public';
    }

    /**
     * Public URL za original (baziran na path-u koji već čuvaš).
     */
    public function getUrlAttribute(): ?string
    {
        if (!$this->path) return null;
        return Storage::disk($this->getDisk())->url($this->path);
    }

    /**
     * “Bundle” varijanti, zgodno za API response.
     * Ključevi se čitaju iz config('photos.variants') — npr. sm/md/lg.
     */
    public function getUrlsAttribute(): array
    {
        $out = [];
        $variants = (array) config('photos.variants', ['sm' => 320, 'md' => 800, 'lg' => 1600]);
        foreach (array_keys($variants) as $key) {
            $out[$key] = $this->variantUrl($key);
        }
        return $out;
    }

    /**
     * Ljudski čitljiv prikaz ekspozicije, npr. "1/125".
     * Puni se iz kolone exposure_time_s (ako je dodaš).
     */
    public function getExposureDisplayAttribute(): ?string
    {
        // ako dodaš kolonu exposure_time_s i cast, ovo će raditi; u suprotnom ostaje null
        $v = $this->attributes['exposure_time_s'] ?? null;
        if ($v === null) return null;
        $v = (float) $v;
        if ($v >= 1) {
            $txt = rtrim(rtrim(number_format($v, 2), '0'), '.');
            return $txt . 's';
        }
        $den = max(1, (int) round(1 / $v));
        return '1/' . $den;
    }

    /**
     * Bazni direktorijum varijanti na osnovu originalnog path-a.
     * Ako ti je path = photos/{session}/{photo}/orig.jpg → baseDir = photos/{session}/{photo}
     */
    public function baseDir(): ?string
    {
        if (!$this->path) return null;
        return \dirname($this->path);
    }

    /**
     * Putanja do varijante na disku (relative path na FS disk-u).
     */
    public function variantPath(string $size, bool $webp = false): ?string
    {
        $base = $this->baseDir();
        if (!$base) return null;
        $ext = $webp ? 'webp' : 'jpg';
        return "{$base}/{$size}.{$ext}";
    }

    /**
     * Public URL do varijante. Ako varijanta ne postoji, vraća URL originala (fallback).
     */
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
        // Fallback na original
        return $this->url;
    }

    /** ─────────────────────────────────── Scopes ────────────────────────────────── */

    public function scopeOrdered($q)
    {
        // Primarni sort po taken_at, sekundarni po ord, pa id.
        return $q->orderByRaw('taken_at IS NULL, taken_at ASC')
            ->orderByRaw('ord IS NULL, ord ASC')
            ->orderBy('id');
    }

    public function scopeForSession($q, int $sessionId)
    {
        // preko relacije catch -> session_id (ako postoji kolona u catches)
        return $q->whereHas('catch', fn($c) => $c->where('session_id', $sessionId));
    }
}
