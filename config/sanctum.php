<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;
use Laravel\Sanctum\Sanctum;

$normalizeStatefulDomain = static function (?string $domain): ?string {
    if ($domain === null) {
        return null;
    }

    $domain = trim($domain);

    if ($domain === '') {
        return null;
    }

    if (! str_contains($domain, '://')) {
        return strtolower($domain);
    }

    $parts = parse_url($domain);

    if (! is_array($parts) || ! isset($parts['host'])) {
        return null;
    }

    $port = isset($parts['port']) ? ':'.$parts['port'] : '';

    return strtolower($parts['host']).$port;
};

$configuredStatefulDomains = array_values(array_unique(array_filter(array_map(
    static fn (string $domain): ?string => $normalizeStatefulDomain($domain),
    explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', ''))
))));

$defaultStatefulDomains = array_values(array_unique(array_filter([
    'localhost',
    'localhost:3000',
    'localhost:5173',
    '127.0.0.1',
    '127.0.0.1:8000',
    '127.0.0.1:5173',
    '::1',
    'schooltool.test',
    $normalizeStatefulDomain((string) env('APP_URL', '')),
    $normalizeStatefulDomain((string) env('FRONTEND_URL', '')),
    $normalizeStatefulDomain(Sanctum::currentApplicationUrlWithPort()),
    Sanctum::currentRequestHost(),
])));

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Requests from the following domains / hosts will receive stateful API
    | authentication cookies. Typically, these should include your local
    | and production domains which access your API via a frontend SPA.
    |
    */

    'stateful' => array_values(array_unique(array_merge(
        $configuredStatefulDomains,
        $defaultStatefulDomains,
    ))),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | This array contains the authentication guards that will be checked when
    | Sanctum is trying to authenticate a request. If none of these guards
    | are able to authenticate the request, Sanctum will use the bearer
    | token that's present on an incoming request for authentication.
    |
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | This value controls the number of minutes until an issued token will be
    | considered expired. This will override any values set in the token's
    | "expires_at" attribute, but first-party sessions are not affected.
    |
    */

    'expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    |
    | Sanctum can prefix new tokens in order to take advantage of numerous
    | security scanning initiatives maintained by open source platforms
    | that notify developers if they commit tokens into repositories.
    |
    | See: https://docs.github.com/en/code-security/secret-scanning/about-secret-scanning
    |
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | When authenticating your first-party SPA with Sanctum you may need to
    | customize some of the middleware Sanctum uses while processing the
    | request. You may change the middleware listed below as required.
    |
    */

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => PreventRequestForgery::class,
    ],

];
