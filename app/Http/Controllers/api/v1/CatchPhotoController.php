<?php
namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
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
            'photos.*' => ['file','image','mimes:jpg,jpeg,png,webp','max:5120'],
        ]);

        if (($catch->photos_count + count($r->photos)) > 3) {
            return response()->json(['message'=>'Maksimum 3 slike po ulovu.'], 422);
        }

        $saved = [];
        foreach ($r->file('photos') as $i => $file) {
            $path = $file->store('catches', 'public');
            $saved[] = CatchPhoto::create([
                'catch_id' => $catch->id,
                'path'     => $path,
                'disk'     => 'public',
                'ord'      => ($catch->photos()->max('ord') ?? 0) + $i + 1,
            ]);
        }

        return response()->json(collect($saved)->loadMissing('catch'), 201);
    }

    public function destroy(Request $r, $id, $photoId) {
        $catch = FishingCatch::findOrFail($id);
        $this->authorize('update', $catch);

        $photo = CatchPhoto::where('catch_id',$id)->findOrFail($photoId);
        Storage::disk($photo->disk)->delete($photo->path);
        $photo->delete();

        return response()->json(['message'=>'Photo deleted']);
    }
}
