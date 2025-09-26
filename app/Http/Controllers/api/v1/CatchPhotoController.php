<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessCatchPhoto;
use App\Models\CatchPhoto;
use App\Models\FishingCatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CatchPhotoController extends Controller
{
    public function store(Request $r, $id) {
        $catch = FishingCatch::withCount('photos')->findOrFail($id);
        $this->authorize('update', $catch);

        $r->validate([
            'photos'   => ['required','array','min:1','max:3'],
            'photos.*' => ['file','image','mimes:jpg,jpeg,png,webp,heic,heif','max:6144'], // 6MB
        ]);

        if (($catch->photos_count + count($r->photos)) > 3) {
            return response()->json(['message'=>'Maksimum 3 slike po ulovu.'], 422);
        }

        $saved = [];
        $baseOrd = (int) ($catch->photos()->max('ord') ?? 0);

        foreach ($r->file('photos') as $i => $file) {
            // struktura: catches/{catch_id}/{photo_id}/orig.jpg
            $tmp = $file->store('catches/_incoming', 'public'); // privremeno
            $photo = CatchPhoto::create([
                'catch_id' => $catch->id,
                'path'     => null,          // popuni ispod
                'disk'     => 'public',
                'ord'      => $baseOrd + $i + 1,
            ]);

            $ext  = strtolower($file->getClientOriginalExtension() ?: 'jpg');
            $dest = "catches/{$catch->id}/{$photo->id}/orig.{$ext}";

            // premesti u finalni path (da kasnije job ima stabilnu putanju)
            Storage::disk('public')->move($tmp, $dest);
            $photo->update(['path' => $dest]);

            // pozadinska obrada
            ProcessCatchPhoto::dispatch($photo->id)->onQueue('images');

            $saved[] = $photo->fresh(); // vrati snimljeno (sa url/urls accessorima)
        }

        // FE odmah dobija original URL-eve; varijante stižu kad job završi
        return response()->json(collect($saved)->map(function (CatchPhoto $p) {
            return [
                'id'    => $p->id,
                'url'   => $p->url,
                'urls'  => $p->urls,   // sm/md/lg (ako postoje) inače fallback na original
                'ord'   => $p->ord,
                'width' => $p->width,
                'height'=> $p->height,
            ];
        })->values(), 201);
    }

    public function destroy(Request $r, $id, $photoId) {
        $catch = FishingCatch::findOrFail($id);
        $this->authorize('update', $catch);

        $photo = CatchPhoto::where('catch_id',$id)->findOrFail($photoId);

        // obriši varijante + original
        $disk = $photo->disk ?: 'public';
        $paths = [$photo->path];
        foreach (array_keys((array) config('photos.variants', [])) as $k) {
            $paths[] = $photo->variantPath($k, false);
            $paths[] = $photo->variantPath($k, true);
        }
        foreach (array_filter($paths) as $p) {
            Storage::disk($disk)->delete($p);
        }

        $photo->delete();

        return response()->json(['message'=>'Photo deleted']);
    }
}
