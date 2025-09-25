<?php

namespace App\Http\Controllers;

use App\Models\CatchPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class PhotoController extends Controller
{
    /**
     * GET /api/photos/{photo}?size=sm|md|lg
     * Content-negotiation: ako klijent podržava webp i imamo webp varijantu, servira se webp.
     * Cache: dugi max-age + immutable.
     */
    public function show(Request $req, CatchPhoto $photo)
    {
        $size = $req->query('size'); // null|sm|md|lg
        $acceptWebp = str_contains($req->header('Accept', ''), 'image/webp');
        $disk = $photo->getDisk();

        // Kandidat path
        $path = $photo->path; // original
        if ($size && array_key_exists($size, config('photos.variants', []))) {
            $candidate = $photo->variantPath($size, $acceptWebp && config('photos.make_webp', true));
            if ($candidate && Storage::disk($disk)->exists($candidate)) {
                $path = $candidate;
            } else {
                // fallback: probaj jpg
                $candidate = $photo->variantPath($size, false);
                if ($candidate && Storage::disk($disk)->exists($candidate)) {
                    $path = $candidate;
                }
            }
        }

        if (!$path || !Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        $absolute = Storage::disk($disk)->path($path);

        // ETag za keš-busting (na osnovu mtime + veličine)
        $mtime = @filemtime($absolute) ?: time();
        $sizeB = @filesize($absolute) ?: 0;
        $etag  = md5($path . ':' . $mtime . ':' . $sizeB);

        if ($req->headers->get('If-None-Match') === $etag) {
            return Response::make('', 304, [
                'ETag'          => $etag,
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ]);
        }

        return response()->file($absolute, [
            'ETag'          => $etag,
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
