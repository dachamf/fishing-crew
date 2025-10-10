<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EventPhotoController extends Controller
{
    // GET /v1/events/{event}/photos
    public function index(Request $r, Event $event) {
        $items = $event->photos()
            ->with(['user:id,name','user.profile:id,user_id,display_name,avatar_path'])
            ->latest('id')->get();

        return response()->json($items->map(fn($p)=>[
            'id' => (int)$p->id,
            'url' => $p->url,
            'created_at' => $p->created_at,
            'user' => [
                'id' => (int)$p->user?->id,
                'name' => $p->user?->name,
                'display_name' => $p->user?->profile?->display_name ?? $p->user?->name,
                'avatar_url' => $p->user?->profile?->avatar_path
                    ? Storage::disk('s3')->url($p->user->profile->avatar_path)
                    : null,
            ],
        ]));
    }

    // POST /v1/events/{event}/photos   (multipart: photo ili photos[])
    public function store(Request $r, Event $event) {
        $this->authorize('rsvp', $event); // isti gate kao i za učešće

        // Upload je DOZVOLJEN čim event počne (tokom i posle).
        // Zaključaj upload samo PRE početka:
        if (optional($event->start_at)->isFuture()) {
            return response()->json(['message' => 'Upload fotografija biće omogućen kad događaj počne.'], 422);
        }

        $r->validate([
            'photo'     => ['nullable','image','max:5120'], // 5MB
            'photos'    => ['nullable','array'],
            'photos.*'  => ['image','max:5120'],
        ]);

        $userId = $r->user()->id;

        // limit 5 po učesniku
        $already  = EventPhoto::where('event_id',$event->id)->where('user_id',$userId)->count();
        $incoming = ($r->hasFile('photo') ? 1 : 0) + ($r->hasFile('photos') ? count($r->file('photos')) : 0);
        if ($already + $incoming > 5) {
            return response()->json(['message' => 'Maksimalno 5 fotografija po učesniku.'], 422);
        }

        $files = [];
        if ($r->hasFile('photo'))  $files[] = $r->file('photo');
        if ($r->hasFile('photos')) $files = array_merge($files, $r->file('photos'));

        $created = [];
        foreach ($files as $file) {
            $path = $file->storePublicly("events/{$event->id}/{$userId}", ['disk'=>'s3','visibility'=>'public']);
            $created[] = EventPhoto::create([
                'event_id' => $event->id,
                'user_id'  => $userId,
                'path'     => $path,
                'urls'     => null,
            ])->fresh();
        }

        return response()->json([
            'items' => collect($created)->map(fn($p)=>[
                'id' => (int)$p->id,
                'url' => $p->url,
                'created_at' => $p->created_at,
            ])->values(),
        ], 201);
    }

    // DELETE /v1/events/{event}/photos/{photo}
    public function destroy(Request $r, Event $event, EventPhoto $photo) {
        $this->authorize('delete', $photo);
        if ($photo->event_id !== $event->id) {
            return response()->json(['message'=>'Nije deo ovog događaja.'], 404);
        }
        if ($photo->path) Storage::disk('s3')->delete($photo->path);
        $photo->delete();
        return response()->json(['ok'=>true]);
    }
}
