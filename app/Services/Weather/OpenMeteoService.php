<?php

namespace App\Services\Weather;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class OpenMeteoService
{
    public function summary(float $lat, float $lng): ?array
    {
        $latKey = round($lat, 2);
        $lngKey = round($lng, 2);
        $cacheKey = "wx:openmeteo:{$latKey},{$lngKey}";

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($lat, $lng) {
            $query = [
                'latitude'        => $lat,
                'longitude'       => $lng,
                'current_weather' => true,
                'windspeed_unit'  => 'kmh',
                'timezone'        => 'auto',
                'hourly'          => 'precipitation,wind_gusts_10m',
            ];

            $resp = Http::retry(2, 200)->timeout(6)->get('https://api.open-meteo.com/v1/forecast', $query);
            if (!$resp->ok()) return null;

            $json    = $resp->json() ?? [];
            $current = $json['current_weather'] ?? null;
            if (!$current) return null;

            $tempC     = $current['temperature']   ?? null;   // °C
            $windKph   = $current['windspeed']     ?? null;   // km/h
            $windDir   = $current['winddirection'] ?? null;   // degrees
            $wxCode    = $current['weathercode']   ?? null;   // WMO code
            $whenIso   = $current['time']          ?? null;
            $isDay     = (int) ($current['is_day'] ?? 1);     // 1 day, 0 night

            $hourly   = $json['hourly'] ?? [];
            $gustArr  = $hourly['wind_gusts_10m'] ?? ($hourly['windgusts_10m'] ?? null);
            $precArr  = $hourly['precipitation']  ?? null;

            $gustKph  = is_array($gustArr) ? $this->lastNumeric($gustArr) : null; // km/h
            $precMm   = is_array($precArr) ? $this->lastNumeric($precArr) : null; // mm

            // Mapiranje na ikonice
            $condText = $this->codeToCondition($wxCode);
            $slug     = $this->codeToIconSlug($wxCode, $isDay === 1);
            $iconUrl  = $this->slugToIconUrl($slug);
            $iconName = $this->slugToTablerIcon($slug); // fallback za FE

            return [
                'temp_c'         => $this->toNumOrNull($tempC),
                'wind_kph'       => $this->toNumOrNull($windKph),
                'wind_gust_kph'  => $this->toNumOrNull($gustKph),
                'wind_dir'       => $this->degToCardinal($this->toNumOrNull($windDir)),
                'precip_mm'      => $this->toNumOrNull($precMm),
                'condition'      => $condText,
                'icon_url'       => $iconUrl,      // npr. /images/weather/clear-day.svg (ako postoji)
                'icon_name'      => $iconName,     // npr. tabler:sun (fallback)
                'when_iso'       => $whenIso,
                'source'         => 'open-meteo',
                'is_day'         => $isDay === 1,
            ];
        });
    }

    private function toNumOrNull($v): ?float
    {
        if ($v === null) return null;
        $n = (float) $v;
        return is_finite($n) ? $n : null;
    }

    private function lastNumeric(array $arr): ?float
    {
        for ($i = count($arr) - 1; $i >= 0; $i--) {
            $v = $this->toNumOrNull($arr[$i] ?? null);
            if ($v !== null) return $v;
        }
        return null;
    }

    private function degToCardinal(?float $deg): ?string
    {
        if ($deg === null) return null;
        $dirs = ['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW'];
        $ix = (int) floor((($deg % 360) / 22.5) + 0.5) % 16;
        return $dirs[$ix] ?? null;
    }

    private function codeToCondition($code): ?string
    {
        if ($code === null) return null;
        $map = [
            0 => 'Clear',
            1 => 'Mainly clear',
            2 => 'Partly cloudy',
            3 => 'Overcast',
            45 => 'Fog',
            48 => 'Depositing rime fog',
            51 => 'Light drizzle',
            53 => 'Moderate drizzle',
            55 => 'Dense drizzle',
            56 => 'Light freezing drizzle',
            57 => 'Dense freezing drizzle',
            61 => 'Slight rain',
            63 => 'Moderate rain',
            65 => 'Heavy rain',
            66 => 'Light freezing rain',
            67 => 'Heavy freezing rain',
            71 => 'Slight snow',
            73 => 'Moderate snow',
            75 => 'Heavy snow',
            77 => 'Snow grains',
            80 => 'Rain showers: slight',
            81 => 'Rain showers: moderate',
            82 => 'Rain showers: violent',
            85 => 'Snow showers: slight',
            86 => 'Snow showers: heavy',
            95 => 'Thunderstorm',
            96 => 'Thunderstorm w/ slight hail',
            99 => 'Thunderstorm w/ heavy hail',
        ];
        return $map[(int) $code] ?? 'Unknown';
    }

    /** Slug za ikonicu u public/images/weather. Pruža day/night varijante. */
    private function codeToIconSlug($code, bool $isDay): string
    {
        $c = (int) $code;
        // day/night varijante za vedro & delimično oblačno
        if ($c === 0) return $isDay ? 'clear-day' : 'clear-night';
        if ($c === 1 || $c === 2) return $isDay ? 'partly-cloudy-day' : 'partly-cloudy-night';
        if ($c === 3) return 'cloudy';
        if ($c === 45 || $c === 48) return 'fog';

        if (in_array($c, [51,53,55,56,57], true)) return 'drizzle';
        if (in_array($c, [61,63,80,81], true))   return 'rain';
        if (in_array($c, [65,82], true))         return 'rain-heavy';

        if (in_array($c, [66,67], true))         return 'sleet';
        if (in_array($c, [71,73,77,85], true))   return 'snow';
        if (in_array($c, [75,86], true))         return 'snow-heavy';

        if ($c === 95)                            return 'thunder';
        if ($c === 96 || $c === 99)              return 'hail';

        return $isDay ? 'partly-cloudy-day' : 'partly-cloudy-night';
    }

    /** Ako fajl postoji u public/images/weather/<slug>.svg → vrati URL, inače null. */
    private function slugToIconUrl(string $slug): ?string
    {
        $rel = "images/weather/{$slug}.svg";
        $path = public_path($rel);
        return file_exists($path) ? asset($rel) : null;
    }

    /** Fallback mapiranje na Tabler ikone (nuxt-icon). */
    private function slugToTablerIcon(string $slug): ?string
    {
        $map = [
            'clear-day'           => 'tabler:sun',
            'clear-night'         => 'tabler:moon',
            'partly-cloudy-day'   => 'tabler:sun-low',
            'partly-cloudy-night' => 'tabler:moon-stars',
            'cloudy'              => 'tabler:cloud',
            'fog'                 => 'tabler:mist',
            'drizzle'             => 'tabler:cloud-rain',
            'rain'                => 'tabler:cloud-rain',
            'rain-heavy'          => 'tabler:cloud-rain',
            'sleet'               => 'tabler:cloud-snow',
            'snow'                => 'tabler:cloud-snow',
            'snow-heavy'          => 'tabler:cloud-snow',
            'thunder'             => 'tabler:cloud-storm',
            'hail'                => 'tabler:cloud-hail',
        ];
        return $map[$slug] ?? 'tabler:cloud';
    }
}
