<?php

namespace App\Services;

use App\Models\CatchPhoto;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver; // ili Imagick driver
use PHPExif\Reader\Reader;

class PhotoProcessor
{
    public function __construct(
        private ?ImageManager $images = null
    ) {
        $this->images ??= new ImageManager(new Driver());
    }

    /** Izvlači EXIF (raw array). */
    public function extractExif(string $absolutePath): array
    {
        try {
            $reader = Reader::factory(Reader::TYPE_NATIVE);
            $data = $reader->read($absolutePath);
            return $data?->getData() ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Popunjava kolone na CatchPhoto prema našem hibrid modelu. */
    public function mapAndPersistExif(CatchPhoto $photo, array $exif, array $imageInfo = []): void
    {
        $gps = $exif['gps'] ?? [];

        // Privatnost: opciono skidanje GPS-a pre snimanja
        if (config('photos.strip_gps', true)) {
            unset($exif['gps'], $exif['GPS'], $exif['geo']);
        }

        $fill = [
            'taken_at' => $exif['created'] ?? $exif['dateTimeOriginal'] ?? null,
            'format'   => $imageInfo['format'] ?? ($exif['MimeType'] ?? null), // npr. "jpg" ili "image/jpeg"
            'width'    => $imageInfo['width']  ?? null,
            'height'   => $imageInfo['height'] ?? null,
            'gps_lat'  => config('photos.strip_gps', true) ? null : $this->gpsToDecimal($gps['latitude'] ?? null),
            'gps_lng'  => config('photos.strip_gps', true) ? null : $this->gpsToDecimal($gps['longitude'] ?? null),
            'exif'     => $exif,
        ];

        // Ako format dođe kao MIME, prevedi u ekstenziju
        if (!empty($fill['format']) && str_contains((string)$fill['format'], '/')) {
            $fill['format'] = $this->mimeToExt((string)$fill['format']);
        }

        $photo->fill($fill)->save();
    }

    /** Generiše sm/md/lg (+webp) varijante i auto-orijentiše sliku. */
    public function generateVariants(CatchPhoto $photo): void
    {
        $origRel = $photo->originalPath(); // vidi metodu u CatchPhoto modelu ako si je dodao; ako ne, koristimo $photo->path
        $disk = $photo->getDisk();

        // Fallback: ako nema helper, uzmi path direktno
        $relPath = method_exists($photo, 'originalPath') ? $origRel : $photo->path;
        if (!$relPath || !Storage::disk($disk)->exists($relPath)) {
            return;
        }

        $abs = Storage::disk($disk)->path($relPath);
        $img = $this->images->read($abs)->orientate();

        // Izmeri info (popuni width/height/format)
        $info = [
            'width'  => $img->width(),
            'height' => $img->height(),
            'format' => pathinfo($relPath, PATHINFO_EXTENSION) ?: 'jpg',
        ];

        // Spasi EXIF + tehničke
        $exif = $this->extractExif($abs);
        $this->mapAndPersistExif($photo, $exif, $info);

        // Varijante
        $variants = (array) config('photos.variants', ['sm' => 320, 'md' => 800, 'lg' => 1600]);
        $makeWebp = (bool) config('photos.make_webp', true);

        foreach ($variants as $key => $maxWidth) {
            $clone = clone $img;
            $clone->scaleDown((int) $maxWidth);

            // jpg
            $jpgRel = $photo->variantPath($key, false) ?? $this->variantPathFromOriginal($relPath, $key, 'jpg');
            Storage::disk($disk)->put($jpgRel, (string) $clone->toJpeg(quality: 78));

            // webp
            if ($makeWebp) {
                $webpRel = $photo->variantPath($key, true) ?? $this->variantPathFromOriginal($relPath, $key, 'webp');
                Storage::disk($disk)->put($webpRel, (string) $clone->toWebp(quality: 76));
            }
        }
    }

    /** ─────────────── Helpers ─────────────── */

    private function gpsToDecimal($v): ?float
    {
        if (!$v) return null;
        if (is_array($v)) {
            [$d,$m,$s] = ($v + [0,0,0]);
        } else {
            $parts = array_map('trim', explode(',', (string)$v));
            [$d,$m,$s] = array_map(fn($p)=>$this->ratioToFloat($p), $parts + [0,0,0]);
        }
        $d = (float) $d; $m = (float) $m; $s = (float) $s;
        return $d + ($m/60.0) + ($s/3600.0);
    }

    private function ratioToFloat($v): ?float
    {
        if ($v === null) return null;
        if (is_numeric($v)) return (float) $v;
        if (is_string($v) && str_contains($v, '/')) {
            [$n,$d] = array_map('floatval', explode('/', $v, 2) + [1,1]);
            return $d ? $n / $d : null;
        }
        return (float) preg_replace('/[^\d\.]/', '', (string)$v);
    }

    private function mimeToExt(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/heic', 'image/heif' => 'heic',
            default => 'jpg',
        };
    }

    /** Ako nemaš helper u modelu, generiši putanju varijante iz originalnog path-a. */
    private function variantPathFromOriginal(string $origRel, string $size, string $ext): string
    {
        $dir = \dirname($origRel);
        return "{$dir}/{$size}.{$ext}";
    }
}
