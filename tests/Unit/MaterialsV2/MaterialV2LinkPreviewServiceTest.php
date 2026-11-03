<?php

use App\Services\MaterialsV2\MaterialV2LinkPreviewService;
use App\Support\RemoteUrlGuard;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;
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
        'nicht geprüft',
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

it('uses the pinned address and bounds real responses without the PHP stream transport', function (string $prefix, int $extraBytes, string $status, ?string $title) {
    $serverCode = <<<'PHP'
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($server === false) { throw new RuntimeException($error); }
        echo stream_socket_get_name($server, false)."\n";
        flush();
        $client = stream_socket_accept($server, 10);
        if ($client === false) { exit(1); }
        while (($line = fgets($client)) !== false && trim($line) !== '') {}
        $body = base64_decode($argv[1]).str_repeat('x', (int) $argv[2]);
        fwrite($client, "HTTP/1.1 200 OK\r\nContent-Type: text/html\r\nConnection: close\r\n\r\n");
        for ($offset = 0; $offset < strlen($body); $offset += 8192) {
            if (@fwrite($client, substr($body, $offset, 8192)) === false) { break; }
        }
        fclose($client);
        fclose($server);
        PHP;
    $server = new Process([PHP_BINARY, '-r', $serverCode, base64_encode($prefix), (string) $extraBytes], timeout: 15);
    $server->start();

    try {
        expect($server->waitUntil(fn (string $type, string $output): bool => $type === Process::OUT && str_contains($output, "\n")))->toBeTrue();
        $port = (int) substr(trim($server->getOutput()), strlen('127.0.0.1:'));
        $url = "http://materials-preview.invalid:{$port}/lesson";
        $this->mock(RemoteUrlGuard::class)
            ->shouldReceive('resolve')
            ->once()
            ->with($url)
            ->andReturn(['url' => $url, 'host' => 'materials-preview.invalid', 'port' => $port, 'ip' => '127.0.0.1']);
        Http::allowStrayRequests([$url]);

        $result = app(MaterialV2LinkPreviewService::class)->inspect($url);

        expect($result)->toMatchArray([
            'reachable' => true,
            'status' => $status,
            'title' => $title,
            'http_status' => 200,
        ]);
    } finally {
        $server->stop();
    }
})->with([
    'complete title before a large body' => ['<html><head><title>Unterricht</title></head><body>', 600000, 'success', 'Unterricht'],
    'body exceeds the title preview limit' => ['<html><head>', 600000, 'warning', null],
    'small page without title' => ['<html><head></head><body>Unterricht</body></html>', 0, 'warning', null],
    'page ending without a head closing tag' => ['<html><title>Unterricht</title><body>Inhalt</body></html>', 0, 'success', 'Unterricht'],
]);
