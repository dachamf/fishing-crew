<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\FishingCatch;
use App\Models\FishingSession;

class AchievementsController extends Controller
{
    // GET /v1/achievements?scope=me
    public function index(Request $r)
    {
        $userId = (string)$r->query('scope') === 'me' ? $r->user()->id : null;

        $catchTable   = (new FishingCatch)->getTable();
        $sessionTable = (new FishingSession)->getTable();

        // PB (najveći pojedinačni ulov po težini)
        $pb = DB::table("$catchTable as c")
            ->join("$sessionTable as s", 's.id', '=', 'c.session_id')
            ->when($userId, fn($q) => $q->where('s.user_id', $userId))
            ->selectRaw('MAX(COALESCE(c.total_weight_kg,0)) as max_kg')
            ->value('max_kg') ?? 0;

        // Streak (broj uzastopnih dana sa sesijom) — MVP: bilo 2+ dana zaredom
        $dates = DB::table("$sessionTable as s")
            ->when($userId, fn($q) => $q->where('s.user_id', $userId))
            ->orderBy('started_at')
            ->pluck('started_at')
            ->map(fn($d) => \Illuminate\Support\Carbon::parse($d)->toDateString())
            ->unique()
            ->values();

        $streak2 = false;
        for ($i=1; $i<$dates->count(); $i++) {
            $prev = Carbon::parse($dates[$i-1]);
            $curr = Carbon::parse($dates[$i]);
            if ($prev->diffInDays($curr) === 1) { $streak2 = true; break; }
        }

        // Night owl (noćni lov: sesija start 22–05)
        $hasNight = DB::table("$sessionTable as s")
            ->when($userId, fn($q) => $q->where('s.user_id', $userId))
            ->whereRaw("HOUR(s.started_at) >= 22 OR HOUR(s.started_at) < 5")
            ->exists();

        $badges = [
            [
                'code' => 'pb',
                'title' => 'Personal Best',
                'desc' => 'Najveći pojedinačni ulov (kg)',
                'unlocked' => $pb > 0,
                'value' => round((float)$pb, 2),
            ],
            [
                'code' => 'streak2',
                'title' => 'Back-to-Back',
                'desc' => 'Dve sesije zaredom (dani)',
                'unlocked' => $streak2,
            ],
            [
                'code' => 'nightowl',
                'title' => 'Noćni čuvar',
                'desc' => 'Sesija noću (22–05)',
                'unlocked' => $hasNight,
            ],
        ];

        return response()->json($badges);
    }
}
