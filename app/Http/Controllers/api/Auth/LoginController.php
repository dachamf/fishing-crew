<?php

namespace App\Http\Controllers\api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            if (! $user) {
                throw ValidationException::withMessages([
                    'email' => ['The provided credentials are incorrect.'],
                ]);
            } elseif (! Hash::check($request->password, $user->password)) {
                throw ValidationException::withMessages([
                    'password' => ['The provided credentials are incorrect.'],
                ]);
            }
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('fishing_crew_api_token')->plainTextToken;
        $cookieDomain = $this->resolveCookieDomain($request);

        return response()->json([
            'user' => $user,
        ], Response::HTTP_OK)
            ->cookie('auth_token', $token, 60 * 24 * 30, '/', $cookieDomain, true, true, false, 'Lax');
    }

    private function resolveCookieDomain(LoginRequest $request): ?string
    {
        $domain = config('session.domain');
        if ($domain) {
            return $domain;
        }

        $frontendHost = parse_url((string) config('app.frontend_url'), PHP_URL_HOST);
        $apiHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $suffix = $this->commonDomainSuffix($frontendHost, $apiHost);
        if ($suffix) {
            return '.'.$suffix;
        }

        $host = $request->getHost();
        if ($host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) {
            return null;
        }

        $parts = explode('.', $host);
        if (count($parts) <= 2) {
            return $host;
        }

        $first = $parts[0];
        if (in_array($first, ['api', 'app', 'www'], true)) {
            return '.'.implode('.', array_slice($parts, 1));
        }

        return $host;
    }

    private function commonDomainSuffix(?string $left, ?string $right): ?string
    {
        if (! $left || ! $right) {
            return null;
        }

        $leftParts = array_reverse(explode('.', $left));
        $rightParts = array_reverse(explode('.', $right));
        $common = [];

        $max = min(count($leftParts), count($rightParts));
        for ($i = 0; $i < $max; $i++) {
            if ($leftParts[$i] !== $rightParts[$i]) {
                break;
            }
            $common[] = $leftParts[$i];
        }

        if (count($common) < 2) {
            return null;
        }

        return implode('.', array_reverse($common));
    }
}
