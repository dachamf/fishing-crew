<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Services\Weather\OpenMeteoService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class WeatherController extends Controller
{
    /**
     * GET /v1/weather/summary?lat=..&lng=..
     * Trenutno: stub (501) dok ne uvežemo providera (npr. Open-Meteo).
     */
    public function summary(Request $r, OpenMeteoService $svc): Response|JsonResponse
    {
        $data = $r->validate([
            'lat' => ['required','numeric','between:-90,90'],
            'lng' => ['required','numeric','between:-180,180'],
        ]);

        $summary = $svc->summary((float) $data['lat'], (float) $data['lng']);

        // Graciozni fallback: nema podataka → 204 No Content
        if ($summary === null) {
            return response()->noContent();
        }

        return response()->json($summary);
    }
}
