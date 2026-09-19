<?php

use App\Http\Middleware\AuthenticateFeaturePreviewControl;
use App\Services\FeaturePreviewControlClient;
use App\Services\FeaturePreviewControlDecision;
use App\Services\FeaturePreviewControlSignature;
use App\Services\FeaturePreviewRuntimeService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->privateControlDirectory = sys_get_temp_dir().'/schooltool-control-security-'.bin2hex(random_bytes(8));
    mkdir($this->privateControlDirectory, 0700);
    file_put_contents($this->privateControlDirectory.'/control.key', random_bytes(32));
    chmod($this->privateControlDirectory.'/control.key', 0600);
    config([
        'schooltool.preview.instance' => false,
        'schooltool.preview.live_url' => 'https://main.example.test',
        'schooltool.preview.control_url' => 'https://main.example.test/api/feature-preview/control',
        'schooltool.preview.control_key_path' => $this->privateControlDirectory.'/control.key',
        'schooltool.preview.control_rate_limit' => 1200,
        'cache.default' => 'database',
        'cache.stores.file' => ['driver' => 'file', 'path' => $this->privateControlDirectory.'/cache', 'lock_path' => $this->privateControlDirectory.'/cache'],
    ]);
    Cache::purge('file');
    Http::preventStrayRequests();
});

afterEach(function (): void {
    Cache::purge('file');
    (new Filesystem)->deleteDirectory($this->privateControlDirectory);
});

function previewControlRequest(string $body, ?array $headers = null, string $method = 'POST', string $url = 'https://main.example.test/api/feature-preview/control'): Request
{
    $request = Request::create($url, $method, server: ['CONTENT_TYPE' => 'application/json'], content: $body);
    $request->headers->add($headers ?? app(FeaturePreviewControlSignature::class)->requestHeaders($body));

    return $request;
}

function previewControlClientHeaders(ClientRequest $request): array
{
    $headers = [];
    foreach ([FeaturePreviewControlSignature::TIMESTAMP, FeaturePreviewControlSignature::NONCE, FeaturePreviewControlSignature::SIGNATURE] as $name) {
        $headers[$name] = $request->header($name)[0] ?? '';
    }

    return $headers;
}

test('control middleware verifies raw bytes and signs a response bound to that exact request', function (): void {
    $body = '{"operation":"admission","payload":{"identity":{"id":9}}}';
    $request = previewControlRequest($body);
    $request->request->set('payload', ['identity' => ['id' => 99]]);
    $response = app(AuthenticateFeaturePreviewControl::class)->handle($request, function (Request $verified) {
        expect($verified->attributes->get('feature_preview_control_operation'))->toBe('admission')
            ->and($verified->attributes->get('feature_preview_control_payload'))->toBe(['identity' => ['id' => 9]]);

        return response()->json(['allowed' => true]);
    });
    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
    $requestHeaders = $responseHeaders = [];
    foreach ([FeaturePreviewControlSignature::TIMESTAMP, FeaturePreviewControlSignature::NONCE, FeaturePreviewControlSignature::SIGNATURE] as $name) {
        $requestHeaders[$name] = $request->header($name);
        $responseHeaders[$name] = $response->headers->get($name);
    }
    app(FeaturePreviewControlSignature::class)->validateResponse($response->getContent(), $body, $requestHeaders, $responseHeaders, 200);
    expect(fn () => app(FeaturePreviewControlSignature::class)->validateRequest($response->getContent(), $responseHeaders))
        ->toThrow(RuntimeException::class);
});

test('the stateless main control route reaches only the signed decision endpoint', function (): void {
    $decision = Mockery::mock(FeaturePreviewControlDecision::class);
    $decision->shouldReceive('decide')->once()->with('admission', ['identity' => ['id' => 9]])->andReturn(['allowed' => true]);
    app()->instance(FeaturePreviewControlDecision::class, $decision);
    $body = '{"operation":"admission","payload":{"identity":{"id":9}}}';
    $headers = app(FeaturePreviewControlSignature::class)->requestHeaders($body);
    $server = ['CONTENT_TYPE' => 'application/json', 'HTTPS' => 'on'];
    foreach ($headers as $name => $value) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
    }

    $response = $this->call('POST', config('schooltool.preview.control_url'), server: $server, content: $body)
        ->assertOk()->assertExactJson(['allowed' => true]);
    expect($response->headers->has(FeaturePreviewControlSignature::SIGNATURE))->toBeTrue()
        ->and($response->headers->has('Set-Cookie'))->toBeFalse();
});

