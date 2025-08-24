<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SpeciesResource;
use App\Models\Species;
use Illuminate\Http\Request;

class SpeciesController extends Controller
{
    // GET /api/v1/species?search=smu
    public function index(Request $r) {
        $q = Species::query()->where('is_active', true);
        if ($s = trim((string)$r->search)) {
            $q->where(fn($w) => $w->where('name_sr','like',"%$s%")
                ->orWhere('name_latin','like',"%$s%")
                ->orWhere('slug','like',"%$s%"));
        }
        return SpeciesResource::collection($q->orderBy('name_sr')->limit(50)->get());
    }

}
