<?php

namespace App\Services;

use RuntimeException;
use Throwable;

class FeaturePreviewControlSignature
{
    public const PATH = '/api/feature-preview/control';

    public const MAX_BODY_BYTES = 65536;

    public const CLOCK_WINDOW = 30;

    public const TIMESTAMP = 'X-Schooltool-Preview-Timestamp';

    public const NONCE = 'X-Schooltool-Preview-Nonce';

    public const SIGNATURE = 'X-Schooltool-Preview-Signature';

    public function url(): string
    {
        $url = config('schooltool.preview.control_url');
        $parts = is_string($url) ? parse_url($url) : false;
        $liveHost = parse_url((string) config('schooltool.preview.live_url'), PHP_URL_HOST);
        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https'
            || ! is_string($parts['host'] ?? null) || preg_match('/\A[a-z0-9][a-z0-9.-]*\z/', $parts['host']) !== 1
            || ($parts['path'] ?? null) !== self::PATH || ($parts['port'] ?? 443) !== 443
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || (is_string($liveHost) && strtolower($liveHost) !== $parts['host'])) {
            throw new RuntimeException('The preview control URL must be the fixed HTTPS endpoint of the live application.');
        }

        return 'https://'.$parts['host'].self::PATH;
    }

    public function configurationIsSafe(): bool
    {
        try {
            $this->url();
            $this->key();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array<string, string> */
    public function requestHeaders(string $body): array
    {
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));

        return [
            self::TIMESTAMP => $timestamp,
            self::NONCE => $nonce,
            self::SIGNATURE => $this->mac($this->requestMessage($body, $timestamp, $nonce)),
        ];
    }

    /** @param array<string, string> $headers */
    public function validateRequest(string $body, array $headers): void
    {
        $this->validateHeaders($headers);
        if (! hash_equals($this->mac($this->requestMessage($body, $headers[self::TIMESTAMP], $headers[self::NONCE])), $headers[self::SIGNATURE])) {
            throw new RuntimeException('Invalid preview control signature.');
        }
    }

    /**
     * @param  array<string, string>  $requestHeaders
     * @return array<string, string>
     */
    public function responseHeaders(string $body, string $requestBody, array $requestHeaders, int $status = 200): array
    {
        $timestamp = (string) time();

        return [
            self::TIMESTAMP => $timestamp,
            self::NONCE => $requestHeaders[self::NONCE],
            self::SIGNATURE => $this->mac($this->responseMessage($body, $requestBody, $requestHeaders, $timestamp, $status)),
        ];
    }

    /**
     * @param  array<string, string>  $requestHeaders
     * @param  array<string, string>  $responseHeaders
     */
    public function validateResponse(string $body, string $requestBody, array $requestHeaders, array $responseHeaders, int $status): void
    {
        $this->validateHeaders($responseHeaders);
        if (! hash_equals($requestHeaders[self::NONCE], $responseHeaders[self::NONCE])
            || ! hash_equals($this->mac($this->responseMessage($body, $requestBody, $requestHeaders, $responseHeaders[self::TIMESTAMP], $status)), $responseHeaders[self::SIGNATURE])) {
            throw new RuntimeException('Invalid preview control response.');
        }
    }

    private function key(): string
    {
        $path = config('schooltool.preview.control_key_path');
        $resolved = is_string($path) && $path !== '' ? realpath($path) : false;
        $application = realpath(base_path());
        $normalize = static fn (string $value): string => PHP_OS_FAMILY === 'Windows'
            ? strtolower(str_replace('\\', '/', $value)) : $value;
        if (! is_string($path) || $resolved === false || $application === false
            || $normalize($path) !== $normalize($resolved) || ! is_file($path) || is_link($path)
            || str_starts_with($normalize($resolved), rtrim($normalize($application), '/').'/')
            || preg_match('~/public_html(?:/|$)~i', str_replace('\\', '/', $resolved)) === 1
            || (PHP_OS_FAMILY !== 'Windows' && (((fileperms($path) & 0077) !== 0) || ((fileperms(dirname($path)) & 0077) !== 0)))
            || (function_exists('posix_geteuid') && (fileowner($path) !== posix_geteuid() || fileowner(dirname($path)) !== posix_geteuid()))
            || (lstat($path)['nlink'] ?? 0) !== 1) {
            throw new RuntimeException('Configure a private preview control key outside the application.');
        }
        $key = file_get_contents($path);
        if (! is_string($key) || strlen($key) !== 32) {
            throw new RuntimeException('The preview control key must contain exactly 32 random bytes.');
        }
        $applicationKey = (string) config('app.key', '');
        $applicationKey = str_starts_with($applicationKey, 'base64:') ? base64_decode(substr($applicationKey, 7), true) : $applicationKey;
        if (is_string($applicationKey) && hash_equals($key, $applicationKey)) {
            throw new RuntimeException('The preview control key must be independent of the application key.');
        }

        return $key;
    }

    private function mac(string $message): string
    {
        return hash_hmac('sha256', $message, $this->key());
    }

    private function requestMessage(string $body, string $timestamp, string $nonce): string
    {
        $this->assertBodySize($body);

        return implode("\n", ['schooltool-preview-control-request-v1', 'POST', $this->url(), $timestamp, $nonce, hash('sha256', $body)]);
    }

    /** @param array<string, string> $requestHeaders */
    private function responseMessage(string $body, string $requestBody, array $requestHeaders, string $timestamp, int $status): string
    {
        $this->assertBodySize($body);

        return implode("\n", ['schooltool-preview-control-response-v1', $timestamp, $requestHeaders[self::NONCE],
            hash('sha256', $this->requestMessage($requestBody, $requestHeaders[self::TIMESTAMP], $requestHeaders[self::NONCE])),
            (string) $status, hash('sha256', $body)]);
    }

    /** @param array<string, string> $headers */
    private function validateHeaders(array $headers): void
    {
        if (preg_match('/\A[0-9]{10}\z/', $headers[self::TIMESTAMP] ?? '') !== 1
            || abs(time() - (int) $headers[self::TIMESTAMP]) > self::CLOCK_WINDOW
            || preg_match('/\A[a-f0-9]{32}\z/', $headers[self::NONCE] ?? '') !== 1
            || preg_match('/\A[a-f0-9]{64}\z/', $headers[self::SIGNATURE] ?? '') !== 1) {
            throw new RuntimeException('Invalid or expired preview control signature.');
        }
    }

    private function assertBodySize(string $body): void
    {
        if (strlen($body) > self::MAX_BODY_BYTES) {
            throw new RuntimeException('Preview control message exceeds the size limit.');
        }
    }
}
