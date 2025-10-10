<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class EventResource extends JsonResource
{
    protected function normalizeRsvp(?string $s): string
    {
        $s = Str::lower($s ?? '');
        return match ($s) {
            'yes', 'going'      => 'yes',
            'no', 'declined'    => 'no',
            'maybe', 'undecided'=> 'undecided',
            default             => 'undecided',
        };
    }

    public function toArray(Request $request): array
    {
        // status iz eager-loadovane relacije (preporuka), ili fallback na pivot ako ga nekad koristiš
        $rawStatus = $this->whenLoaded('myRsvp')
            ? optional($this->myRsvp)->status
            : (optional($this->pivot)->rsvp ?? null);

        return [
            'id'            => $this->id,
            'group_id'      => $this->group_id,
            'title'         => $this->title,
            'location_name' => $this->location_name,
            'latitude'      => $this->latitude? (float)$this->latitude : null,
            'longitude'     => $this->longitude? (float)$this->longitude : null,
            'start_at'      => $this->start_at?->toISOString(),
            'description'   => $this->description,
            'status'        => $this->status,
            'created_at'    => $this->created_at?->toISOString(),

            // 👇 UVEK string: "yes" | "no" | "undecided"
            'my_rsvp'       => $this->normalizeRsvp($rawStatus),
        ];
    }
}
