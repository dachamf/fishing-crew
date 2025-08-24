<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SpeciesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name_sr' => $this->name_sr,
            'name_latin' => $this->name_latin,
            'slug' => $this->slug,
        ];
    }
}
