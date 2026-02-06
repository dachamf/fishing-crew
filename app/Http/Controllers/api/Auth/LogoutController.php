<?php

namespace App\Http\Controllers\api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LogoutController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        $cookieDomain = $this->resolveCookieDomain($request);

        return response()->noContent()
            ->withoutCookie('auth_token', '/', $cookieDomain);
    }

    private function resolveCookieDomain(Request $request): ?string
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
