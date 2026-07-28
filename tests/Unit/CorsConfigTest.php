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
