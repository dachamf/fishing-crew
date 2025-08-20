<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AvatarUploadRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Resources\ProfileResource;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{

    /**
     * @param Request $req
     * @return ProfileResource
     */
    public function me(Request $req)
    {
        $profile = Profile::firstOrCreate(['user_id' => $req->user()->id]);
        return new ProfileResource($profile);
    }

    /**
     * @param ProfileUpdateRequest $req
     * @return ProfileResource
     */
    public function update(ProfileUpdateRequest $req)
    {
        $user = $req->user();
        $profile = Profile::firstOrCreate(['user_id' => $user->id]);
        $profile->fill($req->validated())->save();

        return new ProfileResource($profile->fresh());
    }


    /**
     * @param AvatarUploadRequest $req
     * @return JsonResponse
     */
    public function uploadAvatar(AvatarUploadRequest $req)
    {
        $user = $req->user();
        $profile = Profile::firstOrCreate(['user_id' => $user->id]);

        // obriši stari avatar ako postoji
        if ($profile->avatar_path && Storage::disk('s3')->exists($profile->avatar_path)) {
            Storage::disk('s3')->delete($profile->avatar_path);
        }

        $file = $req->file('avatar');
        $path = $file->store("avatars/{$user->id}", ['disk' => 's3']); // čuva originalno ime-hash

        $profile->avatar_path = $path;
        $profile->save();

        return response()->json([
            'avatar_url' => $profile->avatar_url,
            'path' => $path,
        ], 201);
    }

    /**
     * @param Request $req
     * @return Response
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
     * @param User $user
     * @return ProfileResource|JsonResponse
     */
    public function showPublic(User $user)
    {
        $profile = $user->profile;
        if (!$profile) {
            return response()->json(['message' => 'Profile not found'], 404);
        }

        // Ako želiš “sanitizovan” pogled, možeš filtrirati polja ovde,
        // sad vraćamo isto kao i me()
        return new ProfileResource($profile);
    }
}
