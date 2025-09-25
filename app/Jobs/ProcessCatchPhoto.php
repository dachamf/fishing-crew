<?php
namespace App\Jobs;

use App\Models\CatchPhoto;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Storage;

class ProcessCatchPhoto implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public int $photoId) {}

    public function handle(): void
    {
        $photo = CatchPhoto::find($this->photoId);
        if (!$photo || !$photo->path || !Storage::disk('public')->exists($photo->path)) return;

        $full = Storage::disk('public')->path($photo->path);

        // EXIF (ako postoji)
        $exif = @exif_read_data($full, 'EXIF', true) ?: null;
        $gpsLat = null; $gpsLng = null; $takenAt = null;
        if ($exif) {
            $takenAt = $exif['EXIF']['DateTimeOriginal'] ?? null;
            // ekstrakcija GPS (ako postoji) – možeš dopuniti kasnije preciznije
        }

        // Thumbs (Intervention v3 preporučeno) – ovde pseudo; zameni stvarnom obradom
        $thumbRel  = preg_replace('~(\.\w+)$~', '_thumb$1', $photo->path);
        $mediumRel = preg_replace('~(\.\w+)$~', '_medium$1', $photo->path);
        $webpRel   = preg_replace('~(\.\w+)$~', '.webp', $photo->path);

        // TODO: generiši fajlove → Storage::disk('public')->put($thumbRel, $bytes) ...

        $photo->update([
            'exif_json'  => $exif ? json_encode($exif) : null,
            'taken_at'   => $takenAt,
            'gps_lat'    => $gpsLat,
            'gps_lng'    => $gpsLng,
            'thumb_url'  => Storage::disk('public')->url($thumbRel),
            'medium_url' => Storage::disk('public')->url($mediumRel),
            'webp_url'   => Storage::disk('public')->url($webpRel),
        ]);
    }
}
