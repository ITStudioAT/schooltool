<?php

namespace App\Http\Middleware;

use App\Services\FeaturePreviewControlSignature;
use Closure;
use Illuminate\Cache\FileStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuthenticateFeaturePreviewControl
{
    public function __construct(private FeaturePreviewControlSignature $signature) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $url = $this->signature->url();
            $length = (string) $request->header('Content-Length', '');
            if (config('schooltool.preview.instance') || ! $request->isSecure() || ! $request->isMethod('POST')
                || $request->getPathInfo() !== FeaturePreviewControlSignature::PATH || $request->getQueryString() !== null
                || $request->getHost() !== parse_url($url, PHP_URL_HOST) || $request->getPort() !== 443
                || strtolower(trim(explode(';', (string) $request->header('Content-Type'))[0])) !== 'application/json'
                || ($length !== '' && (! ctype_digit($length) || (int) $length > FeaturePreviewControlSignature::MAX_BODY_BYTES))) {
                throw new RuntimeException('Invalid preview control request.');
            }
            $body = $request->getContent();
            $headers = [];
            foreach ([FeaturePreviewControlSignature::TIMESTAMP, FeaturePreviewControlSignature::NONCE, FeaturePreviewControlSignature::SIGNATURE] as $name) {
                $headers[$name] = (string) $request->header($name);
            }
            $this->signature->validateRequest($body, $headers);
            $envelope = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
            if (! is_array($envelope) || count($envelope) !== 2
                || ! in_array($envelope['operation'] ?? null, ['status', 'admission', 'recipient'], true)
                || ! is_array($envelope['payload'] ?? null)) {
                throw new RuntimeException('Invalid preview control operation.');
            }
            $this->consumeNonce($headers[FeaturePreviewControlSignature::NONCE]);
            $request->attributes->set('feature_preview_control_operation', $envelope['operation']);
            $request->attributes->set('feature_preview_control_payload', $envelope['payload']);
        } catch (Throwable) {
            return response()->json(['message' => 'Preview control request refused.'], 403)->header('Cache-Control', 'no-store, private');
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        if ($response->getStatusCode() === 200 && is_string($response->getContent())) {
            foreach ($this->signature->responseHeaders($response->getContent(), $body, $headers) as $name => $value) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    private function consumeNonce(string $nonce): void
    {
        if (config('cache.stores.file.driver') !== 'file') {
            throw new RuntimeException('Preview control replay protection requires the local file cache.');
        }
        $cache = Cache::store('file');
        $store = $cache->getStore();
        if (! $store instanceof FileStore) {
            throw new RuntimeException('Preview control replay protection requires the local file cache.');
        }
        $store->lock('schooltool-preview-control-replay-lock', 5)->block(2, function () use ($cache, $nonce): void {
            $now = time();
            $state = $cache->get('schooltool-preview-control-replay', ['nonces' => [], 'minute' => intdiv($now, 60), 'count' => 0]);
            if (! is_array($state) || ! is_array($state['nonces'] ?? null)
                || ! is_int($state['minute'] ?? null) || ! is_int($state['count'] ?? null)) {
                throw new RuntimeException('Invalid preview control replay state.');
            }
            $nonces = array_filter($state['nonces'], static fn ($expires): bool => is_int($expires) && $expires >= $now);
            $count = $state['minute'] === intdiv($now, 60) ? $state['count'] : 0;
            $limit = (int) config('schooltool.preview.control_rate_limit', 1200);
            if (isset($nonces[$nonce]) || $limit < 1 || $count >= min($limit, 12000)) {
                throw new RuntimeException('Preview control request is replayed or rate limited.');
            }
            $nonces[$nonce] = $now + 2 * FeaturePreviewControlSignature::CLOCK_WINDOW + 1;
            if (! $cache->put('schooltool-preview-control-replay', ['nonces' => $nonces, 'minute' => intdiv($now, 60), 'count' => $count + 1], 2 * FeaturePreviewControlSignature::CLOCK_WINDOW + 2)) {
                throw new RuntimeException('Cannot record preview control replay state.');
            }
        });
    }
}
