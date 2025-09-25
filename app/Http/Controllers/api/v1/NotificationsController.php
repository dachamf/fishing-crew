<?php
namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationsController extends Controller
{
    public function unreadCount(Request $r) {
        $uid = $r->user()->id;

        // 1) Session reviews koji čekaju MENE
        $pending = DB::table('session_reviews')->where('reviewer_id', $uid)->where('status','pending')->count();

        // 2) (opciono) nove nominacije u poslednjih X dana
        $recentNoms = DB::table('session_reviews')
            ->where('reviewer_id', $uid)
            ->whereNull('decided_at')
            ->where('status','pending')
            ->count();

        $total = (int)($pending + $recentNoms);

        return response()->json(['unread_count' => $total]);
    }
}