test('control middleware rejects malformed or modified requests before decisions run', function (string $change): void {
    $body = '{"operation":"status","payload":{}}';
    $headers = app(FeaturePreviewControlSignature::class)->requestHeaders($body);
    $method = 'POST';
    $url = config('schooltool.preview.control_url');
    if ($change === 'body') {
        $body = '{"operation":"admission","payload":{}}';
    }
    if ($change === 'past') {
        $headers[FeaturePreviewControlSignature::TIMESTAMP] = (string) (time() - 31);
    }
    if ($change === 'future') {
        $headers[FeaturePreviewControlSignature::TIMESTAMP] = (string) (time() + 31);
    }
    if ($change === 'signature') {
        $headers[FeaturePreviewControlSignature::SIGNATURE] = str_repeat('0', 64);
    }
    if ($change === 'nonce') {
        $headers[FeaturePreviewControlSignature::NONCE] = str_repeat('0', 32);
    }
    if ($change === 'method') {
        $method = 'GET';
    }
    if ($change === 'path') {
        $url .= '/other';
    }
    if ($change === 'query') {
        $url .= '?operation=status';
    }
    if ($change === 'host') {
        $url = str_replace('main.', 'other.', $url);
    }
    if ($change === 'http') {
        $url = str_replace('https:', 'http:', $url);
    }
    if ($change === 'preview') {
        config(['schooltool.preview.instance' => true]);
    }
    if ($change === 'unknown operation') {
        $body = '{"operation":"query","payload":{}}';
        $headers = app(FeaturePreviewControlSignature::class)->requestHeaders($body);
    }
    $response = app(AuthenticateFeaturePreviewControl::class)->handle(previewControlRequest($body, $headers, $method, $url), function () {
        throw new RuntimeException('Decision must never be called.');
    });

    expect($response->getStatusCode())->toBe(403);
})->with(['body', 'past', 'future', 'signature', 'nonce', 'method', 'path', 'query', 'host', 'http', 'preview', 'unknown operation']);

test('control replay and rate protection uses local files and fails closed without its cache', function (string $failure): void {
    $body = '{"operation":"status","payload":{}}';
    $request = previewControlRequest($body);
    $middleware = app(AuthenticateFeaturePreviewControl::class);
    if ($failure === 'cache') {
        config(['cache.stores.file.driver' => 'database']);
    } else {
        config(['schooltool.preview.control_rate_limit' => 1]);
        expect($middleware->handle($request, fn () => response()->json(['allowed' => true]))->getStatusCode())->toBe(200);
    }
    $response = $middleware->handle($failure === 'rate' ? previewControlRequest($body) : $request, function () {
        throw new RuntimeException('Replayed or limited request reached the decision.');
    });
    expect($response->getStatusCode())->toBe(403);
})->with(['replay', 'rate', 'cache']);

test('control keys must be private external files and endpoints must be fixed HTTPS URLs', function (string $url): void {
    expect(app(FeaturePreviewControlSignature::class)->configurationIsSafe())->toBeTrue();
    config(['schooltool.preview.control_url' => $url]);
    expect(app(FeaturePreviewControlSignature::class)->configurationIsSafe())->toBeFalse();
})->with([
    'http://main.example.test/api/feature-preview/control',
    'https://main.example.test:8443/api/feature-preview/control',
    'https://other.example.test/api/feature-preview/control',
    'https://main.example.test/api/feature-preview/control?x=1',
    'https://main.example.test/api/feature-preview/control#x',
    'https://user:password@main.example.test/api/feature-preview/control',
    'https://main.example.test/api/feature-preview/other',
]);

test('control refuses malformed keys and key files inside the application', function (): void {
    file_put_contents($this->privateControlDirectory.'/control.key', 'too short');
    expect(app(FeaturePreviewControlSignature::class)->configurationIsSafe())->toBeFalse();
    $path = storage_path('framework/control-key-test-'.bin2hex(random_bytes(6)));
    file_put_contents($path, random_bytes(32));
    config(['schooltool.preview.control_key_path' => $path]);
    try {
        expect(app(FeaturePreviewControlSignature::class)->configurationIsSafe())->toBeFalse();
    } finally {
        unlink($path);
    }
});

test('control keys cannot reuse the application encryption key', function (): void {
    config(['app.key' => 'base64:'.base64_encode(file_get_contents($this->privateControlDirectory.'/control.key'))]);
    expect(app(FeaturePreviewControlSignature::class)->configurationIsSafe())->toBeFalse();
});

test('control refuses oversized request bodies before decisions run', function (): void {
    $body = str_repeat('x', FeaturePreviewControlSignature::MAX_BODY_BYTES + 1);
    $request = previewControlRequest($body, [FeaturePreviewControlSignature::SIGNATURE => str_repeat('0', 64)]);
    $request->headers->set('Content-Length', (string) strlen($body));
    expect(app(AuthenticateFeaturePreviewControl::class)->handle($request, function () {
        throw new RuntimeException('Oversized request reached the decision.');
    })->getStatusCode())->toBe(403);
});

