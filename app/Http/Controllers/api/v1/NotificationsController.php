<?php
namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class NotificationsController extends Controller
{
    public function unreadCount(Request $r) {
        $uid = $r->user()->id;
        $key = "u:{$uid}:notif:unread";

        $pending = Cache::remember($key, 30, function () use ($uid) {
            return \DB::table('session_reviews')
                ->where('reviewer_id', $uid)
                ->where('status', 'pending')
                ->count();
        });

        return response()->json(['unread_count' => (int) $pending]);
    }
}
