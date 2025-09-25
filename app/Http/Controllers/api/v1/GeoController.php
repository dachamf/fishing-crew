<?php
namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GeoController extends Controller
{
    public function reverse(Request $r) {
        $data = $r->validate([
            'lat' => ['required','numeric','between:-90,90'],
            'lng' => ['required','numeric','between:-180,180'],
            'lang'=> ['nullable','string','max:5'],
        ]);
        $lang = $data['lang'] ?? 'sr';

        $resp = Http::withHeaders([
            'User-Agent' => 'FishingCrew/1.0 (+ddev)',
        ])->timeout(6)->get('https://nominatim.openstreetmap.org/reverse', [
            'format' => 'jsonv2',
            'lat'    => $data['lat'],
            'lon'    => $data['lng'],
            'zoom'   => 12,
            'addressdetails' => 0,
            'accept-language' => $lang,
        ]);

        if (!$resp->ok()) {
            return response()->json(['display_name' => null], 200);
        }

        $json = $resp->json();
        $name = $json['display_name'] ?? null;

        return response()->json(['display_name' => $name], 200);
    }
}
