<?php

namespace App\Services\MaterialsV2;

use App\Support\RemoteUrlGuard;
use DOMDocument;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LengthException;
use Throwable;

class MaterialV2LinkPreviewService
{
    private const MaxHtmlBytes = 524288;

    private const MaxRedirects = 3;

    public function __construct(
        private readonly RemoteUrlGuard $remoteUrlGuard,
    ) {}

    /**
     * @return array{
     *     reachable: bool,
     *     status: string,
     *     url: string,
     *     title: ?string,
     *     message: string,
     *     http_status: ?int
     * }
     */
    public function inspect(string $url): array
    {
        $currentUrl = $url;

        for ($redirectCount = 0; $redirectCount <= self::MaxRedirects; $redirectCount++) {
            try {
                $remoteUrl = $this->remoteUrlGuard->resolve($currentUrl);
            } catch (InvalidArgumentException) {
                $message = $redirectCount === 0
                    ? 'Die Zieladresse kann aus Sicherheitsgründen nicht geprüft werden.'
                    : 'Die Weiterleitung auf eine nicht erlaubte Zieladresse wurde blockiert.';

                return $this->warning($currentUrl, $message);
            }

            if (! defined('CURLOPT_RESOLVE')) {
                return $this->warning(
                    $remoteUrl['url'],
                    'Die sichere Zielprüfung ist auf diesem Server nicht verfügbar.',
                );
            }

            try {
                $response = $this->request($remoteUrl);
            } catch (Throwable) {
                return $this->warning(
                    $remoteUrl['url'],
                    'Die Zieladresse ist derzeit nicht erreichbar.',
                );
            }

            if ($response->redirect()) {
                $location = trim($response->header('Location'));

                if ($location === '') {
                    return $this->warning(
                        $remoteUrl['url'],
                        'Die Zieladresse antwortet mit einer ungültigen Weiterleitung.',
                        $response->status(),
                    );
                }

                if ($redirectCount === self::MaxRedirects) {
                    return $this->warning(
                        $remoteUrl['url'],
                        'Die Zieladresse leitet zu oft weiter.',
                        $response->status(),
                    );
                }

                try {
                    $currentUrl = $this->resolveRedirectUrl($remoteUrl['url'], $location);
                } catch (Throwable) {
                    return $this->warning(
                        $remoteUrl['url'],
                        'Die Zieladresse antwortet mit einer ungültigen Weiterleitung.',
                        $response->status(),
                    );
                }

                continue;
            }

            if (! $response->successful()) {
                return $this->warning(
                    $remoteUrl['url'],
                    "Die Zieladresse antwortet mit HTTP-Status {$response->status()}.",
                    $response->status(),
                );
            }

            $contentType = Str::lower($response->header('Content-Type'));
            if ($contentType !== '' && ! Str::contains($contentType, ['text/html', 'application/xhtml+xml'])) {
                return $this->warning(
                    $remoteUrl['url'],
                    'Die Zieladresse ist erreichbar, liefert aber keine Webseite.',
                    $response->status(),
                    true,
                );
            }

            try {
                $html = $this->readHtml($response);
            } catch (LengthException) {
                return $this->warning(
                    $remoteUrl['url'],
                    'Die Webseite ist für eine sichere Titelprüfung zu groß.',
                    $response->status(),
                    true,
                );
            }

            $title = $this->extractTitle($html);
            if ($title === null) {
                return $this->warning(
                    $remoteUrl['url'],
                    'Die Zieladresse ist erreichbar, enthält aber keinen Seitentitel.',
                    $response->status(),
                    true,
                );
            }

            return [
                'reachable' => true,
                'status' => 'success',
                'url' => $remoteUrl['url'],
                'title' => $title,
                'message' => 'Zieladresse erreichbar. Der Seitentitel wurde übernommen.',
                'http_status' => $response->status(),
            ];
        }

        return $this->warning($currentUrl, 'Die Zieladresse konnte nicht geprüft werden.');
    }

    /**
     * @param  array{url:string,host:string,port:int,ip:string}  $remoteUrl
     */
    private function request(array $remoteUrl): Response
    {
        $resolvedIp = str_contains($remoteUrl['ip'], ':')
            ? "[{$remoteUrl['ip']}]"
            : $remoteUrl['ip'];

        return Http::connectTimeout(3)
            ->timeout(8)
            ->withHeaders([
                'Accept' => 'text/html,application/xhtml+xml;q=0.9',
                'User-Agent' => 'Schooltool-Link-Pruefung/1.0',
            ])
            ->withOptions([
                'allow_redirects' => false,
                'stream' => true,
                'curl' => [
                    constant('CURLOPT_RESOLVE') => [
                        "{$remoteUrl['host']}:{$remoteUrl['port']}:{$resolvedIp}",
                    ],
                ],
            ])
            ->get($remoteUrl['url']);
    }

    private function resolveRedirectUrl(string $baseUrl, string $location): string
    {
        return (string) UriResolver::resolve(
            new Uri($baseUrl),
            new Uri($location),
        )->withFragment('');
    }

    private function readHtml(Response $response): string
    {
        $contentLength = (int) $response->header('Content-Length');
        if ($contentLength > self::MaxHtmlBytes) {
            throw new LengthException('HTML response exceeds preview limit.');
        }

        $body = $response->toPsrResponse()->getBody();
        $html = '';

        while (! $body->eof()) {
            $chunk = $body->read(8192);
            if ($chunk === '') {
                break;
            }

            $html .= $chunk;

            if (strlen($html) > self::MaxHtmlBytes) {
                throw new LengthException('HTML response exceeds preview limit.');
            }

            if (stripos($html, '</head>') !== false) {
                break;
            }
        }

        return $html;
    }

    private function extractTitle(string $html): ?string
    {
        if (trim($html) === '') {
            return null;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previousUseInternalErrors = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML(
                '<?xml encoding="UTF-8">'.$html,
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseInternalErrors);
        }

        if (! $loaded) {
            return null;
        }

        $title = Str::squish((string) $document->getElementsByTagName('title')->item(0)?->textContent);

        return $title === '' ? null : Str::limit($title, 255, '');
    }

    /**
     * @return array{
     *     reachable: bool,
     *     status: string,
     *     url: string,
     *     title: null,
     *     message: string,
     *     http_status: ?int
     * }
     */
    private function warning(
        string $url,
        string $message,
        ?int $httpStatus = null,
        bool $reachable = false,
    ): array {
        return [
            'reachable' => $reachable,
            'status' => 'warning',
            'url' => $url,
            'title' => null,
            'message' => $message,
            'http_status' => $httpStatus,
        ];
    }
}
