<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

class FeaturePreviewControlClient
{
    public function __construct(private FeaturePreviewControlSignature $signature) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function request(string $operation, array $payload = []): array
    {
        try {
            if (! in_array($operation, ['status', 'admission', 'recipient'], true)) {
                throw new RuntimeException('Unsupported preview control operation.');
            }
            $body = json_encode(['operation' => $operation, 'payload' => (object) $payload], JSON_THROW_ON_ERROR);
            $headers = $this->signature->requestHeaders($body);
            $response = Http::acceptJson()->withHeaders($headers)->withBody($body, 'application/json')
                ->connectTimeout(2)->timeout(5)->withoutRedirecting()->withOptions([
                    'verify' => true, 'proxy' => '', 'stream' => true, 'read_timeout' => 5, 'decode_content' => false,
                    'on_headers' => function (ResponseInterface $response): void {
                        $length = $response->getHeaderLine('Content-Length');
                        if ($length !== '' && (! ctype_digit($length) || (int) $length > FeaturePreviewControlSignature::MAX_BODY_BYTES)) {
                            throw new RuntimeException('Preview control response exceeds the size limit.');
                        }
                    },
                ])->post($this->signature->url());
            $stream = $response->toPsrResponse()->getBody();
            try {
                if ($response->status() !== 200 || strtolower(trim(explode(';', $response->header('Content-Type'))[0])) !== 'application/json'
                    || ! in_array(strtolower($response->header('Content-Encoding')), ['', 'identity'], true)) {
                    throw new RuntimeException('Invalid preview control response.');
                }
                $content = '';
                while (strlen($content) <= FeaturePreviewControlSignature::MAX_BODY_BYTES) {
                    $chunk = $stream->read(min(8192, FeaturePreviewControlSignature::MAX_BODY_BYTES + 1 - strlen($content)));
                    if ($chunk === '') {
                        break;
                    }
                    $content .= $chunk;
                }
                $responseHeaders = [];
                foreach ([FeaturePreviewControlSignature::TIMESTAMP, FeaturePreviewControlSignature::NONCE, FeaturePreviewControlSignature::SIGNATURE] as $name) {
                    $responseHeaders[$name] = $response->header($name);
                }
                $this->signature->validateResponse($content, $body, $headers, $responseHeaders, $response->status());
                $result = json_decode($content, true, 16, JSON_THROW_ON_ERROR);
                $this->assertResult($operation, $result);

                return $result;
            } finally {
                $stream->close();
            }
        } catch (Throwable) {
            throw new RuntimeException('The live preview control service is unavailable or returned an invalid response.');
        }
    }

    private function assertResult(string $operation, mixed $result): void
    {
        $valid = is_array($result);
        if ($operation === 'status') {
            $source = $result['source'] ?? null;
            $valid = $valid && count($result) === 3 && is_bool($result['schema_ready'] ?? null) && is_bool($result['enabled'] ?? null)
                && is_array($source) && count($source) === 3 && is_string($source['database'] ?? null)
                && preg_match('/\A[a-zA-Z0-9_]{1,64}\z/', $source['database']) === 1
                && is_string($source['server_fingerprint'] ?? null) && preg_match('/\A[a-f0-9]{64}\z/', $source['server_fingerprint']) === 1
                && is_string($source['app_key_fingerprint'] ?? null) && preg_match('/\A[a-f0-9]{64}\z/', $source['app_key_fingerprint']) === 1;
        } else {
            $valid = $valid && count($result) === 1 && is_bool($result['allowed'] ?? null);
        }
        if (! $valid) {
            throw new RuntimeException('Invalid preview control result.');
        }
    }
}
