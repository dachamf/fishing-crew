<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
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
        ];
    }
}
