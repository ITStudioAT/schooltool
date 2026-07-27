<?php

use App\Services\MaterialsV2\MaterialV2LinkPreviewService;
use App\Support\RemoteUrlGuard;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Http::preventStrayRequests();
});

it('confirms an html target and extracts a clean title', function () {
    $this->mock(RemoteUrlGuard::class)
        ->shouldReceive('resolve')
        ->once()
        ->with('https://example.org/unterricht')
        ->andReturn([
            'url' => 'https://example.org/unterricht',
            'host' => 'example.org',
            'port' => 443,
            'ip' => '93.184.216.34',
        ]);

    Http::fake([
        'https://example.org/*' => Http::response(
            '<html><head><title>  Lernen &amp; Lehren  </title></head><body>Inhalt</body></html>',
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        ),
    ]);

    $result = app(MaterialV2LinkPreviewService::class)->inspect('https://example.org/unterricht');

    expect($result)
        ->toMatchArray([
            'reachable' => true,
            'status' => 'success',
            'url' => 'https://example.org/unterricht',
            'title' => 'Lernen & Lehren',
            'http_status' => 200,
        ]);

    Http::assertSentCount(1);
});

it('rechecks every redirect target before requesting it', function () {
    $guard = $this->mock(RemoteUrlGuard::class);
    $guard->shouldReceive('resolve')
        ->once()
        ->with('https://example.org/start')
        ->andReturn([
            'url' => 'https://example.org/start',
            'host' => 'example.org',
            'port' => 443,
            'ip' => '93.184.216.34',
        ]);
    $guard->shouldReceive('resolve')
        ->once()
        ->with('https://example.org/final')
        ->andReturn([
            'url' => 'https://example.org/final',
            'host' => 'example.org',
            'port' => 443,
            'ip' => '93.184.216.34',
        ]);

    Http::fakeSequence()
        ->push('', 302, ['Location' => '/final'])
        ->push('<html><head><title>Finales Ziel</title></head></html>', 200, [
            'Content-Type' => 'text/html',
        ]);

    $result = app(MaterialV2LinkPreviewService::class)->inspect('https://example.org/start');

    expect($result)
        ->toMatchArray([
            'reachable' => true,
            'status' => 'success',
            'url' => 'https://example.org/final',
            'title' => 'Finales Ziel',
        ]);

    Http::assertSentCount(2);
});

it('blocks a redirect to a non-public target before the second request', function () {
    $guard = $this->mock(RemoteUrlGuard::class);
    $guard->shouldReceive('resolve')
        ->once()
        ->with('https://example.org/start')
        ->andReturn([
            'url' => 'https://example.org/start',
            'host' => 'example.org',
            'port' => 443,
            'ip' => '93.184.216.34',
        ]);
    $guard->shouldReceive('resolve')
        ->once()
        ->with('http://169.254.169.254/latest/meta-data')
        ->andThrow(new InvalidArgumentException('Private address.'));

    Http::fake([
        'https://example.org/start' => Http::response('', 302, [
            'Location' => 'http://169.254.169.254/latest/meta-data',
        ]),
    ]);

    $result = app(MaterialV2LinkPreviewService::class)->inspect('https://example.org/start');

    expect($result)
        ->toMatchArray([
            'reachable' => false,
            'status' => 'warning',
            'title' => null,
        ])
        ->and($result['message'])->toContain('blockiert');

    Http::assertSentCount(1);
});

it('warns when the target is unavailable, not html, or has no title', function (
    string $responseType,
    string $body,
    int $status,
    array $headers,
    string $expectedMessage,
) {
    $this->mock(RemoteUrlGuard::class)
        ->shouldReceive('resolve')
        ->once()
        ->andReturn([
            'url' => 'https://example.org/resource',
            'host' => 'example.org',
            'port' => 443,
            'ip' => '93.184.216.34',
        ]);

    if ($responseType === 'connection') {
        Http::fake([
            '*' => Http::failedConnection(),
        ]);
    } else {
        Http::fake([
            '*' => Http::response($body, $status, $headers),
        ]);
    }

    $result = app(MaterialV2LinkPreviewService::class)->inspect('https://example.org/resource');

    expect($result['status'])->toBe('warning')
        ->and($result['title'])->toBeNull()
        ->and($result['message'])->toContain($expectedMessage);
})->with([
    'connection failure' => [
        'connection',
        '',
        0,
        [],
        'nicht erreichbar',
    ],
    'http failure' => [
        'response',
        'Nicht gefunden',
        404,
        ['Content-Type' => 'text/html'],
        'HTTP-Status 404',
    ],
    'non html response' => [
        'response',
        '{"ok":true}',
        200,
        ['Content-Type' => 'application/json'],
        'keine Webseite',
    ],
    'missing title' => [
        'response',
        '<html><head></head><body>Ohne Titel</body></html>',
        200,
        ['Content-Type' => 'text/html'],
        'keinen Seitentitel',
    ],
    'oversized response' => [
        'response',
        '<html></html>',
        200,
        [
            'Content-Type' => 'text/html',
            'Content-Length' => '524289',
        ],
        'zu groß',
    ],
]);
