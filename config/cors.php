<?php

$normalizeCorsOrigin = static function (?string $origin): ?string {
    if ($origin === null) {
        return null;
    }

    $origin = trim($origin);

    if ($origin === '') {
        return null;
    }

    if (! str_contains($origin, '://')) {
        return $origin;
    }

    $parts = parse_url($origin);

    if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
        return null;
    }

    $port = isset($parts['port']) ? ':'.$parts['port'] : '';

    return strtolower($parts['scheme']).'://'.strtolower($parts['host']).$port;
};

$configuredCorsOrigins = array_values(array_filter(array_map(
    static fn (string $origin): ?string => $normalizeCorsOrigin($origin),
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
)));

$defaultCorsOrigins = array_values(array_unique(array_filter([
    $normalizeCorsOrigin((string) env('APP_URL', '')),
    $normalizeCorsOrigin((string) env('FRONTEND_URL', '')),
    'http://localhost',
    'http://localhost:3000',
    'http://localhost:5173',
    'http://127.0.0.1',
    'http://127.0.0.1:8000',
    'http://127.0.0.1:5173',
    'https://localhost',
    'https://127.0.0.1',
    'https://schooltool.at',
])));

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => $configuredCorsOrigins !== [] ? $configuredCorsOrigins : $defaultCorsOrigins,

    'allowed_origins_patterns' => [
        '#^https?://.*\\.localhost(:\\d+)?$#',
        '#^https?://.*\\.test(:\\d+)?$#',
    ],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'Origin',
        'Precognition',
        'Precognition-Validate-Only',
        'X-CSRF-TOKEN',
        'X-Requested-With',
        'X-Socket-ID',
        'X-XSRF-TOKEN',
    ],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
