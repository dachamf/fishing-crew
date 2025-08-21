<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AvatarUploadRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Resources\ProfileResource;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * @return ProfileResource
     */
    public function me(Request $req)
    {
        $profile = Profile::firstOrCreate(['user_id' => $req->user()->id]);

        return new ProfileResource($profile);
    }

    /**
     * @return ProfileResource
     */
    public function update(ProfileUpdateRequest $req)
    {
        $user = $req->user();
        $profile = Profile::firstOrCreate(['user_id' => $user->id]);

        $data = $req->validate([
            'display_name' => ['nullable','string','max:120'],
            'bio'          => ['nullable','string','max:1000'],
            'location'     => ['nullable','string','max:120'],
            'favorite_species' => ['nullable','string','max:120'],
            'gear'         => ['nullable','string','max:200'],
            'theme'        => ['nullable','in:light,dark'],
        ]);

        // klasična polja
        $profile->fill(collect($data)->except(['theme'])->all());

        // settings.theme merge (zadrži ostala podešavanja)
        if ($req->filled('theme')) {
            $settings = $profile->settings ?? [];
            $settings['theme'] = $req->string('theme')->toString();
            $profile->settings = $settings;
        }

        $profile->save();

        return new ProfileResource($profile->refresh());
    }

    /**
     * @return JsonResponse
     */
    public function uploadAvatar(AvatarUploadRequest $req)
    {
        $user = $req->user();
        $profile = $user->profile()->firstOrCreate([]);

        $disk = Storage::disk('s3');
        $dir  = "avatars/{$user->id}";

        // obriši stari ako postoji
        if ($profile->avatar_path) {
            $disk->delete($profile->avatar_path);
        }

        // snimi kao public (MinIO će staviti u bucket 'media/avatars/...'):
        $path = $req->file('avatar')->storePublicly($dir, 's3');

        if (!$path) {
            return response()->json(['ok'=>false,'message'=>'Upload failed'], 500);
        }

        $url = $disk->url($path); // koristi AWS_URL + path style

        $profile->update([
            'avatar_path' => $path,
        ]);

        return response()->json(['avatar_url' => $url, 'path' => $path]);
    }

    /**
     * @return Response|JsonResponse
     */
    public function deleteAvatar(Request $req)
    {
        $profile = Profile::firstOrCreate(['user_id' => $req->user()->id]);

        if ($profile->avatar_path && Storage::disk('s3')->exists($profile->avatar_path)) {
            Storage::disk('s3')->delete($profile->avatar_path);
        }

        $profile->avatar_path = null;
        $profile->save();

        return response()->noContent();
    }

    // GET /api/v1/users/{user}/profile  (javna ili polu-javna verzija)

    /**
     * @return ProfileResource|JsonResponse
     */
    public function showPublic(User $user)
    {
        $profile = $user->profile;
        if (! $profile) {
            return response()->json(['message' => 'Profile not found'], 404);
        }

        // Ako želiš “sanitizovan” pogled, možeš filtrirati polja ovde,
        // sad vraćamo isto kao i me()
        return new ProfileResource($profile);
    }
}
