<?php
// app/Http/Controllers/api/Auth/EmailVerificationController.php
namespace App\Http\Controllers\api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Verified;

class EmailVerificationController extends Controller
{
    // GET /api/auth/verify-email/{id}/{hash}
    public function verify(Request $request)
    {
        $user = \App\Models\User::findOrFail($request->route('id'));

        // Proveri hash iz linka
        $expected = sha1($user->getEmailForVerification());
        if (! hash_equals($expected, (string) $request->route('hash'))) {
            return response()->json(['message' => 'Invalid verification link'], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return $this->redirectToApp('already-verified');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return $this->redirectToApp('success');
    }

    // POST /api/auth/email/verification-notification (auth:sanctum)
    public function send(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['status' => 'already-verified']);
        }
        $request->user()->sendEmailVerificationNotification();
        return response()->json(['status' => 'verification-link-sent']);
    }

    protected function redirectToApp(string $status)
    {
        $app = config('app.frontend_url');
        return redirect()->away($app.'/verify?status='.$status);
    }
}
