<?php

use Tests\TestCase;

uses(TestCase::class);

it('never combines wildcard origins with credentialed CORS', function () {
    expect(config('cors.supports_credentials'))->toBeTrue()
        ->and(config('cors.allowed_origins'))->not->toContain('*')
        ->and(config('cors.allowed_methods'))->not->toContain('*')
        ->and(config('cors.allowed_headers'))->not->toContain('*')
        ->and(config('cors.allowed_methods'))->toContain('OPTIONS')
        ->and(config('cors.allowed_headers'))->toContain(
            'Authorization',
            'Content-Type',
            'Precognition',
            'Precognition-Validate-Only',
            'X-CSRF-TOKEN',
            'X-Socket-ID',
            'X-XSRF-TOKEN',
        );
});

it('accepts explicit origins from CORS_ALLOWED_ORIGINS', function () {
    $previousCorsAllowedOrigins = getenv('CORS_ALLOWED_ORIGINS');
    $configuredOrigins = 'https://frontend.example.com, http://localhost:4173';

    putenv("CORS_ALLOWED_ORIGINS={$configuredOrigins}");
    $_ENV['CORS_ALLOWED_ORIGINS'] = $configuredOrigins;
    $_SERVER['CORS_ALLOWED_ORIGINS'] = $configuredOrigins;

    try {
        $corsConfig = require base_path('config/cors.php');

        expect($corsConfig['allowed_origins'])->toBe([
            'https://frontend.example.com',
            'http://localhost:4173',
        ]);
    } finally {
        if ($previousCorsAllowedOrigins === false) {
            putenv('CORS_ALLOWED_ORIGINS');
            unset($_ENV['CORS_ALLOWED_ORIGINS'], $_SERVER['CORS_ALLOWED_ORIGINS']);
        } else {
            putenv("CORS_ALLOWED_ORIGINS={$previousCorsAllowedOrigins}");
            $_ENV['CORS_ALLOWED_ORIGINS'] = $previousCorsAllowedOrigins;
            $_SERVER['CORS_ALLOWED_ORIGINS'] = $previousCorsAllowedOrigins;
        }
    }
});

it('does not include development origins or patterns in production defaults', function () {
    $previousAppEnvironment = getenv('APP_ENV');
    $previousAppUrl = getenv('APP_URL');
    $previousFrontendUrl = getenv('FRONTEND_URL');
    $previousCorsAllowedOrigins = getenv('CORS_ALLOWED_ORIGINS');

    putenv('APP_ENV=production');
    putenv('APP_URL=https://schooltool.example');
    putenv('FRONTEND_URL');
    putenv('CORS_ALLOWED_ORIGINS');
    $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'production';
    $_ENV['APP_URL'] = $_SERVER['APP_URL'] = 'https://schooltool.example';
    unset(
        $_ENV['FRONTEND_URL'],
        $_SERVER['FRONTEND_URL'],
        $_ENV['CORS_ALLOWED_ORIGINS'],
        $_SERVER['CORS_ALLOWED_ORIGINS'],
    );

    try {
        $corsConfig = require base_path('config/cors.php');

        expect($corsConfig['allowed_origins'])->toBe(['https://schooltool.example'])
            ->and($corsConfig['allowed_origins_patterns'])->toBeEmpty()
            ->and($corsConfig['allowed_origins'])
            ->not->toContain('http://localhost:5173', 'http://127.0.0.1:5173');
    } finally {
        foreach ([
            'APP_ENV' => $previousAppEnvironment,
            'APP_URL' => $previousAppUrl,
            'FRONTEND_URL' => $previousFrontendUrl,
            'CORS_ALLOWED_ORIGINS' => $previousCorsAllowedOrigins,
        ] as $name => $previousValue) {
            if ($previousValue === false) {
                putenv($name);
                unset($_ENV[$name], $_SERVER[$name]);

                continue;
            }

            putenv("{$name}={$previousValue}");
            $_ENV[$name] = $previousValue;
            $_SERVER[$name] = $previousValue;
        }
    }
});
