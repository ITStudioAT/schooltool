<?php

namespace App\Services\Materials;

use App\Models\MaterialCardAttachment;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpPresentation\IOFactory as PresentationIOFactory;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Html as SpreadsheetHtmlWriter;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;

class MaterialAttachmentPreviewService
{
    private const TEXT_PREVIEW_MAX_BYTES = 1_048_576;

    /**
     * @var array<int, string>
     */
    private array $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'tif', 'tiff'];

    /**
     * @var array<int, string>
     */
    private array $audioExtensions = ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac'];

    /**
     * @var array<int, string>
     */
    private array $videoExtensions = ['mp4', 'webm', 'ogg', 'mov', 'm4v'];

    /**
     * @var array<int, string>
     */
    private array $spreadsheetExtensions = ['xls', 'xlsx', 'xlsm', 'xlt', 'xltx', 'xltm', 'ods', 'ots', 'csv', 'tsv', 'slk', 'xml'];

    /**
     * @var array<int, string>
     */
    private array $wordExtensions = ['doc', 'docx', 'docm', 'dot', 'dotx', 'odt', 'rtf'];

    /**
     * @var array<int, string>
     */
    private array $presentationExtensions = ['ppt', 'pptx', 'pptm', 'pps', 'ppsx', 'pot', 'potx', 'odp'];

    /**
     * @var array<int, string>
     */
    private array $textExtensions = ['txt', 'md', 'json', 'log', 'ini', 'yaml', 'yml', 'sql', 'xml'];

    /**
     * @var array<int, string>
     */
    private array $archiveExtensions = ['zip', 'rar', '7z', 'tar', 'gz', 'bz2'];

    /**
     * @param  array<int, string>  $diskCandidates
     */
    public function preview(MaterialCardAttachment $attachment, string $downloadUrl = '', array $diskCandidates = []): Response
    {
        $relativePath = (string) ($attachment->file_path ?? '');
        if ($relativePath === '') {
            abort(404, 'Datei nicht gefunden');
        }

        ['disk' => $disk, 'disk_name' => $diskName] = $this->resolveAttachmentDisk($relativePath, $diskCandidates);
        if (! $disk) {
            abort(404, 'Datei nicht gefunden');
        }

        ['path' => $absolutePath, 'is_temp' => $isTempFile] = $this->resolveLocalPath($disk, $relativePath, $diskName);

        $fileName = $this->displayName($attachment);
        $extension = $this->fileExtension($attachment);
        $mimeType = $this->normalizeMimeType((string) ($attachment->mime_type ?? $disk->mimeType($relativePath) ?? ''));
        $sizeBytes = $this->fileSizeBytes($disk, $relativePath);
        $maxRenderableBytes = (int) config('materials_preview.max_render_bytes', 31_457_280);
        $usesRenderer = $this->isSpreadsheet($extension, $mimeType)
            || $this->isWordDocument($extension, $mimeType)
            || $this->isPresentation($extension, $mimeType)
            || $this->isTextLike($extension, $mimeType);

        if ($usesRenderer && $maxRenderableBytes > 0 && $sizeBytes > $maxRenderableBytes) {
            if ($isTempFile) {
                @unlink($absolutePath);
            }

            return $this->messageResponse(
                $fileName,
                'Die Datei ist für eine Browser-Vorschau zu groß. Bitte direkt herunterladen.',
                $downloadUrl
            );
        }

        if ($this->isSpreadsheet($extension, $mimeType)) {
            try {
                $html = $this->renderSpreadsheetHtml($absolutePath, $extension);
                if ($isTempFile) {
                    @unlink($absolutePath);
                }

                return $this->htmlDocumentResponse($html, $fileName);
            } catch (\Throwable) {
                // Fallback auf nachgelagerte Strategien.
            }
        }

        if ($this->isWordDocument($extension, $mimeType)) {
            try {
                $html = $this->renderWordHtml($absolutePath, $extension);
                if ($isTempFile) {
                    @unlink($absolutePath);
                }

                return $this->htmlDocumentResponse($html, $fileName);
            } catch (\Throwable) {
                // Fallback auf nachgelagerte Strategien.
            }
        }

        if ($this->isPresentation($extension, $mimeType)) {
            try {
                $html = $this->renderPresentationHtml($absolutePath);
                if ($isTempFile) {
                    @unlink($absolutePath);
                }

                return $this->htmlDocumentResponse($html, $fileName);
            } catch (\Throwable) {
                // Fallback auf nachgelagerte Strategien.
            }
        }

        if ($this->isHtmlDocument($extension, $mimeType)) {
            $response = $this->htmlFilePreviewResponse($absolutePath, $fileName, $downloadUrl);
            if ($isTempFile) {
                @unlink($absolutePath);
            }

            return $response;
        }

        if ($this->isTextLike($extension, $mimeType)) {
            $response = $this->textPreviewResponse($absolutePath, $fileName, $downloadUrl);
            if ($isTempFile) {
                @unlink($absolutePath);
            }

            return $response;
        }

        if ($this->isInlineMedia($extension, $mimeType)) {
            $inlineMime = $this->inlineMimeType($extension, $mimeType);

            return $this->inlineFileResponse($absolutePath, $inlineMime, $fileName, $isTempFile);
        }

        $pdfPreviewPath = $this->tryLibreOfficePdfPreview($absolutePath);
        if ($isTempFile) {
            @unlink($absolutePath);
        }

        if (is_string($pdfPreviewPath) && $pdfPreviewPath !== '') {
            $previewName = $this->replaceExtensionWithPdf($fileName);

            return $this->inlineFileResponse($pdfPreviewPath, 'application/pdf', $previewName, true);
        }

        if (in_array($extension, $this->archiveExtensions, true)) {
            return $this->messageResponse(
                $fileName,
                'Für Archivdateien ist keine Browser-Vorschau möglich.',
                $downloadUrl
            );
        }

        return $this->messageResponse(
            $fileName,
            'Für dieses Dateiformat ist derzeit keine direkte Vorschau verfügbar.',
            $downloadUrl
        );
    }

    public function missingFilePreview(MaterialCardAttachment $attachment): Response
    {
        return $this->messageResponse(
            $this->displayName($attachment),
            'Die Datei wurde im Speicher nicht gefunden. Das Material ist vorhanden, aber der verknüpfte Anhang kann nicht geöffnet werden.'
        );
    }

    /**
     * @return array{path: string, is_temp: bool}
     */
    private function resolveLocalPath(Filesystem $disk, string $relativePath, string $diskName = ''): array
    {
        if ($diskName === 'local') {
            return ['path' => Storage::disk('local')->path($relativePath), 'is_temp' => false];
        }

        $content = $disk->get($relativePath);
        if ($content === null) {
            abort(404, 'Datei nicht gefunden');
        }

        $ext = pathinfo($relativePath, PATHINFO_EXTENSION);
        $tempPath = tempnam(sys_get_temp_dir(), 'st_prev_');
        if ($ext !== '' && is_string($tempPath)) {
            @rename($tempPath, $tempPath.'.'.$ext);
            $tempPath .= '.'.$ext;
        }

        file_put_contents($tempPath, $content);

        return ['path' => $tempPath, 'is_temp' => true];
    }

    /**
     * @return array{disk:Filesystem|null,disk_name:string}
     */
    /**
     * @param  array<int, string>  $candidateDiskNames
     * @return array{disk:Filesystem|null,disk_name:string}
     */
    private function resolveAttachmentDisk(string $relativePath, array $candidateDiskNames = []): array
    {
        $path = trim($relativePath);
        if ($path === '') {
            return ['disk' => null, 'disk_name' => ''];
        }

        $candidates = $candidateDiskNames !== []
            ? array_values(array_filter(array_unique($candidateDiskNames), static fn (string $diskName): bool => $diskName !== ''))
            : array_values(array_filter(array_unique([
                (string) config('filesystems.default'),
                'local',
                'public',
            ]), static fn (string $diskName): bool => $diskName !== ''));

        foreach ($candidates as $diskName) {
            $disk = Storage::disk($diskName);
            if ($disk->exists($path)) {
                return ['disk' => $disk, 'disk_name' => $diskName];
            }
        }

        foreach ($this->fallbackDisks() as $disk) {
            if ($disk->exists($path)) {
                return ['disk' => $disk, 'disk_name' => 'fallback-local'];
            }
        }

        return ['disk' => null, 'disk_name' => ''];
    }

    /**
     * @return array<int, Filesystem>
     */
    private function fallbackDisks(): array
    {
        $roots = [
            storage_path('app/private'),
            storage_path('app'),
            storage_path('app/public'),
        ];

        return array_map(
            static fn (string $root): Filesystem => Storage::build([
                'driver' => 'local',
                'root' => $root,
                'throw' => false,
            ]),
            array_values(array_unique($roots))
        );
    }

    private function renderSpreadsheetHtml(string $absolutePath, string $extension): string
    {
        $readerType = $this->spreadsheetReaderType($extension);
        if ($readerType === null) {
            throw new \RuntimeException('Unbekannter Tabellen-Typ.');
        }

        $reader = SpreadsheetIOFactory::createReader($readerType);
        if ($readerType === 'Csv') {
            if (method_exists($reader, 'setDelimiter') && $extension === 'tsv') {
                $reader->setDelimiter("\t");
            }
            if (method_exists($reader, 'setEnclosure')) {
                $reader->setEnclosure('"');
            }
        }

        $spreadsheet = $reader->load($absolutePath);

        try {
            $writer = SpreadsheetIOFactory::createWriter($spreadsheet, 'Html');
            if ($writer instanceof SpreadsheetHtmlWriter) {
                $writer->setEmbedImages(true);
                $writer->setGenerateSheetNavigationBlock(true);
            }

            return $this->captureOutput(fn () => $writer->save('php://output'));
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    private function renderWordHtml(string $absolutePath, string $extension): string
    {
        $candidates = $this->wordReaderCandidates($extension);
        $lastError = null;

        foreach ($candidates as $readerName) {
            try {
                $phpWord = WordIOFactory::load($absolutePath, $readerName);
                $writer = WordIOFactory::createWriter($phpWord, 'HTML');

                return $this->captureOutput(fn () => $writer->save('php://output'));
            } catch (\Throwable $error) {
                $lastError = $error;
            }
        }

        if ($lastError instanceof \Throwable) {
            throw $lastError;
        }

        throw new \RuntimeException('Word-Vorschau konnte nicht erzeugt werden.');
    }

    private function renderPresentationHtml(string $absolutePath): string
    {
        $presentation = PresentationIOFactory::load($absolutePath);
        $writer = PresentationIOFactory::createWriter($presentation, 'HTML');

        return $this->captureOutput(fn () => $writer->save('php://output'));
    }

    private function captureOutput(callable $callback): string
    {
        $startLevel = ob_get_level();
        ob_start();

        try {
            $callback();
            $output = ob_get_clean();

            return is_string($output) ? $output : '';
        } catch (\Throwable $error) {
            while (ob_get_level() > $startLevel) {
                ob_end_clean();
            }

            throw $error;
        }
    }

    private function inlineFileResponse(
        string $absolutePath,
        string $mimeType,
        string $fileName,
        bool $deleteAfterSend = false
    ): BinaryFileResponse {
        $response = response()->file($absolutePath, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);

        $response->setContentDisposition('inline', $fileName);

        if ($deleteAfterSend) {
            $response->deleteFileAfterSend(true);
        }

        return $response;
    }

    private function textPreviewResponse(string $absolutePath, string $fileName, string $downloadUrl = ''): Response
    {
        $sizeBytes = @filesize($absolutePath);
        $readBytes = is_int($sizeBytes) && $sizeBytes > 0
            ? min($sizeBytes, self::TEXT_PREVIEW_MAX_BYTES)
            : self::TEXT_PREVIEW_MAX_BYTES;

        $raw = @file_get_contents($absolutePath, false, null, 0, $readBytes);
        $content = is_string($raw) ? $raw : '';

        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8,ISO-8859-1,Windows-1252');
        }

        $escaped = nl2br($this->escapeHtml($content), false);
        $truncated = is_int($sizeBytes) && $sizeBytes > self::TEXT_PREVIEW_MAX_BYTES;
        $hint = $truncated
            ? '<p class="preview-hint">Vorschau gekürzt. Bitte Datei herunterladen, um den vollständigen Inhalt zu sehen.</p>'
            : '';

        $body = <<<HTML
            <div class="text-preview-wrap">
                {$hint}
                <pre class="text-preview">{$escaped}</pre>
            </div>
        HTML;

        return $this->shellHtmlResponse($fileName, $body, $downloadUrl);
    }

    private function messageResponse(string $fileName, string $message, string $downloadUrl = ''): Response
    {
        $messageHtml = '<p>'.$this->escapeHtml($message).'</p>';

        return $this->shellHtmlResponse($fileName, $messageHtml, $downloadUrl);
    }

    private function htmlFilePreviewResponse(string $absolutePath, string $fileName, string $downloadUrl = ''): Response
    {
        $raw = @file_get_contents($absolutePath);
        $content = is_string($raw) ? $raw : '';

        if ($content === '') {
            return $this->messageResponse(
                $fileName,
                'HTML-Vorschau konnte nicht geladen werden.',
                $downloadUrl
            );
        }

        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8,ISO-8859-1,Windows-1252');
        }

        $normalized = $this->normalizeGeneratedHtml($content, $fileName);
        $bodyHtml = $this->extractHtmlBody($normalized);
        if (trim($bodyHtml) === '') {
            $bodyHtml = '<p>HTML-Inhalt konnte nicht dargestellt werden.</p>';
        }

        return $this->shellHtmlResponse(
            $fileName,
            '<div class="rich-html-preview">'.$bodyHtml.'</div>',
            $downloadUrl
        );
    }

    private function shellHtmlResponse(string $fileName, string $bodyHtml, string $downloadUrl = ''): Response
    {
        $safeTitle = $this->escapeHtml($fileName);
        $downloadButton = '';
        if ($downloadUrl !== '') {
            $safeDownloadUrl = $this->escapeHtml($downloadUrl);
            $downloadButton = <<<HTML
                <a class="download-btn" href="{$safeDownloadUrl}">Datei herunterladen</a>
            HTML;
        }

        $document = <<<HTML
            <!doctype html>
            <html lang="de">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title>Vorschau: {$safeTitle}</title>
                <style>
                    body { margin: 0; font-family: Arial, sans-serif; background: #f5f7fb; color: #1a2b3b; }
                    .topbar { padding: 10px 14px; background: #1f5fbf; color: #fff; font-size: 14px; display: flex; gap: 12px; align-items: center; justify-content: space-between; }
                    .title { font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
                    .panel { margin: 16px; padding: 16px; background: #fff; border-radius: 10px; border: 1px solid #d9e1f3; }
                    .download-btn { display: inline-block; margin-top: 10px; text-decoration: none; background: #1f5fbf; color: #fff; padding: 10px 14px; border-radius: 8px; font-size: 14px; }
                    .preview-hint { margin: 0 0 10px 0; color: #8a5a00; font-size: 14px; }
                    .text-preview-wrap { max-width: 100%; }
                    .text-preview { margin: 0; white-space: pre-wrap; word-break: break-word; font-family: Consolas, Menlo, monospace; font-size: 14px; line-height: 1.5; }
                    .rich-html-preview { max-width: 100%; line-height: 1.6; }
                    .rich-html-preview p { margin: 0 0 0.65rem 0; }
                    .rich-html-preview ul, .rich-html-preview ol { margin: 0.45rem 0 0.75rem 0; padding-inline-start: 1.4rem; }
                    .rich-html-preview li { margin: 0.2rem 0; }
                    .rich-html-preview blockquote {
                        margin: 0.75rem 0;
                        padding: 0.5rem 0.75rem;
                        border-left: 3px solid #fd802e;
                        background: rgba(253, 128, 46, 0.10);
                        border-radius: 0 6px 6px 0;
                    }
                    .rich-html-preview pre {
                        background: #f5f7fb;
                        border: 1px solid #d9e1f3;
                        border-radius: 8px;
                        padding: 10px 12px;
                        overflow: auto;
                    }
                    .rich-html-preview code {
                        background: #f5f7fb;
                        border: 1px solid #d9e1f3;
                        border-radius: 4px;
                        padding: 1px 4px;
                    }
                </style>
            </head>
            <body>
                <div class="topbar">
                    <div class="title">{$safeTitle}</div>
                </div>
                <div class="panel">
                    {$bodyHtml}
                    {$downloadButton}
                </div>
            </body>
            </html>
        HTML;

        return response($document, 200, $this->htmlHeaders());
    }

    private function htmlDocumentResponse(string $html, string $fileName): Response
    {
        $document = $this->normalizeGeneratedHtml($html, $fileName);

        return response($document, 200, $this->htmlHeaders());
    }

    /**
     * @return array<string, string>
     */
    private function htmlHeaders(): array
    {
        return [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src data: blob:; media-src data: blob:; style-src 'unsafe-inline'; font-src data:; frame-ancestors 'self'; base-uri 'none'; form-action 'none'",
        ];
    }

    private function normalizeGeneratedHtml(string $html, string $fileName): string
    {
        $document = trim($html);
        if ($document === '') {
            return $this->shellHtmlResponse(
                $fileName,
                '<p>Für diese Datei konnte keine Vorschau erzeugt werden.</p>'
            )->getContent() ?: '';
        }

        $document = $this->sanitizeHtml($document);

        if (! preg_match('/<html/i', $document)) {
            $safeTitle = $this->escapeHtml($fileName);
            $styles = $this->defaultRichTextPreviewStyles();
            $document = <<<HTML
                <!doctype html>
                <html lang="de">
                <head>
                    <meta charset="utf-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1">
                    <title>Vorschau: {$safeTitle}</title>
                    {$styles}
                </head>
                <body>{$document}</body>
                </html>
            HTML;
        } elseif (! preg_match('/<meta[^>]+charset=/i', $document)) {
            $document = preg_replace('/<head([^>]*)>/i', '<head$1><meta charset="utf-8">', $document, 1) ?? $document;
        }

        $document = $this->injectDefaultRichTextStyles($document);

        return $document;
    }

    private function sanitizeHtml(string $html): string
    {
        $clean = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $clean = preg_replace('/\son\w+\s*=\s*"[^"]*"/i', '', $clean) ?? $clean;
        $clean = preg_replace("/\son\w+\s*=\s*'[^']*'/i", '', $clean) ?? $clean;
        $clean = str_ireplace('javascript:', '', $clean);

        return $clean;
    }

    private function extractHtmlBody(string $document): string
    {
        $match = [];
        if (preg_match('/<body[^>]*>([\s\S]*)<\/body>/i', $document, $match)) {
            return trim((string) ($match[1] ?? ''));
        }

        return trim($document);
    }

    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function injectDefaultRichTextStyles(string $document): string
    {
        if (preg_match('/<style[^>]+id="materials-richtext-preview-style"/i', $document)) {
            return $document;
        }

        $styles = $this->defaultRichTextPreviewStyles();
        if (preg_match('/<\/head>/i', $document)) {
            return preg_replace('/<\/head>/i', $styles.'</head>', $document, 1) ?? $document;
        }

        if (preg_match('/<head[^>]*>/i', $document)) {
            return preg_replace('/<head([^>]*)>/i', '<head$1>'.$styles, $document, 1) ?? $document;
        }

        return $document;
    }

    private function defaultRichTextPreviewStyles(): string
    {
        return <<<'HTML'
            <style id="materials-richtext-preview-style">
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.55;
                    color: #1a2b3b;
                    margin: 14px;
                }
                p { margin: 0 0 0.65rem 0; }
                ul, ol {
                    margin: 0.45rem 0 0.7rem 0;
                    padding-inline-start: 1.35rem;
                }
                li { margin: 0.2rem 0; }
                blockquote {
                    margin: 0.75rem 0;
                    padding: 0.5rem 0.75rem;
                    border-left: 3px solid #fd802e;
                    background: rgba(253, 128, 46, 0.10);
                    border-radius: 0 6px 6px 0;
                }
                pre {
                    background: #f5f7fb;
                    border: 1px solid #d9e1f3;
                    border-radius: 8px;
                    padding: 10px 12px;
                    overflow: auto;
                }
                code {
                    background: #f5f7fb;
                    border: 1px solid #d9e1f3;
                    border-radius: 4px;
                    padding: 1px 4px;
                }
            </style>
        HTML;
    }

    private function displayName(MaterialCardAttachment $attachment): string
    {
        $name = trim((string) ($attachment->name ?? ''));
        if ($name !== '') {
            return $name;
        }

        $path = trim((string) ($attachment->file_path ?? ''));
        if ($path !== '') {
            return basename($path);
        }

        return 'Datei';
    }

    private function fileExtension(MaterialCardAttachment $attachment): string
    {
        $candidates = [
            trim((string) ($attachment->name ?? '')),
            trim((string) ($attachment->file_path ?? '')),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }

            $clean = preg_split('/[?#]/', $candidate)[0] ?? $candidate;
            $extension = strtolower(pathinfo($clean, PATHINFO_EXTENSION));
            if ($extension !== '') {
                return $extension;
            }
        }

        $mime = $this->normalizeMimeType((string) ($attachment->mime_type ?? ''));

        return match ($mime) {
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            default => '',
        };
    }

    private function normalizeMimeType(string $value): string
    {
        return strtolower(trim($value));
    }

    private function isInlineMedia(string $extension, string $mimeType): bool
    {
        if ($mimeType === 'application/pdf') {
            return true;
        }

        if (str_starts_with($mimeType, 'image/')) {
            return true;
        }

        if (str_starts_with($mimeType, 'audio/')) {
            return true;
        }

        if (str_starts_with($mimeType, 'video/')) {
            return true;
        }

        return in_array($extension, $this->imageExtensions, true)
            || in_array($extension, $this->audioExtensions, true)
            || in_array($extension, $this->videoExtensions, true)
            || $extension === 'pdf';
    }

    private function inlineMimeType(string $extension, string $mimeType): string
    {
        if ($mimeType !== '' && $mimeType !== 'application/octet-stream') {
            return $mimeType;
        }

        return match ($extension) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'bmp' => 'image/bmp',
            'mp4', 'm4v' => 'video/mp4',
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'm4a' => 'audio/mp4',
            default => 'application/octet-stream',
        };
    }

    private function isSpreadsheet(string $extension, string $mimeType): bool
    {
        if (in_array($extension, $this->spreadsheetExtensions, true)) {
            return true;
        }

        return in_array($mimeType, [
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
            'application/vnd.ms-excel.sheet.macroenabled.12',
            'application/vnd.oasis.opendocument.spreadsheet',
            'text/csv',
            'application/csv',
            'text/tab-separated-values',
        ], true);
    }

    private function spreadsheetReaderType(string $extension): ?string
    {
        return match ($extension) {
            'xlsx', 'xlsm', 'xltx', 'xltm' => 'Xlsx',
            'xls', 'xlt' => 'Xls',
            'ods', 'ots' => 'Ods',
            'csv', 'tsv' => 'Csv',
            'slk' => 'Slk',
            'xml' => 'Xml',
            default => null,
        };
    }

    private function isWordDocument(string $extension, string $mimeType): bool
    {
        if (in_array($extension, $this->wordExtensions, true)) {
            return true;
        }

        return in_array($mimeType, [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
            'application/vnd.ms-word.document.macroenabled.12',
            'application/vnd.oasis.opendocument.text',
            'application/rtf',
            'text/rtf',
        ], true);
    }

    private function isPresentation(string $extension, string $mimeType): bool
    {
        if (in_array($extension, $this->presentationExtensions, true)) {
            return true;
        }

        return in_array($mimeType, [
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
            'application/vnd.openxmlformats-officedocument.presentationml.template',
            'application/vnd.ms-powerpoint.presentation.macroenabled.12',
            'application/vnd.oasis.opendocument.presentation',
        ], true);
    }

    private function isTextLike(string $extension, string $mimeType): bool
    {
        if (in_array($extension, $this->textExtensions, true)) {
            return true;
        }

        if (str_starts_with($mimeType, 'text/')) {
            return true;
        }

        return in_array($mimeType, [
            'application/json',
            'application/xml',
        ], true);
    }

    private function isHtmlDocument(string $extension, string $mimeType): bool
    {
        if (in_array($extension, ['html', 'htm'], true)) {
            return true;
        }

        return $mimeType === 'text/html' || $mimeType === 'application/xhtml+xml';
    }

    /**
     * @return array<int, string>
     */
    private function wordReaderCandidates(string $extension): array
    {
        $primary = match ($extension) {
            'doc' => 'MsDoc',
            'docx', 'docm', 'dot', 'dotx' => 'Word2007',
            'odt' => 'ODText',
            'rtf' => 'RTF',
            default => 'Word2007',
        };

        $candidates = [$primary, 'Word2007', 'MsDoc', 'ODText', 'RTF'];

        return array_values(array_unique($candidates));
    }

    private function tryLibreOfficePdfPreview(string $absolutePath): ?string
    {
        if (! (bool) config('materials_preview.libreoffice.enabled', true)) {
            return null;
        }

        $binary = $this->resolveLibreOfficeBinary();
        if ($binary === null) {
            return null;
        }

        $tempBaseDir = storage_path('app/private/materials/preview-temp');
        if (! is_dir($tempBaseDir) && ! @mkdir($tempBaseDir, 0775, true) && ! is_dir($tempBaseDir)) {
            return null;
        }

        $workDir = $tempBaseDir.DIRECTORY_SEPARATOR.uniqid('lo_', true);
        if (! @mkdir($workDir, 0775, true) && ! is_dir($workDir)) {
            return null;
        }

        try {
            $process = new Process([
                $binary,
                '--headless',
                '--nologo',
                '--nodefault',
                '--nolockcheck',
                '--nofirststartwizard',
                '--convert-to',
                'pdf',
                '--outdir',
                $workDir,
                $absolutePath,
            ]);

            $timeout = (int) config('materials_preview.libreoffice.timeout_seconds', 60);
            if ($timeout > 0) {
                $process->setTimeout($timeout);
            }

            $process->run();
            if (! $process->isSuccessful()) {
                return null;
            }

            $expected = $workDir.DIRECTORY_SEPARATOR.pathinfo($absolutePath, PATHINFO_FILENAME).'.pdf';
            $pdfPath = is_file($expected)
                ? $expected
                : ($this->firstPdfInDirectory($workDir) ?? '');

            if ($pdfPath === '' || ! is_file($pdfPath)) {
                return null;
            }

            $tempPdf = tempnam($tempBaseDir, 'preview_');
            if (! is_string($tempPdf) || $tempPdf === '') {
                return null;
            }

            if (! @copy($pdfPath, $tempPdf)) {
                @unlink($tempPdf);

                return null;
            }

            return $tempPdf;
        } catch (\Throwable) {
            return null;
        } finally {
            $this->deleteDirectory($workDir);
        }
    }

    private function resolveLibreOfficeBinary(): ?string
    {
        $configured = trim((string) config('materials_preview.libreoffice.binary', ''));
        if ($configured === '') {
            return null;
        }

        if (str_contains($configured, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $configured) === 1) {
            return is_file($configured) ? $configured : null;
        }

        $probe = PHP_OS_FAMILY === 'Windows'
            ? new Process(['where', $configured])
            : new Process(['which', $configured]);
        $probe->setTimeout(4);
        $probe->run();

        if (! $probe->isSuccessful()) {
            return null;
        }

        $lines = preg_split('/\R/', trim($probe->getOutput())) ?: [];
        $line = trim((string) ($lines[0] ?? ''));

        return $line !== '' ? $line : $configured;
    }

    private function firstPdfInDirectory(string $directory): ?string
    {
        $files = @glob($directory.DIRECTORY_SEPARATOR.'*.pdf');
        if (! is_array($files) || count($files) === 0) {
            return null;
        }

        $first = reset($files);

        return is_string($first) ? $first : null;
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = @scandir($directory);
        if (! is_array($items)) {
            @rmdir($directory);

            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }

    private function replaceExtensionWithPdf(string $fileName): string
    {
        $clean = trim($fileName);
        if ($clean === '') {
            return 'Vorschau.pdf';
        }

        $withoutExt = pathinfo($clean, PATHINFO_FILENAME);

        return ($withoutExt !== '' ? $withoutExt : 'Vorschau').'.pdf';
    }

    private function fileSizeBytes($disk, string $relativePath): int
    {
        try {
            $size = (int) $disk->size($relativePath);

            return $size > 0 ? $size : 0;
        } catch (\Throwable) {
            return 0;
        }
    }
}
