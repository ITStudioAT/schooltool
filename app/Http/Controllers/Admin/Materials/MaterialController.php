<?php

namespace App\Http\Controllers\Admin\Materials;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Materials\MaterialCardAttachmentTextUpdateRequest;
use App\Http\Requests\Admin\Materials\MaterialCardAttachmentUpdateRequest;
use App\Http\Requests\Admin\Materials\MaterialCardFileAttachmentStoreRequest;
use App\Http\Requests\Admin\Materials\MaterialCardIndexRequest;
use App\Http\Requests\Admin\Materials\MaterialCardLinkAttachmentStoreRequest;
use App\Http\Requests\Admin\Materials\MaterialCardQuickStoreRequest;
use App\Http\Requests\Admin\Materials\MaterialCardRemoteImageAttachmentStoreRequest;
use App\Http\Requests\Admin\Materials\MaterialCardStoreRequest;
use App\Http\Requests\Admin\Materials\MaterialCardTempAttachmentStoreRequest;
use App\Http\Requests\Admin\Materials\MaterialCardUpdateRequest;
use App\Http\Resources\Admin\Materials\MaterialCardAttachmentResource;
use App\Http\Resources\Admin\Materials\MaterialCardResource;
use App\Http\Resources\Admin\PaginateResource;
use App\Models\MaterialCard;
use App\Models\MaterialCardAttachment;
use App\Services\Materials\MaterialAttachmentPreviewService;
use App\Services\Materials\MaterialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class MaterialController extends Controller
{
    public function config(Request $request, MaterialService $service)
    {
        $authUser = $this->authorizeForMaterials();

        return response()->json($service->config($authUser), 200);
    }

    public function index(MaterialCardIndexRequest $request, MaterialService $service)
    {
        $authUser = $this->authorizeForMaterials();
        $validated = $request->validated();

        $cards = $service->listForUser($authUser, $validated);

        return response()->json([
            'data' => MaterialCardResource::collection($cards),
            'meta' => new PaginateResource($cards),
        ], 200);
    }

    public function store(MaterialCardStoreRequest $request, MaterialService $service)
    {
        $authUser = $this->authorizeForMaterials();
        $validated = $request->validated()['data'];

        $card = $service->createCard($authUser, $validated);

        return response()->json(new MaterialCardResource($this->loadCardForResponse($card)), 200);
    }

    public function quickStore(MaterialCardQuickStoreRequest $request, MaterialService $service)
    {
        $authUser = $this->authorizeForMaterials();
        $validated = $request->validated()['data'];

        $card = $service->createCard($authUser, $validated);

        return response()->json(new MaterialCardResource($this->loadCardForResponse($card)), 200);
    }

    public function show(MaterialCard $material_card)
    {
        $authUser = $this->authorizeForMaterials();
        $this->assertIsOwner($authUser->id, $material_card->user_id);

        return response()->json(new MaterialCardResource($this->loadCardForResponse($material_card)), 200);
    }

    public function update(MaterialCardUpdateRequest $request, MaterialCard $material_card, MaterialService $service)
    {
        $authUser = $this->authorizeForMaterials();
        $this->assertIsOwner($authUser->id, $material_card->user_id);
        $validated = $request->validated()['data'];

        $card = $service->updateCard($material_card, $validated, $authUser);

        return response()->json(new MaterialCardResource($this->loadCardForResponse($card)), 200);
    }

    public function destroy(MaterialCard $material_card, MaterialService $service)
    {
        $authUser = $this->authorizeForMaterials();
        $this->assertIsOwner($authUser->id, $material_card->user_id);

        $service->deleteCard($material_card);

        return response()->noContent();
    }

    public function storeLinkAttachment(
        MaterialCardLinkAttachmentStoreRequest $request,
        MaterialCard $material_card,
        MaterialService $service
    ) {
        $authUser = $this->authorizeForMaterials();
        $this->assertIsOwner($authUser->id, $material_card->user_id);
        $validated = $request->validated()['data'];

        $attachment = $service->addLinkAttachment(
            $material_card,
            $validated['url'],
            $validated['name'] ?? null
        );

        return response()->json(new MaterialCardAttachmentResource($attachment), 200);
    }

    public function storeRemoteImageAttachment(
        MaterialCardRemoteImageAttachmentStoreRequest $request,
        MaterialCard $material_card,
        MaterialService $service
    ) {
        $authUser = $this->authorizeForMaterials();
        $this->assertIsOwner($authUser->id, $material_card->user_id);
        $validated = $request->validated()['data'];

        $attachment = $service->addImageAttachmentFromUrl(
            $material_card,
            (string) ($validated['url'] ?? ''),
            isset($validated['name']) ? (string) $validated['name'] : null
        );

        return response()->json(new MaterialCardAttachmentResource($attachment), 200);
    }

    public function storeFileAttachment(
        MaterialCardFileAttachmentStoreRequest $request,
        MaterialCard $material_card,
        MaterialService $service
    ) {
        $authUser = $this->authorizeForMaterials();
        $this->assertIsOwner($authUser->id, $material_card->user_id);
        $validated = $request->validated();

        $attachment = $service->addFileAttachment(
            $material_card,
            $validated['file'],
            $validated['name'] ?? null
        );

        return response()->json(new MaterialCardAttachmentResource($attachment), 200);
    }

    public function storeTempFileAttachment(
        MaterialCardTempAttachmentStoreRequest $request,
        MaterialCard $material_card,
        MaterialService $service
    ) {
        $authUser = $this->authorizeForMaterials();
        $this->assertIsOwner($authUser->id, $material_card->user_id);
        $validated = $request->validated()['data'];

        $attachment = $service->addFileAttachmentFromTempUpload(
            $authUser,
            $material_card,
            (string) ($validated['upload_id'] ?? ''),
            isset($validated['name']) ? (string) $validated['name'] : null
        );

        return response()->json(new MaterialCardAttachmentResource($attachment), 200);
    }

    public function destroyAttachment(MaterialCardAttachment $material_card_attachment, MaterialService $service)
    {
        $authUser = $this->authorizeForMaterials();
        $material_card_attachment->loadMissing('materialCard');
        $this->assertIsOwner($authUser->id, (int) $material_card_attachment->materialCard->user_id);

        $service->deleteAttachment($material_card_attachment);

        return response()->noContent();
    }

    public function updateAttachment(
        MaterialCardAttachmentUpdateRequest $request,
        MaterialCardAttachment $material_card_attachment,
        MaterialService $service
    ) {
        $authUser = $this->authorizeForMaterials();
        $material_card_attachment->loadMissing('materialCard');
        $this->assertIsOwner($authUser->id, (int) $material_card_attachment->materialCard->user_id);
        $validated = $request->validated()['data'];

        $attachment = $service->updateAttachmentName($material_card_attachment, $validated['name']);

        return response()->json(new MaterialCardAttachmentResource($attachment), 200);
    }

    public function textAttachmentContent(MaterialCardAttachment $material_card_attachment, MaterialService $service)
    {
        $authUser = $this->authorizeForMaterials();
        $material_card_attachment->loadMissing('materialCard');
        $this->assertIsOwner($authUser->id, (int) $material_card_attachment->materialCard->user_id);

        $contentHtml = $service->readEditableTextAttachmentContent($material_card_attachment);

        return response()->json([
            'data' => [
                'id' => (int) $material_card_attachment->id,
                'name' => (string) ($material_card_attachment->name ?? ''),
                'content_html' => $contentHtml,
            ],
        ], 200);
    }

    public function updateTextAttachmentContent(
        MaterialCardAttachmentTextUpdateRequest $request,
        MaterialCardAttachment $material_card_attachment,
        MaterialService $service
    ) {
        $authUser = $this->authorizeForMaterials();
        $material_card_attachment->loadMissing('materialCard');
        $this->assertIsOwner($authUser->id, (int) $material_card_attachment->materialCard->user_id);
        $validated = $request->validated()['data'];

        $attachment = $service->updateEditableTextAttachmentContent(
            $material_card_attachment,
            (string) ($validated['content_html'] ?? ''),
            isset($validated['name']) ? (string) $validated['name'] : null
        );

        return response()->json(new MaterialCardAttachmentResource($attachment), 200);
    }

    public function downloadAttachment(MaterialCardAttachment $material_card_attachment)
    {
        $authUser = $this->authorizeForMaterials();
        $material_card_attachment->loadMissing('materialCard');
        $this->assertIsOwner($authUser->id, (int) $material_card_attachment->materialCard->user_id);

        if ($material_card_attachment->attachment_type !== MaterialCardAttachment::TYPE_FILE || ! $material_card_attachment->file_path) {
            abort(404, 'Datei nicht gefunden');
        }

        $disk = Storage::disk(config('filesystems.default'));
        $relativePath = (string) $material_card_attachment->file_path;

        if (! $disk->exists($relativePath)) {
            abort(404, 'Datei nicht gefunden');
        }

        $name = $this->safeAttachmentDownloadName(
            $material_card_attachment->name ?: basename($relativePath)
        );

        if ($this->isHtmlAttachment($material_card_attachment)) {
            $rawHtml = (string) $disk->get($relativePath);
            $styledHtml = $this->ensureRichTextStylesForHtmlDownload($rawHtml, $name);

            return response()->streamDownload(
                static function () use ($styledHtml): void {
                    echo $styledHtml;
                },
                $name,
                [
                    'Content-Type' => 'text/html; charset=UTF-8',
                    'Cache-Control' => 'private, no-store, max-age=0',
                    'X-Content-Type-Options' => 'nosniff',
                ]
            );
        }

        $stream = $disk->readStream($relativePath);
        if (! is_resource($stream)) {
            abort(404, 'Datei nicht gefunden');
        }

        $contentType = $this->attachmentDownloadContentType($material_card_attachment, $disk, $relativePath);

        return response()->streamDownload(
            static function () use ($stream): void {
                try {
                    while (! feof($stream)) {
                        $chunk = fread($stream, 8192);
                        if ($chunk === false) {
                            break;
                        }

                        echo $chunk;
                    }
                } finally {
                    fclose($stream);
                }
            },
            $name,
            [
                'Content-Type' => $contentType,
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    public function downloadAttachmentDocx(MaterialCardAttachment $material_card_attachment)
    {
        $authUser = $this->authorizeForMaterials();
        $material_card_attachment->loadMissing('materialCard');
        $this->assertIsOwner($authUser->id, (int) $material_card_attachment->materialCard->user_id);

        if (! $this->isHtmlAttachment($material_card_attachment)) {
            abort(422, 'DOCX-Export ist nur für Text/HTML-Anhänge verfügbar.');
        }

        $relativePath = (string) ($material_card_attachment->file_path ?? '');
        if ($relativePath === '' || ! Storage::exists($relativePath)) {
            abort(404, 'Datei nicht gefunden');
        }

        $name = $material_card_attachment->name ?: basename($relativePath);
        $rawHtml = (string) Storage::get($relativePath);
        $styledHtml = $this->ensureRichTextStylesForHtmlDownload($rawHtml, $name);
        $bodyHtml = $this->extractHtmlBody($styledHtml);
        $normalizedBodyHtml = $this->normalizeHtmlFragmentForDocx($bodyHtml);

        $phpWord = $this->newDocxDocument();
        $section = $phpWord->addSection();

        try {
            \PhpOffice\PhpWord\Shared\Html::addHtml($section, $normalizedBodyHtml, false, false);
        } catch (\Throwable $error) {
            Log::warning('DOCX export fallback to plain text.', [
                'attachment_id' => (int) $material_card_attachment->id,
                'error' => $error->getMessage(),
            ]);

            $phpWord = $this->newDocxDocument();
            $section = $phpWord->addSection();
            $fallbackText = $this->plainTextFromHtmlForDocx($bodyHtml);
            $this->addPlainTextToDocxSection($section, $fallbackText);
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'materials-docx-');
        if (! is_string($tempPath) || $tempPath === '') {
            abort(500, 'Temporäre Datei konnte nicht erstellt werden.');
        }

        @unlink($tempPath);
        $tempDocxPath = $tempPath . '.docx';

        try {
            $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save($tempDocxPath);
        } catch (\Throwable $error) {
            @unlink($tempDocxPath);
            abort(422, 'DOCX-Export konnte nicht erstellt werden.');
        }

        return response()
            ->download(
                $tempDocxPath,
                $this->docxDownloadName($name),
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'Cache-Control' => 'private, no-store, max-age=0',
                    'X-Content-Type-Options' => 'nosniff',
                ]
            )
            ->deleteFileAfterSend(true);
    }

    public function previewAttachment(
        MaterialCardAttachment $material_card_attachment,
        MaterialAttachmentPreviewService $previewService
    ) {
        $authUser = $this->authorizeForMaterials();
        $material_card_attachment->loadMissing('materialCard');
        $this->assertIsOwner($authUser->id, (int) $material_card_attachment->materialCard->user_id);

        if ($material_card_attachment->attachment_type !== MaterialCardAttachment::TYPE_FILE || ! $material_card_attachment->file_path) {
            abort(404, 'Datei nicht gefunden');
        }

        $downloadUrl = '/api/admin/materials/attachments/' . $material_card_attachment->id . '/download';

        return $previewService->preview($material_card_attachment, $downloadUrl);
    }

    private function authorizeForMaterials()
    {
        if (! $authUser = $this->userHasRole(['admin', 'materials_admin', 'materials_moderator'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        return $authUser;
    }

    private function assertIsOwner(int $authUserId, int $ownerId): void
    {
        if ($authUserId !== $ownerId) {
            abort(403, 'Sie dürfen nur eigene Materialkarten verwalten.');
        }
    }

    private function loadCardForResponse(MaterialCard $card): MaterialCard
    {
        if (Schema::hasTable('material_card_classifications')) {
            return $card->loadMissing(
                'attachments',
                'classifications.subject',
                'classifications.topic',
                'classifications.unit'
            );
        }

        return $card->loadMissing('attachments');
    }

    private function isHtmlAttachment(MaterialCardAttachment $attachment): bool
    {
        if ($attachment->attachment_type !== MaterialCardAttachment::TYPE_FILE) {
            return false;
        }

        $mimeType = strtolower(trim((string) ($attachment->mime_type ?? '')));
        if ($mimeType === 'text/html' || $mimeType === 'application/xhtml+xml') {
            return true;
        }

        $name = trim((string) ($attachment->name ?? ''));
        $filePath = trim((string) ($attachment->file_path ?? ''));
        $candidate = $name !== '' ? $name : $filePath;
        $extension = strtolower((string) pathinfo($candidate, PATHINFO_EXTENSION));

        return $extension === 'html' || $extension === 'htm';
    }

    private function ensureRichTextStylesForHtmlDownload(string $html, string $fileName): string
    {
        $document = trim($html);
        if ($document === '') {
            $safeTitle = htmlspecialchars($fileName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $document = '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>'
                . $safeTitle . '</title></head><body></body></html>';
        }

        if (! mb_check_encoding($document, 'UTF-8')) {
            $document = mb_convert_encoding($document, 'UTF-8', 'UTF-8,ISO-8859-1,Windows-1252');
        }

        if (! preg_match('/<html/i', $document)) {
            $safeTitle = htmlspecialchars($fileName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $document = '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>'
                . $safeTitle . '</title></head><body>' . $document . '</body></html>';
        }

        if (! preg_match('/<meta[^>]+charset=/i', $document)) {
            $document = preg_replace('/<head([^>]*)>/i', '<head$1><meta charset="utf-8">', $document, 1) ?? $document;
        }

        if (preg_match('/<style[^>]+id="materials-richtext-download-style"/i', $document)) {
            return $document;
        }

        $styleTag = $this->richTextDownloadStyleTag();
        if (preg_match('/<\/head>/i', $document)) {
            $document = preg_replace('/<\/head>/i', $styleTag . '</head>', $document, 1) ?? $document;
            return $document;
        }

        if (preg_match('/<head[^>]*>/i', $document)) {
            $document = preg_replace('/<head([^>]*)>/i', '<head$1>' . $styleTag, $document, 1) ?? $document;
            return $document;
        }

        return $document;
    }

    private function richTextDownloadStyleTag(): string
    {
        return '<style id="materials-richtext-download-style">'
            . 'body{font-family:Arial,sans-serif;line-height:1.55;color:#1a2b3b;margin:14px;}'
            . 'p{margin:0 0 .65rem 0;}'
            . 'ul,ol{margin:.45rem 0 .75rem 0;padding-inline-start:1.4rem;}'
            . 'li{margin:.2rem 0;}'
            . 'blockquote{margin:.75rem 0;padding:.5rem .75rem;border-left:3px solid #fd802e;background:rgba(253,128,46,.10);border-radius:0 6px 6px 0;}'
            . 'pre{background:#f5f7fb;border:1px solid #d9e1f3;border-radius:8px;padding:10px 12px;overflow:auto;}'
            . 'code{background:#f5f7fb;border:1px solid #d9e1f3;border-radius:4px;padding:1px 4px;}'
            . '</style>';
    }

    private function extractHtmlBody(string $document): string
    {
        $match = [];
        if (preg_match('/<body[^>]*>([\s\S]*)<\/body>/i', $document, $match)) {
            return trim((string) ($match[1] ?? ''));
        }

        return trim($document);
    }

    private function normalizeHtmlFragmentForDocx(string $html): string
    {
        $fragment = trim($html);
        if ($fragment === '') {
            return '<p></p>';
        }

        if (! mb_check_encoding($fragment, 'UTF-8')) {
            $fragment = mb_convert_encoding($fragment, 'UTF-8', 'UTF-8,ISO-8859-1,Windows-1252');
        }

        $fragment = $this->normalizeBlockquotesForDocx($fragment);
        $wrappedHtml = '<!doctype html><html><head><meta charset="UTF-8"></head><body>' . $fragment . '</body></html>';
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previousState = libxml_use_internal_errors(true);

        try {
            $flags = 0;
            if (defined('LIBXML_HTML_NOIMPLIED')) {
                $flags |= LIBXML_HTML_NOIMPLIED;
            }
            if (defined('LIBXML_HTML_NODEFDTD')) {
                $flags |= LIBXML_HTML_NODEFDTD;
            }
            if (defined('LIBXML_NONET')) {
                $flags |= LIBXML_NONET;
            }

            $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $wrappedHtml, $flags);
            if (! $loaded) {
                return $fragment;
            }

            $bodyNodes = $dom->getElementsByTagName('body');
            if ($bodyNodes->length === 0) {
                return $fragment;
            }

            $body = $bodyNodes->item(0);
            if (! $body) {
                return $fragment;
            }

            $normalized = '';
            foreach ($body->childNodes as $childNode) {
                $normalized .= (string) ($dom->saveXML($childNode) ?: '');
            }

            return trim($normalized) !== '' ? trim($normalized) : '<p></p>';
        } catch (\Throwable $error) {
            return $fragment;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousState);
        }
    }

    private function normalizeBlockquotesForDocx(string $html): string
    {
        $quoteTableStyle = $this->docxBlockquoteTableStyle();
        $quoteCellStyle = $this->docxBlockquoteCellStyle();

        $normalized = preg_replace_callback(
            '/<blockquote\b[^>]*>([\s\S]*?)<\/blockquote>/iu',
            static function (array $match) use ($quoteTableStyle, $quoteCellStyle): string {
                $inner = trim((string) ($match[1] ?? ''));
                if ($inner === '') {
                    return '';
                }

                $mergedParagraphs = preg_replace('/<\/p>\s*<p\b[^>]*>/iu', '<br/><br/>', $inner) ?? $inner;
                $mergedParagraphs = preg_replace('/^\s*<p\b[^>]*>/iu', '', $mergedParagraphs) ?? $mergedParagraphs;
                $mergedParagraphs = preg_replace('/<\/p>\s*$/iu', '', $mergedParagraphs) ?? $mergedParagraphs;

                return '<table style="' . $quoteTableStyle . '"><tr><td style="' . $quoteCellStyle . '">'
                    . trim($mergedParagraphs)
                    . '</td></tr></table>';
            },
            $html
        );

        return $normalized ?? $html;
    }

    private function docxBlockquoteTableStyle(): string
    {
        return 'width:100%; margin-top:10px; margin-bottom:10px;';
    }

    private function docxBlockquoteCellStyle(): string
    {
        return 'border-left:3px #FD802E solid; background-color:#FFF2E8; padding:8px 18px; font-style:italic; color:#5A3A12;';
    }

    private function plainTextFromHtmlForDocx(string $html): string
    {
        $normalized = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
        $normalized = preg_replace('/<\/(p|div|li|h[1-6]|blockquote)>/i', "$0\n", $normalized) ?? $normalized;

        $text = strip_tags($normalized);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $text = preg_replace("/\r\n|\r/u", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;
        $text = trim((string) $text);

        return $text;
    }

    private function addPlainTextToDocxSection(\PhpOffice\PhpWord\Element\Section $section, string $text): void
    {
        $normalizedText = trim($text);
        if ($normalizedText === '') {
            $section->addText(' ', ['size' => 12]);
            return;
        }

        $lines = preg_split('/\n/u', $normalizedText) ?: [];
        $wroteText = false;

        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $section->addTextBreak();
            }

            $segment = trim((string) $line);
            if ($segment === '') {
                continue;
            }

            $section->addText($segment, ['size' => 12]);
            $wroteText = true;
        }

        if (! $wroteText) {
            $section->addText($normalizedText, ['size' => 12]);
        }
    }

    private function newDocxDocument(): \PhpOffice\PhpWord\PhpWord
    {
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $phpWord->setDefaultFontSize(12);

        $docLocale = $this->docxLanguageFromLaravelLocale();
        $phpWord->getSettings()->setThemeFontLang(new \PhpOffice\PhpWord\Style\Language($docLocale));

        $phpWord->addTitleStyle(1, ['bold' => true, 'size' => 24], ['spaceAfter' => 240]);
        $phpWord->addTitleStyle(2, ['bold' => true, 'size' => 20], ['spaceAfter' => 220]);
        $phpWord->addTitleStyle(3, ['bold' => true, 'size' => 16], ['spaceAfter' => 200]);
        $phpWord->addTitleStyle(4, ['bold' => true, 'size' => 14], ['spaceAfter' => 180]);
        $phpWord->addTitleStyle(5, ['bold' => true, 'size' => 13], ['spaceAfter' => 160]);
        $phpWord->addTitleStyle(6, ['bold' => true, 'size' => 12], ['spaceAfter' => 140]);

        return $phpWord;
    }

    private function docxLanguageFromLaravelLocale(): string
    {
        $raw = trim((string) (app()->getLocale() ?: config('app.locale', 'en')));
        if ($raw === '') {
            return 'en-US';
        }

        $normalized = str_replace('_', '-', $raw);
        if (preg_match('/^[a-z]{2}$/i', $normalized) === 1) {
            return strtolower($normalized) . '-' . strtoupper($normalized);
        }

        $parts = array_values(array_filter(explode('-', $normalized), static fn ($part) => $part !== ''));
        if (count($parts) >= 2) {
            $language = strtolower((string) $parts[0]);
            $region = strtoupper((string) $parts[1]);

            if (preg_match('/^[a-z]{2}$/', $language) === 1 && preg_match('/^[A-Z0-9]{2,4}$/', $region) === 1) {
                return $language . '-' . $region;
            }
        }

        return 'en-US';
    }

    private function docxDownloadName(string $name): string
    {
        $base = trim($name);
        if ($base === '') {
            return 'Text.docx';
        }

        $withoutHtml = preg_replace('/\.(html?|HTML?)$/', '', $base) ?? $base;
        $withoutTrailingDot = rtrim($withoutHtml, ". \t\n\r\0\x0B");
        if ($withoutTrailingDot === '') {
            return 'Text.docx';
        }

        return mb_substr($withoutTrailingDot, 0, 240) . '.docx';
    }

    private function safeAttachmentDownloadName(string $name): string
    {
        $value = trim($name);
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? $value;
        $value = str_replace(['/', '\\'], '_', $value);
        $value = trim($value, " .\t\n\r\0\x0B");

        if ($value === '') {
            return 'Datei';
        }

        return mb_substr($value, 0, 240);
    }

    private function attachmentDownloadContentType(
        MaterialCardAttachment $attachment,
        \Illuminate\Contracts\Filesystem\Filesystem $disk,
        string $relativePath
    ): string {
        $storedMimeType = trim((string) ($attachment->mime_type ?? ''));
        if ($this->isSafeHeaderValue($storedMimeType)) {
            return $storedMimeType;
        }

        $detectedMimeType = $disk->mimeType($relativePath);
        $detectedMimeType = is_string($detectedMimeType) ? trim($detectedMimeType) : '';

        if ($this->isSafeHeaderValue($detectedMimeType)) {
            return $detectedMimeType;
        }

        return 'application/octet-stream';
    }

    private function isSafeHeaderValue(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        return ! preg_match('/[\r\n\x00]/', $value);
    }
}
