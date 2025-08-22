<?php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AttendeeResource extends JsonResource {
    public function toArray($request)
    {
        return [
            'id'       => $this->id,
            'name'     => $this->name,
            'email'    => $this->email,
            'avatar'   => $this->whenLoaded('profile', fn() => $this->profile->avatar_url),
            'rsvp'   => $this->pivot?->rsvp,
        ];
    }
}
