<?php

namespace App\Http\Resources;

use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Profile */
class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'display_name' => $this->display_name,
            'birth_year' => $this->birth_year,
            'location' => $this->location,
            'favorite_species' => $this->favorite_species,
            'gear' => $this->gear,
            'bio' => $this->bio,
            'avatar_url' => $this->avatar_url, // iz accessor-a
            'settings' => $this->settings ?? new \stdClass,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'theme' => data_get($this->settings, 'theme'),
        ];
    }
}