test('control client accepts only the complete typed source identity status', function (): void {
    $expected = ['schema_ready' => true, 'enabled' => false, 'source' => [
        'database' => 'main_db', 'server_fingerprint' => str_repeat('a', 64), 'app_key_fingerprint' => str_repeat('b', 64),
    ]];
    Http::fake(function (ClientRequest $request) use ($expected) {
        $body = json_encode($expected, JSON_THROW_ON_ERROR);

        return Http::response($body, 200, [
            ...app(FeaturePreviewControlSignature::class)->responseHeaders($body, $request->body(), previewControlClientHeaders($request)),
            'Content-Type' => 'application/json',
        ]);
    });
    expect(app(FeaturePreviewControlClient::class)->request('status'))->toBe($expected);
});

test('preview client permits a signed response only with safe transport and fresh binding', function (): void {
    config(['schooltool.preview.instance' => true]);
    app(FeaturePreviewRuntimeService::class)->install();
    $previousBody = $previousHeaders = null;
    Http::fake(function (ClientRequest $request, array $options) use (&$previousBody, &$previousHeaders) {
        expect($options['verify'])->toBeTrue()->and($options['allow_redirects'])->toBeFalse()
            ->and($options['connect_timeout'])->toBe(2)->and($options['timeout'])->toBe(5);
        if ($previousBody === null) {
            $previousBody = '{"allowed":true}';
            $previousHeaders = app(FeaturePreviewControlSignature::class)->responseHeaders($previousBody, $request->body(), previewControlClientHeaders($request));
        }

        return Http::response($previousBody, 200, [...$previousHeaders, 'Content-Type' => 'application/json']);
    });
    expect(app(FeaturePreviewControlClient::class)->request('admission', ['identity' => ['id' => 9]]))->toBe(['allowed' => true]);
    expect(fn () => app(FeaturePreviewControlClient::class)->request('admission', ['identity' => ['id' => 9]]))
        ->toThrow(RuntimeException::class, 'unavailable or returned an invalid response');
});

test('preview client fails closed on unsafe or malformed responses', function (string $failure): void {
    Http::fake(function (ClientRequest $request) use ($failure) {
        $body = match ($failure) {
            'wrong type' => '{"allowed":"true"}',
            'extra data' => '{"allowed":true,"password":"secret"}',
            'invalid json' => 'not json',
            'oversized' => str_repeat('x', FeaturePreviewControlSignature::MAX_BODY_BYTES + 1),
            default => '{"allowed":true}',
        };
        $status = match ($failure) {
            'redirect' => 302, 'server error' => 503, default => 200
        };
        $headers = $failure === 'oversized' ? [] : app(FeaturePreviewControlSignature::class)->responseHeaders($body, $request->body(), previewControlClientHeaders($request), $status);
        if ($failure === 'signature') {
            $headers[FeaturePreviewControlSignature::SIGNATURE] = str_repeat('0', 64);
        }
        if ($failure === 'expired') {
            $headers[FeaturePreviewControlSignature::TIMESTAMP] = (string) (time() - 31);
        }

        return Http::response($body, $status, [...$headers, 'Content-Type' => $failure === 'content type' ? 'text/html' : 'application/json', 'Location' => 'https://other.example.test']);
    });
    expect(fn () => app(FeaturePreviewControlClient::class)->request('admission'))
        ->toThrow(RuntimeException::class, 'unavailable or returned an invalid response');
    Http::assertSentCount(1);
})->with(['wrong type', 'extra data', 'invalid json', 'oversized', 'redirect', 'server error', 'signature', 'expired', 'content type']);

test('runtime blocks attempts to weaken the single signed control transport', function (string $failure): void {
    config(['schooltool.preview.instance' => true]);
    app(FeaturePreviewRuntimeService::class)->install();
    Http::fake();
    $body = '{"operation":"status","payload":{}}';
    $headers = app(FeaturePreviewControlSignature::class)->requestHeaders($body);
    $options = ['verify' => true, 'allow_redirects' => false, 'proxy' => '', 'timeout' => 5, 'connect_timeout' => 2];
    if ($failure === 'tls') {
        $options['verify'] = false;
    }
    if ($failure === 'redirects') {
        $options['allow_redirects'] = true;
    }
    if ($failure === 'proxy') {
        $options['proxy'] = 'https://other.example.test';
    }
    if ($failure === 'timeout') {
        $options['timeout'] = 0;
    }
    if ($failure === 'unsigned') {
        $headers = [];
    }
    $url = config('schooltool.preview.control_url').($failure === 'query' ? '?x=1' : '');

    expect(fn () => Http::withHeaders($headers)->withBody($body, 'application/json')->withOptions($options)->post($url))
        ->toThrow(RuntimeException::class, 'Outbound HTTP integrations are disabled');
    Http::assertNothingSent();
})->with(['tls', 'redirects', 'proxy', 'timeout', 'unsigned', 'query']);
