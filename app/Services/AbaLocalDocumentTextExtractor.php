<?php

namespace App\Services;

use App\Models\AbaAttachment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class AbaLocalDocumentTextExtractor
{
    public function __construct(
        private readonly AbaDocxPageMapper $pageMapper,
    ) {}

    /**
     * @return array{absolute_path:string,is_temp:bool}
     */
    public function prepareLocalFile(AbaAttachment $attachment): array
    {
        $diskName = trim((string) ($attachment->disk ?? '')) ?: 'local';
        $relativePath = trim((string) ($attachment->path ?? ''));
        if ($relativePath === '') {
            throw new \RuntimeException('Dokumentpfad fehlt.');
        }

        if ($diskName === 'local') {
            $absolutePath = Storage::disk('local')->path($relativePath);
            if (! is_file($absolutePath)) {
                throw new \RuntimeException('Dokumentdatei wurde nicht gefunden.');
            }

            return ['absolute_path' => $absolutePath, 'is_temp' => false];
        }

        if (! Storage::disk($diskName)->exists($relativePath)) {
            throw new \RuntimeException('Dokumentdatei wurde nicht gefunden.');
        }

        $content = Storage::disk($diskName)->get($relativePath);
        $tempPath = tempnam(sys_get_temp_dir(), 'aba_doc_');
        if (! is_string($tempPath) || $tempPath === '') {
            throw new \RuntimeException('Temporäre Datei konnte nicht erstellt werden.');
        }

        $extension = $this->guessExtension($attachment, $relativePath);
        if ($extension !== '') {
            $extendedPath = $tempPath.'.'.$extension;
            if (@rename($tempPath, $extendedPath)) {
                $tempPath = $extendedPath;
            }
        }

        file_put_contents($tempPath, $content);

        return ['absolute_path' => $tempPath, 'is_temp' => true];
    }

    public function extractText(AbaAttachment $attachment): string
    {
        $result = $this->extractDocument($attachment);

        return (string) ($result['text'] ?? '');
    }

    /**
     * @return array{
     *   text:string,
     *   selected_candidate:string|null,
     *   candidates:array<int, array<string,mixed>>,
     *   outline:array<int, array<string,mixed>>,
     *   toc_lines:array<int, int>,
     *   metadata:array<string,mixed>
     * }
     */
    public function extractDocument(AbaAttachment $attachment): array
    {
        $prepared = $this->prepareLocalFile($attachment);
        $absolutePath = $prepared['absolute_path'];
        $isTemp = $prepared['is_temp'];

        try {
            $extension = strtolower($this->guessExtension($attachment, $absolutePath));
            $mimeType = strtolower(trim((string) ($attachment->mime_type ?? '')));
            $isDocxLike = in_array($extension, ['docx', 'docm', 'dotx'], true)
                || in_array($mimeType, [
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-word.document.macroenabled.12',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
                ], true);

            if ($isDocxLike) {
                return $this->extractDocxDocument($absolutePath);
            }

            $extractors = [];
            if (in_array($extension, ['txt', 'md', 'log', 'csv', 'json', 'xml', 'yaml', 'yml'], true) || str_starts_with($mimeType, 'text/')) {
                $extractors[] = ['id' => 'plain_text', 'callback' => fn (): string => $this->extractFromTextFile($absolutePath)];
            }

            if (in_array($extension, ['docx', 'docm', 'dotx', 'odt'], true)) {
                $extractors[] = ['id' => 'zip_xml_raw', 'callback' => fn (): string => $this->extractFromZipXml($absolutePath, $extension)];
            }

            if (in_array($extension, ['html', 'htm', 'xhtml'], true) || in_array($mimeType, ['text/html', 'application/xhtml+xml'], true)) {
                $extractors[] = ['id' => 'html', 'callback' => fn (): string => $this->extractFromHtml($absolutePath)];
            }

            if (in_array($extension, ['rtf'], true)) {
                $extractors[] = ['id' => 'rtf', 'callback' => fn (): string => $this->extractFromRtf($absolutePath)];
            }

            if ($extension === 'pdf' || $mimeType === 'application/pdf') {
                $extractors[] = ['id' => 'pdf_tools', 'callback' => fn (): string => $this->extractFromPdf($absolutePath)];
            }

            $extractors[] = ['id' => 'libreoffice', 'callback' => fn (): string => $this->extractWithLibreOffice($absolutePath)];
            $extractors[] = ['id' => 'plain_text_fallback', 'callback' => fn (): string => $this->extractFromTextFile($absolutePath)];

            $candidates = [];
            foreach ($extractors as $extractor) {
                $text = '';
                $status = 'ok';
                try {
                    $text = $this->normalizeText((string) $extractor['callback']());
                    if ($text === '') {
                        $status = 'empty';
                    }
                } catch (\Throwable) {
                    $status = 'error';
                }

                $lineCount = $text === '' ? 0 : count(preg_split('/\n/u', $text) ?: []);
                $candidates[] = [
                    'id' => (string) $extractor['id'],
                    'text' => $text,
                    'outline' => [],
                    'toc_lines' => [],
                    'status' => $status,
                    'score' => $text === '' ? -100 : 10 + $lineCount,
                    'metrics' => [
                        'line_count' => $lineCount,
                        'text_length' => mb_strlen($text),
                    ],
                    'selection_reason' => 'generic_pipeline',
                ];
            }

            $selected = $this->selectBestCandidate($candidates, 'generic');
            $this->logCandidateDiagnostics('generic', $candidates, $selected);

            return [
                'text' => (string) ($selected['text'] ?? ''),
                'selected_candidate' => is_string($selected['id'] ?? null) ? $selected['id'] : null,
                'candidates' => $this->reduceCandidateSummaries($candidates),
                'outline' => [],
                'toc_lines' => [],
                'metadata' => [
                    'path_type' => 'generic',
                ],
            ];
        } finally {
            if ($isTemp) {
                @unlink($absolutePath);
            }
        }
    }

    /**
     * @return array{
     *   text:string,
     *   selected_candidate:string|null,
     *   candidates:array<int, array<string,mixed>>,
     *   outline:array<int, array<string,mixed>>,
     *   toc_lines:array<int, int>,
     *   metadata:array<string,mixed>
     * }
     */
    private function extractDocxDocument(string $absolutePath): array
    {
        $candidates = [
            $this->buildDocxXmlCandidate($absolutePath),
            $this->buildDocxPhpWordCandidate($absolutePath),
            $this->buildDocxMammothCandidate($absolutePath),
            [
                'id' => 'zip_xml_raw',
                'text' => $this->normalizeText($this->extractFromZipXml($absolutePath, 'docx')),
                'outline' => [],
                'toc_lines' => [],
                'status' => 'ok',
                'metrics' => [],
                'selection_reason' => 'fallback_raw',
            ],
        ];

        foreach ($candidates as $index => $candidate) {
            $evaluated = $this->evaluateCandidate($candidate, 'docx');
            $candidates[$index] = array_merge($candidate, $evaluated);
        }

        $selected = $this->selectBestCandidate($candidates, 'docx');
        $this->logCandidateDiagnostics('docx', $candidates, $selected);

        $text = $this->normalizeText((string) ($selected['text'] ?? ''));
        if ($text === '') {
            $libreofficeText = $this->normalizeText($this->extractWithLibreOffice($absolutePath));
            if ($libreofficeText !== '') {
                $text = $libreofficeText;
            }
        }

        $pageMap = $this->pageMapper->buildPageMap($absolutePath);

        return [
            'text' => $text,
            'selected_candidate' => is_string($selected['id'] ?? null) ? $selected['id'] : null,
            'candidates' => $this->reduceCandidateSummaries($candidates),
            'outline' => is_array($selected['outline'] ?? null) ? array_values($selected['outline']) : [],
            'toc_lines' => is_array($selected['toc_lines'] ?? null) ? array_values(array_unique(array_map('intval', $selected['toc_lines']))) : [],
            'metadata' => [
                'path_type' => 'docx_hybrid',
                'selected_score' => (int) ($selected['score'] ?? 0),
            ],
            'page_map' => $pageMap,
        ];
    }

    /**
     * @return array{
     *   id:string,
     *   text:string,
     *   outline:array<int, array<string,mixed>>,
     *   toc_lines:array<int, int>,
     *   status:string,
     *   metrics:array<string,mixed>,
     *   selection_reason:string
     * }
     */
    private function buildDocxXmlCandidate(string $absolutePath): array
    {
        $candidate = [
            'id' => 'docx_xml',
            'text' => '',
            'outline' => [],
            'toc_lines' => [],
            'status' => 'empty',
            'metrics' => [],
            'selection_reason' => 'zip_xml_outline',
        ];

        if (! class_exists(\ZipArchive::class)) {
            $candidate['status'] = 'unavailable';
            $candidate['metrics'] = ['reason' => 'zip_extension_missing'];

            return $candidate;
        }

        $zip = new \ZipArchive;
        if ($zip->open($absolutePath) !== true) {
            $candidate['status'] = 'error';
            $candidate['metrics'] = ['reason' => 'zip_open_failed'];

            return $candidate;
        }

        try {
            $xml = $zip->getFromName('word/document.xml');
            if (! is_string($xml) || trim($xml) === '') {
                $candidate['status'] = 'empty';
                $candidate['metrics'] = ['reason' => 'document_xml_missing'];

                return $candidate;
            }

            $document = new \DOMDocument('1.0', 'UTF-8');
            if (! @$document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
                $candidate['status'] = 'error';
                $candidate['metrics'] = ['reason' => 'document_xml_invalid'];

                return $candidate;
            }

            $xpath = new \DOMXPath($document);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $paragraphs = $xpath->query('//w:body/w:p');
            if (! $paragraphs) {
                $candidate['status'] = 'empty';
                $candidate['metrics'] = ['reason' => 'paragraphs_not_found'];

                return $candidate;
            }

            $lines = [];
            $outline = [];
            $tocLines = [];
            $lineNumber = 0;

            foreach ($paragraphs as $paragraph) {
                $text = $this->extractTextFromWordParagraph($xpath, $paragraph);
                $text = trim($text);
                if ($text === '') {
                    continue;
                }

                $lineNumber++;
                $styleValue = trim((string) $xpath->evaluate('string(w:pPr/w:pStyle/@w:val)', $paragraph));
                $outlineLevelValue = trim((string) $xpath->evaluate('string(w:pPr/w:outlineLvl/@w:val)', $paragraph));
                $headingLevel = $this->resolveWordHeadingLevel($styleValue, $outlineLevelValue, $text);
                $knownType = $this->knownSectionType($text);
                $isTocStyle = $this->isWordTocStyle($styleValue);
                $isTocEntry = $this->looksLikeTocEntry($text);
                $isTocLine = $isTocStyle || $isTocEntry;

                if ($isTocLine) {
                    $tocLines[] = $lineNumber;
                }

                $outlineSource = null;
                if ($knownType !== null) {
                    $outlineSource = 'keyword';
                    $headingLevel = $headingLevel ?? 1;
                } elseif ($headingLevel !== null) {
                    $outlineSource = 'docx_style';
                } elseif ($this->looksLikeNumberedHeading($text)) {
                    $outlineSource = 'numbered';
                    $headingLevel = $this->numberedHeadingLevel($text);
                }

                if ($outlineSource !== null) {
                    $outline[] = [
                        'line_number' => $lineNumber,
                        'title' => $text,
                        'level' => $headingLevel,
                        'source' => $outlineSource,
                        'is_toc' => $isTocLine,
                        'section_type' => $knownType,
                    ];
                }

                $lines[] = $text;
            }

            $candidate['text'] = $this->normalizeText(implode("\n", $lines));
            $candidate['outline'] = $outline;
            $candidate['toc_lines'] = $tocLines;
            $candidate['status'] = $candidate['text'] === '' ? 'empty' : 'ok';
            $candidate['metrics'] = [
                'paragraph_count' => count($lines),
                'outline_count' => count($outline),
                'toc_line_count' => count($tocLines),
            ];
        } finally {
            $zip->close();
        }

        return $candidate;
    }

    /**
     * @return array{
     *   id:string,
     *   text:string,
     *   outline:array<int, array<string,mixed>>,
     *   toc_lines:array<int, int>,
     *   status:string,
     *   metrics:array<string,mixed>,
     *   selection_reason:string
     * }
     */
    private function buildDocxPhpWordCandidate(string $absolutePath): array
    {
        $candidate = [
            'id' => 'phpword_html',
            'text' => '',
            'outline' => [],
            'toc_lines' => [],
            'status' => 'empty',
            'metrics' => [],
            'selection_reason' => 'phpword_html_render',
        ];

        if (! class_exists(WordIOFactory::class)) {
            $candidate['status'] = 'unavailable';
            $candidate['metrics'] = ['reason' => 'phpword_missing'];

            return $candidate;
        }

        try {
            $phpWord = WordIOFactory::load($absolutePath, 'Word2007');
            $writer = WordIOFactory::createWriter($phpWord, 'HTML');

            ob_start();
            $writer->save('php://output');
            $html = (string) ob_get_clean();
            if (trim($html) === '') {
                $candidate['status'] = 'empty';
                $candidate['metrics'] = ['reason' => 'phpword_html_empty'];

                return $candidate;
            }

            $parsed = $this->parseHtmlDocument($html);
            $candidate['text'] = $parsed['text'];
            $candidate['outline'] = $parsed['outline'];
            $candidate['toc_lines'] = $parsed['toc_lines'];
            $candidate['status'] = $candidate['text'] === '' ? 'empty' : 'ok';
            $candidate['metrics'] = [
                'block_count' => (int) ($parsed['metrics']['block_count'] ?? 0),
                'outline_count' => count($parsed['outline']),
            ];
        } catch (\Throwable $exception) {
            $candidate['status'] = 'error';
            $candidate['metrics'] = ['reason' => 'phpword_exception', 'error' => mb_substr($exception->getMessage(), 0, 300)];
        }

        return $candidate;
    }

    /**
     * @return array{
     *   id:string,
     *   text:string,
     *   outline:array<int, array<string,mixed>>,
     *   toc_lines:array<int, int>,
     *   status:string,
     *   metrics:array<string,mixed>,
     *   selection_reason:string
     * }
     */
    private function buildDocxMammothCandidate(string $absolutePath): array
    {
        $candidate = [
            'id' => 'mammoth_markdown',
            'text' => '',
            'outline' => [],
            'toc_lines' => [],
            'status' => 'empty',
            'metrics' => [],
            'selection_reason' => 'mammoth_markdown',
        ];

        $commands = [
            ['mammoth', '--output-format=markdown', $absolutePath],
            ['npx', '--yes', 'mammoth', '--output-format=markdown', $absolutePath],
        ];

        $markdown = '';
        $usedCommand = null;
        foreach ($commands as $command) {
            $output = $this->runProcess($command, 25);
            $normalized = $this->normalizeText($output);
            if ($normalized !== '') {
                $markdown = $normalized;
                $usedCommand = implode(' ', $command);
                break;
            }
        }

        if ($markdown === '') {
            $candidate['status'] = 'unavailable';
            $candidate['metrics'] = ['reason' => 'mammoth_not_available_or_empty'];

            return $candidate;
        }

        $lines = preg_split('/\n/u', $markdown) ?: [];
        $plainLines = [];
        $outline = [];
        $tocLines = [];
        $lineNumber = 0;

        foreach ($lines as $rawLine) {
            $line = trim((string) $rawLine);
            if ($line === '') {
                continue;
            }

            $lineNumber++;
            if (preg_match('/^(#{1,6})\s+(.+)$/u', $line, $matches) === 1) {
                $level = strlen((string) $matches[1]);
                $title = trim((string) $matches[2]);
                $plainLines[] = $title;
                $outline[] = [
                    'line_number' => $lineNumber,
                    'title' => $title,
                    'level' => $level,
                    'source' => 'mammoth_heading',
                    'is_toc' => false,
                    'section_type' => $this->knownSectionType($title),
                ];

                continue;
            }

            $plain = trim((string) preg_replace('/^\s*[-*+]\s+/', '', $line));
            if ($plain === '') {
                continue;
            }

            if ($this->looksLikeTocEntry($plain)) {
                $tocLines[] = $lineNumber;
            }

            $plainLines[] = $plain;
        }

        $candidate['text'] = $this->normalizeText(implode("\n", $plainLines));
        $candidate['outline'] = $outline;
        $candidate['toc_lines'] = $tocLines;
        $candidate['status'] = $candidate['text'] === '' ? 'empty' : 'ok';
        $candidate['metrics'] = [
            'command' => $usedCommand,
            'line_count' => count($plainLines),
            'outline_count' => count($outline),
        ];

        return $candidate;
    }

    /**
     * @param  array<string,mixed>  $candidate
     * @return array{score:int,metrics:array<string,mixed>}
     */
    private function evaluateCandidate(array $candidate, string $context): array
    {
        $candidateId = (string) ($candidate['id'] ?? '');
        $text = $this->normalizeText((string) ($candidate['text'] ?? ''));
        $outline = is_array($candidate['outline'] ?? null) ? $candidate['outline'] : [];
        $tocLines = is_array($candidate['toc_lines'] ?? null) ? $candidate['toc_lines'] : [];

        $lines = $text === '' ? [] : array_values(array_filter(
            preg_split('/\n/u', $text) ?: [],
            fn (string $line): bool => trim($line) !== ''
        ));

        $outlineTotal = count($outline);
        $outlineNonToc = 0;
        $chapterLike = 0;
        $keywordSections = 0;
        foreach ($outline as $entry) {
            $title = trim((string) ($entry['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            if (! ($entry['is_toc'] ?? false)) {
                $outlineNonToc++;
            }
            if ($this->looksLikeNumberedHeading($title) || $this->knownSectionType($title) === 'chapter') {
                $chapterLike++;
            }
            if ($this->knownSectionType($title) !== null) {
                $keywordSections++;
            }
        }

        $lineCount = count($lines);
        $textLength = mb_strlen($text);
        $tocLineCount = count(array_unique(array_map('intval', $tocLines)));
        $tocDensity = $lineCount > 0 ? $tocLineCount / $lineCount : 0.0;
        $lineLengths = array_map(static fn (string $line): int => mb_strlen($line), $lines);
        $maxLineLength = $lineLengths === [] ? 0 : max($lineLengths);
        $avgLineLength = $lineCount > 0 ? (int) floor($textLength / $lineCount) : 0;
        $longLineCount = count(array_filter($lineLengths, static fn (int $length): bool => $length >= 800));

        $score = 0;
        if ($text !== '') {
            $score += min(120, (int) floor($textLength / 35));
            $score += min(80, $lineCount);
        } else {
            $score -= 200;
        }

        $score += $outlineNonToc * 16;
        $score += $chapterLike * 18;
        $score += $keywordSections * 8;

        $sourceWeight = 0;
        if ($context === 'docx') {
            $sourceWeight = match ($candidateId) {
                'docx_xml' => 75,
                'phpword_html' => 55,
                'mammoth_markdown' => 25,
                'zip_xml_raw' => -20,
                default => 0,
            };
            $score += $sourceWeight;
        }

        if ($outlineTotal > 0 && $outlineNonToc === 0) {
            $score -= 45;
        }

        if ($tocDensity > 0.35) {
            $score -= (int) floor($tocDensity * 60);
        }

        $anomalyPenalty = 0;
        if ($context === 'docx') {
            if ($avgLineLength > 350) {
                $anomalyPenalty += min(220, (int) floor(($avgLineLength - 350) / 5));
            }

            if ($maxLineLength > 1800) {
                $anomalyPenalty += min(140, (int) floor(($maxLineLength - 1800) / 30));
            }

            if ($lineCount > 0 && ($longLineCount / $lineCount) > 0.18) {
                $anomalyPenalty += 90;
            }

            if ($outlineTotal >= 8 && $chapterLike === 0) {
                $anomalyPenalty += 110;
            }
        }
        $score -= $anomalyPenalty;

        if ($context === 'docx' && $lineCount < 25) {
            $score -= 20;
        }

        if ($context === 'docx' && $candidateId === 'mammoth_markdown' && $chapterLike === 0 && $keywordSections <= 2) {
            $score -= 80;
        }

        if ($text === '') {
            $score -= 100;
        }

        return [
            'score' => $score,
            'metrics' => array_merge(
                is_array($candidate['metrics'] ?? null) ? $candidate['metrics'] : [],
                [
                    'line_count' => $lineCount,
                    'text_length' => $textLength,
                    'outline_total' => $outlineTotal,
                    'outline_non_toc' => $outlineNonToc,
                    'chapter_like' => $chapterLike,
                    'keyword_sections' => $keywordSections,
                    'toc_line_count' => $tocLineCount,
                    'toc_density' => $tocDensity,
                    'avg_line_length' => $avgLineLength,
                    'max_line_length' => $maxLineLength,
                    'long_line_count' => $longLineCount,
                    'source_weight' => $sourceWeight,
                    'anomaly_penalty' => $anomalyPenalty,
                ]
            ),
        ];
    }

    /**
     * @param  array<int, array<string,mixed>>  $candidates
     * @return array<string,mixed>
     */
    private function selectBestCandidate(array $candidates, string $context): array
    {
        if ($candidates === []) {
            return [];
        }

        $selected = null;
        foreach ($candidates as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            if ($selected === null) {
                $selected = $candidate;

                continue;
            }

            $candidateScore = (int) ($candidate['score'] ?? -9999);
            $selectedScore = (int) ($selected['score'] ?? -9999);
            if ($candidateScore > $selectedScore) {
                $selected = $candidate;

                continue;
            }

            if ($candidateScore === $selectedScore) {
                $candidateLength = (int) mb_strlen((string) ($candidate['text'] ?? ''));
                $selectedLength = (int) mb_strlen((string) ($selected['text'] ?? ''));
                if ($candidateLength > $selectedLength) {
                    $selected = $candidate;
                }
            }
        }

        if ($selected === null) {
            $selected = $candidates[0];
        }

        $selected['selection_reason'] = $context === 'docx'
            ? 'best_score_docx_hybrid'
            : 'best_score_generic';

        return $selected;
    }

    /**
     * @param  array<int, array<string,mixed>>  $candidates
     * @return array<int, array<string,mixed>>
     */
    private function reduceCandidateSummaries(array $candidates): array
    {
        $summary = [];
        foreach ($candidates as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            $summary[] = [
                'id' => (string) ($candidate['id'] ?? ''),
                'status' => (string) ($candidate['status'] ?? 'unknown'),
                'score' => (int) ($candidate['score'] ?? -9999),
                'metrics' => is_array($candidate['metrics'] ?? null) ? $candidate['metrics'] : [],
                'selection_reason' => (string) ($candidate['selection_reason'] ?? ''),
            ];
        }

        return $summary;
    }

    /**
     * @param  array<int, array<string,mixed>>  $candidates
     * @param  array<string,mixed>  $selected
     */
    private function logCandidateDiagnostics(string $context, array $candidates, array $selected): void
    {
        $payload = [
            'context' => $context,
            'candidates' => $this->reduceCandidateSummaries($candidates),
            'selected_candidate' => $selected['id'] ?? null,
            'selected_score' => $selected['score'] ?? null,
            'selected_reason' => $selected['selection_reason'] ?? null,
        ];

        $this->logDebug('aba.extraction.candidates_evaluated', $payload);
        $this->logDebug('ABA extraction candidates evaluated', $payload);
        $this->logDebug('ABA extraction candidate selected', [
            'context' => $context,
            'selected_candidate' => $selected['id'] ?? null,
            'selected_score' => $selected['score'] ?? null,
            'selected_reason' => $selected['selection_reason'] ?? null,
            'selected_metrics' => is_array($selected['metrics'] ?? null) ? $selected['metrics'] : [],
        ]);
    }

    private function extractFromTextFile(string $absolutePath): string
    {
        $content = @file_get_contents($absolutePath);
        if (! is_string($content)) {
            return '';
        }

        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8,ISO-8859-1,Windows-1252');
        }

        return $content;
    }

    private function extractFromHtml(string $absolutePath): string
    {
        $html = $this->extractFromTextFile($absolutePath);
        if ($html === '') {
            return '';
        }

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) $text);
    }

    private function extractFromRtf(string $absolutePath): string
    {
        $content = $this->extractFromTextFile($absolutePath);
        if ($content === '') {
            return '';
        }

        $text = preg_replace('/\\\\[a-z]+\d* ?/i', ' ', $content) ?? $content;
        $text = preg_replace('/[{}]/', ' ', $text) ?? $text;

        return trim((string) $text);
    }

    private function extractFromZipXml(string $absolutePath, string $extension): string
    {
        if (! class_exists(\ZipArchive::class)) {
            return '';
        }

        $zip = new \ZipArchive;
        if ($zip->open($absolutePath) !== true) {
            return '';
        }

        try {
            $entries = match ($extension) {
                'odt' => ['content.xml'],
                default => $this->wordZipEntries($zip),
            };

            $parts = [];
            foreach ($entries as $entryName) {
                $xml = $zip->getFromName($entryName);
                if (! is_string($xml) || $xml === '') {
                    continue;
                }

                $text = $this->extractTextFromXml($xml);
                if ($text !== '') {
                    $parts[] = $text;
                }
            }

            return implode("\n\n", $parts);
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array<int, string>
     */
    private function wordZipEntries(\ZipArchive $zip): array
    {
        $entries = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (! is_string($name)) {
                continue;
            }

            if (preg_match('#^word/(document|header\d+|footer\d+|footnotes|endnotes)\.xml$#', $name) === 1) {
                $entries[] = $name;
            }
        }

        if ($entries === []) {
            $entries[] = 'word/document.xml';
        }

        return $entries;
    }

    private function extractTextFromXml(string $xml): string
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $loaded = @$document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        if (! $loaded) {
            $stripped = preg_replace('/<[^>]+>/', ' ', $xml) ?? $xml;
            $decoded = html_entity_decode($stripped, ENT_QUOTES | ENT_XML1, 'UTF-8');

            return trim((string) $decoded);
        }

        $xpath = new \DOMXPath($document);
        $nodes = $xpath->query('//text()[normalize-space()]');
        if (! $nodes) {
            return '';
        }

        $parts = [];
        foreach ($nodes as $node) {
            $value = trim((string) $node->nodeValue);
            if ($value !== '') {
                $parts[] = $value;
            }
        }

        return implode("\n", $parts);
    }

    private function extractTextFromWordParagraph(\DOMXPath $xpath, \DOMNode $paragraph): string
    {
        $parts = [];
        foreach ($xpath->query('.//w:t|.//w:tab|.//w:br', $paragraph) ?: [] as $node) {
            if ($node->localName === 'tab') {
                $parts[] = "\t";

                continue;
            }

            if ($node->localName === 'br') {
                $parts[] = ' ';

                continue;
            }

            $parts[] = (string) $node->textContent;
        }

        $text = implode('', $parts);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function resolveWordHeadingLevel(string $styleValue, string $outlineLevelValue, string $text): ?int
    {
        if ($outlineLevelValue !== '' && is_numeric($outlineLevelValue)) {
            $value = (int) $outlineLevelValue + 1;
            if ($value > 0) {
                return min(9, $value);
            }
        }

        $styleNormalized = mb_strtolower(trim($styleValue));
        if ($styleNormalized !== '') {
            if (preg_match('/(?:heading|überschrift|ueberschrift)\s*([1-9])/iu', $styleNormalized, $matches) === 1) {
                return min(9, (int) $matches[1]);
            }

            if (preg_match('/^h([1-9])$/iu', $styleNormalized, $matches) === 1) {
                return min(9, (int) $matches[1]);
            }
        }

        if ($this->looksLikeNumberedHeading($text)) {
            return $this->numberedHeadingLevel($text);
        }

        return null;
    }

    private function isWordTocStyle(string $styleValue): bool
    {
        $normalized = mb_strtolower(trim($styleValue));
        if ($normalized === '') {
            return false;
        }

        return preg_match('/^(toc|inhaltsverzeichnis)/iu', $normalized) === 1
            || str_contains($normalized, 'toc');
    }

    private function looksLikeNumberedHeading(string $text): bool
    {
        return preg_match('/^\s*\d+(?:\.\d+){0,5}\.?\s+[\p{L}]/u', $text) === 1
            || preg_match('/^\s*[IVXLCDM]+\.\s+[\p{L}]/iu', $text) === 1
            || preg_match('/^\s*(kapitel|chapter)\s+\d+\b/iu', $text) === 1;
    }

    private function numberedHeadingLevel(string $text): int
    {
        if (preg_match('/^\s*(\d+(?:\.\d+)*)(?:\.)?\s+[\p{L}]/u', $text, $matches) === 1) {
            $segments = explode('.', (string) ($matches[1] ?? ''));
            $segments = array_values(array_filter($segments, fn (string $segment): bool => trim($segment) !== ''));

            return max(1, min(9, count($segments)));
        }

        return 1;
    }

    private function looksLikeTocEntry(string $text): bool
    {
        $value = trim($text);
        if ($value === '') {
            return false;
        }

        if (preg_match('/\.{2,}\s*\d+(?:\s*[-–]\s*\d+)?\s*$/u', $value) === 1) {
            return true;
        }

        if (preg_match('/^\s*\d+(?:\.\d+){0,5}\.?\s+.+\s+\d+(?:\s*[-–]\s*\d+)?\s*$/u', $value) === 1) {
            return true;
        }

        if (preg_match('/\s-\s-\s/u', $value) === 1) {
            return true;
        }

        return false;
    }

    private function knownSectionType(string $title): ?string
    {
        $patterns = [
            'abstract' => '/^\s*(abstract|zusammenfassung)\b/iu',
            'foreword' => '/^\s*(vorwort|preface)\b/iu',
            'table_of_contents' => '/^\s*(inhaltsverzeichnis|table of contents)\b/iu',
            'chapter' => '/^\s*((einleitung|introduction|fazit|schluss(?:folgerung)?|res[üu]mee|conclusion)\s*(?:$|[:\-–]\s*[^.!?]{0,120}$)|(kapitel|chapter)\s+\d+\b)/iu',
            'bibliography' => '/^\s*(literaturverzeichnis|quellenverzeichnis|references|bibliography)\b/iu',
            'figure_index' => '/^\s*(abbildungsverzeichnis|list of figures)\b/iu',
            'consent_declaration' => '/^\s*(einverst[aä]ndniserkl[aä]rung|einverstaendniserklaerung|eigenst[aä]ndigkeitserkl[aä]rung|ehrenw[oö]rtliche erkl[aä]rung)\b/iu',
        ];

        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $title) === 1) {
                return $type;
            }
        }

        return null;
    }

    /**
     * @return array{
     *   text:string,
     *   outline:array<int, array<string,mixed>>,
     *   toc_lines:array<int, int>,
     *   metrics:array<string,mixed>
     * }
     */
    private function parseHtmlDocument(string $html): array
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $loaded = @$dom->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        if (! $loaded) {
            return [
                'text' => $this->normalizeText(strip_tags($html)),
                'outline' => [],
                'toc_lines' => [],
                'metrics' => [
                    'block_count' => 0,
                ],
            ];
        }

        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query('//body//h1|//body//h2|//body//h3|//body//h4|//body//h5|//body//h6|//body//p|//body//li');
        if (! $nodes) {
            return [
                'text' => '',
                'outline' => [],
                'toc_lines' => [],
                'metrics' => [
                    'block_count' => 0,
                ],
            ];
        }

        $lines = [];
        $outline = [];
        $tocLines = [];
        $lineNumber = 0;

        foreach ($nodes as $node) {
            $text = trim((string) $node->textContent);
            $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
            if ($text === '') {
                continue;
            }

            $lineNumber++;
            $lines[] = $text;

            $tagName = strtolower((string) $node->nodeName);
            if (preg_match('/^h([1-6])$/', $tagName, $matches) === 1) {
                $outline[] = [
                    'line_number' => $lineNumber,
                    'title' => $text,
                    'level' => (int) $matches[1],
                    'source' => 'html_heading',
                    'is_toc' => false,
                    'section_type' => $this->knownSectionType($text),
                ];
            } elseif ($this->knownSectionType($text) !== null) {
                $outline[] = [
                    'line_number' => $lineNumber,
                    'title' => $text,
                    'level' => 1,
                    'source' => 'html_keyword',
                    'is_toc' => false,
                    'section_type' => $this->knownSectionType($text),
                ];
            }

            if ($this->looksLikeTocEntry($text)) {
                $tocLines[] = $lineNumber;
            }
        }

        return [
            'text' => $this->normalizeText(implode("\n", $lines)),
            'outline' => $outline,
            'toc_lines' => $tocLines,
            'metrics' => [
                'block_count' => count($lines),
            ],
        ];
    }

    private function extractFromPdf(string $absolutePath): string
    {
        $commands = [
            ['pdftotext', '-enc', 'UTF-8', '-layout', $absolutePath, '-'],
            ['pdftotext', '-enc', 'UTF-8', $absolutePath, '-'],
            ['mutool', 'draw', '-F', 'txt', $absolutePath],
        ];

        foreach ($commands as $command) {
            $output = $this->runProcess($command, 120);
            $normalized = $this->normalizeText($output);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }

    private function extractWithLibreOffice(string $absolutePath): string
    {
        $tempDirectory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'aba_lo_'.bin2hex(random_bytes(6));
        if (! @mkdir($tempDirectory, 0775, true) && ! is_dir($tempDirectory)) {
            return '';
        }

        try {
            $process = new Process([
                'soffice',
                '--headless',
                '--convert-to',
                'txt:Text',
                '--outdir',
                $tempDirectory,
                $absolutePath,
            ]);

            $process->setTimeout(120);
            $process->run();
            if (! $process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $sourceName = pathinfo($absolutePath, PATHINFO_FILENAME);
            $outputPath = $tempDirectory.DIRECTORY_SEPARATOR.$sourceName.'.txt';
            if (! is_file($outputPath)) {
                $files = glob($tempDirectory.DIRECTORY_SEPARATOR.'*.txt');
                $outputPath = isset($files[0]) && is_string($files[0]) ? $files[0] : '';
            }

            if ($outputPath === '' || ! is_file($outputPath)) {
                return '';
            }

            return $this->extractFromTextFile($outputPath);
        } catch (\Throwable) {
            return '';
        } finally {
            $this->cleanupDirectory($tempDirectory);
        }
    }

    /**
     * @param  array<int, string>  $command
     */
    private function runProcess(array $command, int $timeoutSeconds): string
    {
        try {
            $process = new Process($command);
            $process->setTimeout($timeoutSeconds);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            return (string) $process->getOutput();
        } catch (\Throwable) {
            return '';
        }
    }

    private function normalizeText(string $value): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $value);
        $text = preg_replace('/\x{FEFF}/u', '', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function guessExtension(AbaAttachment $attachment, string $pathOrName): string
    {
        $candidates = [
            (string) ($attachment->stored_name ?? ''),
            (string) ($attachment->original_name ?? ''),
            (string) $pathOrName,
        ];

        foreach ($candidates as $candidate) {
            $extension = strtolower(trim((string) pathinfo($candidate, PATHINFO_EXTENSION)));
            if ($extension !== '') {
                return $extension;
            }
        }

        return '';
    }

    private function cleanupDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);
        if ($entries !== false) {
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $path = $directory.DIRECTORY_SEPARATOR.$entry;
                if (is_dir($path)) {
                    $this->cleanupDirectory($path);
                } else {
                    @unlink($path);
                }
            }
        }

        @rmdir($directory);
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function logDebug(string $message, array $context = []): void
    {
        try {
            Log::channel('aba-run-debug')->debug($message, $context);
        } catch (\Throwable) {
            Log::debug($message, $context);
        }
    }
}
