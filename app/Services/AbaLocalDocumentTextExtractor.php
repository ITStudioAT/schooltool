<?php

namespace App\Services;

use App\Models\AbaAttachment;
use App\Support\DiagnosticLogContextSanitizer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class AbaLocalDocumentTextExtractor
{
    public function __construct(
        private readonly AbaDocxPageMapper $pageMapper,
        private readonly AbaDocxPageImageCounter $pageImageCounter,
        private readonly AbaDocumentRuleService $documentRuleService,
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
        $pageImageMap = $this->pageImageCounter->countByPage($absolutePath);

        return [
            'text' => $text,
            'selected_candidate' => is_string($selected['id'] ?? null) ? $selected['id'] : null,
            'candidates' => $this->reduceCandidateSummaries($candidates),
            'outline' => is_array($selected['outline'] ?? null) ? array_values($selected['outline']) : [],
            'toc_lines' => is_array($selected['toc_lines'] ?? null) ? array_values(array_unique(array_map('intval', $selected['toc_lines']))) : [],
            'metadata' => [
                'path_type' => 'docx_hybrid',
                'selected_score' => (int) ($selected['score'] ?? 0),
                'page_image_counts' => is_array($pageImageMap['page_image_counts'] ?? null)
                    ? $pageImageMap['page_image_counts']
                    : [],
                'total_image_count' => (int) ($pageImageMap['total_image_count'] ?? 0),
                'image_count_method' => $pageImageMap['method'] ?? null,
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
            $blocks = $xpath->query('//w:body//*[self::w:tbl or (self::w:p and not(ancestor::w:tbl))]');
            if (! $blocks) {
                $candidate['status'] = 'empty';
                $candidate['metrics'] = ['reason' => 'content_blocks_not_found'];

                return $candidate;
            }

            $numberingDefinitions = $this->extractWordNumberingDefinitions($zip);
            $lines = [];
            $outline = [];
            $tocLines = [];
            $lineNumber = 0;
            $numberingState = [];

            foreach ($blocks as $block) {
                $localName = mb_strtolower((string) ($block->localName ?? $block->nodeName));
                if ($localName === 'tbl') {
                    $this->appendWordTableLines($xpath, $block, $lines, $lineNumber);

                    continue;
                }

                if ($localName !== 'p') {
                    continue;
                }

                $this->appendWordParagraphLine(
                    xpath: $xpath,
                    paragraph: $block,
                    lines: $lines,
                    outline: $outline,
                    tocLines: $tocLines,
                    lineNumber: $lineNumber,
                    numberingDefinitions: $numberingDefinitions,
                    numberingState: $numberingState,
                );
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
     * @param  array<int, string>  $lines
     * @param  array<int, array<string,mixed>>  $outline
     * @param  array<int, int>  $tocLines
     * @param  array<string,mixed>  $numberingDefinitions
     * @param  array<string,array<int,int>>  $numberingState
     */
    private function appendWordParagraphLine(
        \DOMXPath $xpath,
        \DOMNode $paragraph,
        array &$lines,
        array &$outline,
        array &$tocLines,
        int &$lineNumber,
        array $numberingDefinitions,
        array &$numberingState,
    ): void {
        $rawText = trim($this->extractTextFromWordParagraph($xpath, $paragraph));
        if ($rawText === '') {
            return;
        }

        $lineNumber++;
        $styleValue = trim((string) $xpath->evaluate('string(w:pPr/w:pStyle/@w:val)', $paragraph));
        $outlineLevelValue = trim((string) $xpath->evaluate('string(w:pPr/w:outlineLvl/@w:val)', $paragraph));
        $listInfo = $this->resolveParagraphListInfo($xpath, $paragraph, $numberingDefinitions, $numberingState);
        $listLevel = is_numeric($listInfo['level'] ?? null) ? (int) ($listInfo['level'] ?? null) : null;
        $listPrefix = is_string($listInfo['prefix'] ?? null) ? trim((string) ($listInfo['prefix'] ?? null)) : null;
        $hasHeadingStyle = $this->paragraphUsesHeadingStyle($styleValue, $outlineLevelValue);
        $preserveAsListLine = $listLevel !== null && ! $hasHeadingStyle;
        $text = $preserveAsListLine
            ? $this->applyListPrefixToText($rawText, $listLevel, $listPrefix)
            : $rawText;
        $alignment = $this->resolveParagraphAlignment($xpath, $paragraph);
        $indentLeftTwips = $this->resolveParagraphIndentLeftTwips($xpath, $paragraph);
        $spacingBeforeTwips = $this->resolveParagraphSpacingTwips($xpath, $paragraph, 'before');
        $spacingAfterTwips = $this->resolveParagraphSpacingTwips($xpath, $paragraph, 'after');
        $fontSizePt = $this->resolveParagraphFontSizePt($xpath, $paragraph);
        $isBold = $this->paragraphHasBoldRun($xpath, $paragraph);
        $headingLevel = $this->resolveWordHeadingLevel(
            styleValue: $styleValue,
            outlineLevelValue: $outlineLevelValue,
            text: $rawText,
            allowNumericFallback: ! $preserveAsListLine,
        );
        $knownType = $preserveAsListLine ? null : $this->knownSectionType($rawText);
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
        } elseif (
            ! $preserveAsListLine
            && ! $isTocLine
            && $this->paragraphIsEntirelyBold($xpath, $paragraph)
            && $this->looksLikeBoldSectionMarkerText($rawText)
        ) {
            $outlineSource = 'bold_marker';
            $headingLevel = 3;
        }

        if ($outlineSource !== null) {
            $entry = [
                'line_number' => $lineNumber,
                'title' => $rawText,
                'level' => $headingLevel,
                'source' => $outlineSource,
                'is_toc' => $isTocLine,
                'section_type' => $knownType,
            ];
            if ($styleValue !== '') {
                $entry['style'] = $styleValue;
            }
            if ($alignment !== null) {
                $entry['alignment'] = $alignment;
            }
            if ($indentLeftTwips !== null) {
                $entry['indent_left_twips'] = $indentLeftTwips;
            }
            if ($spacingBeforeTwips !== null) {
                $entry['spacing_before_twips'] = $spacingBeforeTwips;
            }
            if ($spacingAfterTwips !== null) {
                $entry['spacing_after_twips'] = $spacingAfterTwips;
            }
            if ($fontSizePt !== null) {
                $entry['font_size_pt'] = $fontSizePt;
            }
            $entry['is_bold'] = $isBold;

            $outline[] = $entry;
        }

        $lines[] = $text;
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function appendWordTableLines(\DOMXPath $xpath, \DOMNode $table, array &$lines, int &$lineNumber): void
    {
        $rows = [];
        foreach ($xpath->query('./w:tr', $table) ?: [] as $rowNode) {
            $cells = [];
            foreach ($xpath->query('./w:tc', $rowNode) ?: [] as $cellNode) {
                $cellParts = [];
                foreach ($xpath->query('.//w:p', $cellNode) ?: [] as $cellParagraph) {
                    $cellText = trim($this->extractTextFromWordParagraph($xpath, $cellParagraph));
                    if ($cellText !== '') {
                        $cellParts[] = $cellText;
                    }
                }

                $cellValue = trim(implode(' ', $cellParts));
                $cellValue = preg_replace('/\s+/u', ' ', $cellValue) ?? $cellValue;
                $cells[] = trim($cellValue);
            }

            $nonEmptyCells = array_values(array_filter($cells, fn (string $cell): bool => $cell !== ''));
            if ($nonEmptyCells === []) {
                continue;
            }

            $rows[] = $cells;
        }

        if ($rows === []) {
            return;
        }

        foreach ($rows as $index => $row) {
            $lineNumber++;
            $lines[] = $this->buildMarkdownTableLine($row);

            if ($index === 0) {
                $lineNumber++;
                $lines[] = $this->buildMarkdownTableSeparatorLine(count($row));
            }
        }
    }

    /**
     * @param  array<int, string>  $cells
     */
    private function buildMarkdownTableLine(array $cells): string
    {
        $normalizedCells = array_map(function (string $cell): string {
            $value = trim($cell);
            if ($value === '') {
                return ' ';
            }

            return str_replace('|', '\|', $value);
        }, $cells);

        return '| '.implode(' | ', $normalizedCells).' |';
    }

    private function buildMarkdownTableSeparatorLine(int $columnCount): string
    {
        $columns = max(1, $columnCount);

        return '|'.implode('|', array_fill(0, $columns, '---')).'|';
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
            if ($this->nodeIsInsideMarkupCompatibilityFallback($node)) {
                continue;
            }

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

    /**
     * @param  array<string,mixed>  $numberingDefinitions
     * @param  array<string,array<int,int>>  $numberingState
     * @return array{level:int,prefix:string|null}|null
     */
    private function resolveParagraphListInfo(
        \DOMXPath $xpath,
        \DOMNode $paragraph,
        array $numberingDefinitions,
        array &$numberingState,
    ): ?array {
        $numId = trim((string) $xpath->evaluate('string(w:pPr/w:numPr/w:numId/@w:val)', $paragraph));
        if ($numId === '') {
            return null;
        }

        $levelValue = trim((string) $xpath->evaluate('string(w:pPr/w:numPr/w:ilvl/@w:val)', $paragraph));
        if (! is_numeric($levelValue)) {
            $level = 0;
        } else {
            $level = max(0, min(8, (int) $levelValue));
        }

        return [
            'level' => $level,
            'prefix' => $this->resolveWordListPrefix($numId, $level, $numberingDefinitions, $numberingState),
        ];
    }

    private function paragraphUsesHeadingStyle(string $styleValue, string $outlineLevelValue): bool
    {
        if ($outlineLevelValue !== '' && is_numeric($outlineLevelValue)) {
            return true;
        }

        $styleNormalized = mb_strtolower(trim($styleValue));
        if ($styleNormalized === '') {
            return false;
        }

        return preg_match('/(?:heading|überschrift|ueberschrift)\s*[1-9]/iu', $styleNormalized) === 1
            || preg_match('/^h[1-9]$/iu', $styleNormalized) === 1;
    }

    private function applyListPrefixToText(string $text, int $listLevel, ?string $prefix = null): string
    {
        $value = trim($text);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^\s*(?:[-*•·◦▪▫]|(?:\d+|[a-z]|[ivxlcdm]+)[\.\)])\s+/iu', $value) === 1) {
            return $value;
        }

        $resolvedPrefix = trim((string) $prefix);
        if ($resolvedPrefix !== '') {
            return str_repeat('  ', max(0, min(8, $listLevel))).$resolvedPrefix.' '.$value;
        }

        return str_repeat('  ', max(0, min(8, $listLevel))).'- '.$value;
    }

    /**
     * @return array{
     *   abstract_nums:array<string, array<int, array{start:int,num_fmt:string,lvl_text:string}>>,
     *   nums:array<string, array{abstract_num_id:string,levels:array<int, array{start:int,num_fmt:string,lvl_text:string}>}>
     * }
     */
    private function extractWordNumberingDefinitions(\ZipArchive $zip): array
    {
        $definitions = [
            'abstract_nums' => [],
            'nums' => [],
        ];

        $xml = $zip->getFromName('word/numbering.xml');
        if (! is_string($xml) || trim($xml) === '') {
            return $definitions;
        }

        $document = new \DOMDocument('1.0', 'UTF-8');
        if (! @$document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            return $definitions;
        }

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        foreach ($xpath->query('//w:abstractNum') ?: [] as $abstractNumNode) {
            $abstractNumId = trim((string) $xpath->evaluate('string(@w:abstractNumId)', $abstractNumNode));
            if ($abstractNumId === '') {
                continue;
            }

            $levels = [];
            foreach ($xpath->query('./w:lvl', $abstractNumNode) ?: [] as $levelNode) {
                $levelIndex = trim((string) $xpath->evaluate('string(@w:ilvl)', $levelNode));
                if (! is_numeric($levelIndex)) {
                    continue;
                }

                $levels[(int) $levelIndex] = [
                    'start' => $this->resolveWordNumberingStartValue($xpath, $levelNode, 'string(w:start/@w:val)'),
                    'num_fmt' => trim((string) $xpath->evaluate('string(w:numFmt/@w:val)', $levelNode)) ?: 'decimal',
                    'lvl_text' => trim((string) $xpath->evaluate('string(w:lvlText/@w:val)', $levelNode)),
                ];
            }

            if ($levels !== []) {
                $definitions['abstract_nums'][$abstractNumId] = $levels;
            }
        }

        foreach ($xpath->query('//w:num') ?: [] as $numNode) {
            $numId = trim((string) $xpath->evaluate('string(@w:numId)', $numNode));
            if ($numId === '') {
                continue;
            }

            $levels = [];
            foreach ($xpath->query('./w:lvlOverride', $numNode) ?: [] as $overrideNode) {
                $levelIndex = trim((string) $xpath->evaluate('string(@w:ilvl)', $overrideNode));
                if (! is_numeric($levelIndex)) {
                    continue;
                }

                $level = (int) $levelIndex;
                $start = $this->resolveWordNumberingStartValue($xpath, $overrideNode, 'string(w:startOverride/@w:val)');
                $numFmt = trim((string) $xpath->evaluate('string(w:lvl/w:numFmt/@w:val)', $overrideNode));
                $lvlText = trim((string) $xpath->evaluate('string(w:lvl/w:lvlText/@w:val)', $overrideNode));

                if ($start === 1 && $numFmt === '' && $lvlText === '') {
                    continue;
                }

                $levels[$level] = [
                    'start' => $start,
                    'num_fmt' => $numFmt !== '' ? $numFmt : 'decimal',
                    'lvl_text' => $lvlText,
                ];
            }

            $definitions['nums'][$numId] = [
                'abstract_num_id' => trim((string) $xpath->evaluate('string(w:abstractNumId/@w:val)', $numNode)),
                'levels' => $levels,
            ];
        }

        return $definitions;
    }

    private function resolveWordNumberingStartValue(\DOMXPath $xpath, \DOMNode $node, string $expression): int
    {
        $startValue = trim((string) $xpath->evaluate($expression, $node));

        return is_numeric($startValue) ? max(1, (int) $startValue) : 1;
    }

    /**
     * @param  array<string,mixed>  $numberingDefinitions
     * @param  array<string,array<int,int>>  $numberingState
     */
    private function resolveWordListPrefix(
        string $numId,
        int $level,
        array $numberingDefinitions,
        array &$numberingState,
    ): ?string {
        $numDefinition = is_array($numberingDefinitions['nums'][$numId] ?? null)
            ? $numberingDefinitions['nums'][$numId]
            : null;
        $levelDefinition = $this->resolveWordNumberingLevelDefinition($numDefinition, $level, $numberingDefinitions);
        if (! is_array($levelDefinition)) {
            return null;
        }

        $numberingState[$numId] = is_array($numberingState[$numId] ?? null)
            ? $numberingState[$numId]
            : [];

        $start = max(1, (int) ($levelDefinition['start'] ?? 1));
        $currentValue = $numberingState[$numId][$level] ?? null;
        $numberingState[$numId][$level] = is_numeric($currentValue)
            ? ((int) $currentValue + 1)
            : $start;

        foreach (array_keys($numberingState[$numId]) as $trackedLevel) {
            if ((int) $trackedLevel > $level) {
                unset($numberingState[$numId][$trackedLevel]);
            }
        }

        $levelText = trim((string) ($levelDefinition['lvl_text'] ?? ''));
        if ($levelText === '') {
            $segments = [];
            for ($index = 0; $index <= $level; $index++) {
                if (! isset($numberingState[$numId][$index])) {
                    continue;
                }

                $segments[] = (string) $numberingState[$numId][$index];
            }

            $fallback = implode('.', $segments);

            return $fallback !== '' ? $fallback.'.' : null;
        }

        $resolved = preg_replace_callback(
            '/%(\d+)/',
            function (array $matches) use ($numDefinition, $numberingDefinitions, $numberingState, $numId): string {
                $placeholderLevel = max(0, ((int) ($matches[1] ?? 1)) - 1);
                if (! isset($numberingState[$numId][$placeholderLevel])) {
                    return '';
                }

                $placeholderDefinition = $this->resolveWordNumberingLevelDefinition(
                    $numDefinition,
                    $placeholderLevel,
                    $numberingDefinitions,
                );
                $numberFormat = is_array($placeholderDefinition)
                    ? (string) ($placeholderDefinition['num_fmt'] ?? 'decimal')
                    : 'decimal';

                return $this->formatWordNumberingCounter((int) $numberingState[$numId][$placeholderLevel], $numberFormat);
            },
            $levelText,
        );

        $normalized = trim((string) preg_replace('/\s+/u', ' ', $resolved ?? $levelText));

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param  array<string,mixed>|null  $numDefinition
     * @param  array<string,mixed>  $numberingDefinitions
     * @return array{start:int,num_fmt:string,lvl_text:string}|null
     */
    private function resolveWordNumberingLevelDefinition(?array $numDefinition, int $level, array $numberingDefinitions): ?array
    {
        if (is_array($numDefinition['levels'][$level] ?? null)) {
            return $numDefinition['levels'][$level];
        }

        $abstractNumId = trim((string) ($numDefinition['abstract_num_id'] ?? ''));
        if ($abstractNumId === '') {
            return null;
        }

        return is_array($numberingDefinitions['abstract_nums'][$abstractNumId][$level] ?? null)
            ? $numberingDefinitions['abstract_nums'][$abstractNumId][$level]
            : null;
    }

    private function formatWordNumberingCounter(int $value, string $format): string
    {
        $normalizedFormat = mb_strtolower(trim($format));

        return match ($normalizedFormat) {
            'upperroman', 'roman' => $this->convertToRomanNumeral($value),
            'lowerroman' => mb_strtolower($this->convertToRomanNumeral($value)),
            'upperletter', 'alpha', 'upperalpha' => $this->convertToAlphabeticCounter($value, true),
            'lowerletter', 'loweralpha' => $this->convertToAlphabeticCounter($value, false),
            default => (string) max(1, $value),
        };
    }

    private function convertToRomanNumeral(int $value): string
    {
        $number = max(1, $value);
        $map = [
            1000 => 'M',
            900 => 'CM',
            500 => 'D',
            400 => 'CD',
            100 => 'C',
            90 => 'XC',
            50 => 'L',
            40 => 'XL',
            10 => 'X',
            9 => 'IX',
            5 => 'V',
            4 => 'IV',
            1 => 'I',
        ];

        $result = '';
        foreach ($map as $arabic => $roman) {
            while ($number >= $arabic) {
                $result .= $roman;
                $number -= $arabic;
            }
        }

        return $result;
    }

    private function convertToAlphabeticCounter(int $value, bool $uppercase): string
    {
        $number = max(1, $value);
        $characters = '';

        while ($number > 0) {
            $number--;
            $characters = chr(65 + ($number % 26)).$characters;
            $number = intdiv($number, 26);
        }

        return $uppercase ? $characters : mb_strtolower($characters);
    }

    private function nodeIsInsideMarkupCompatibilityFallback(\DOMNode $node): bool
    {
        $current = $node->parentNode;
        while ($current instanceof \DOMNode) {
            if (
                $current->nodeType === XML_ELEMENT_NODE
                && $current->localName === 'Fallback'
                && $current->namespaceURI === 'http://schemas.openxmlformats.org/markup-compatibility/2006'
            ) {
                return true;
            }

            $current = $current->parentNode;
        }

        return false;
    }

    private function resolveParagraphAlignment(\DOMXPath $xpath, \DOMNode $paragraph): ?string
    {
        $alignment = mb_strtolower(trim((string) $xpath->evaluate('string(w:pPr/w:jc/@w:val)', $paragraph)));
        if ($alignment === '') {
            return null;
        }

        return match ($alignment) {
            'start', 'left' => 'left',
            'end', 'right' => 'right',
            'center', 'centre' => 'center',
            'both', 'justify', 'distribute' => 'justify',
            default => $alignment,
        };
    }

    private function resolveParagraphIndentLeftTwips(\DOMXPath $xpath, \DOMNode $paragraph): ?int
    {
        $value = trim((string) $xpath->evaluate('string(w:pPr/w:ind/@w:start)', $paragraph));
        if ($value === '') {
            $value = trim((string) $xpath->evaluate('string(w:pPr/w:ind/@w:left)', $paragraph));
        }

        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function resolveParagraphSpacingTwips(\DOMXPath $xpath, \DOMNode $paragraph, string $attribute): ?int
    {
        $value = trim((string) $xpath->evaluate('string(w:pPr/w:spacing/@w:'.$attribute.')', $paragraph));
        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function resolveParagraphFontSizePt(\DOMXPath $xpath, \DOMNode $paragraph): ?float
    {
        $sizes = [];
        foreach ($xpath->query('.//w:rPr/w:sz/@w:val|.//w:rPr/w:szCs/@w:val', $paragraph) ?: [] as $sizeNode) {
            $value = trim((string) $sizeNode->nodeValue);
            if ($value === '' || ! is_numeric($value)) {
                continue;
            }

            $sizes[] = (int) $value;
        }

        if ($sizes === []) {
            return null;
        }

        sort($sizes);
        $medianIndex = (int) floor((count($sizes) - 1) / 2);
        $medianHalfPoints = (int) ($sizes[$medianIndex] ?? 0);
        if ($medianHalfPoints <= 0) {
            return null;
        }

        return round($medianHalfPoints / 2, 1);
    }

    private function paragraphHasBoldRun(\DOMXPath $xpath, \DOMNode $paragraph): bool
    {
        $boldCount = (int) $xpath->evaluate(
            'count(.//w:rPr/w:b[not(@w:val) or @w:val="1" or @w:val="true" or @w:val="on"])',
            $paragraph
        );

        return $boldCount > 0;
    }

    /**
     * Prüft, ob alle textführenden Runs des Absatzes fett formatiert sind.
     * Konservativere Variante von paragraphHasBoldRun.
     */
    private function paragraphIsEntirelyBold(\DOMXPath $xpath, \DOMNode $paragraph): bool
    {
        $totalTextRuns = (int) $xpath->evaluate(
            'count(.//w:r[normalize-space(w:t)!=""])',
            $paragraph
        );

        if ($totalTextRuns === 0) {
            return false;
        }

        $boldTextRuns = (int) $xpath->evaluate(
            'count(.//w:r[normalize-space(w:t)!=""][w:rPr/w:b[not(@w:val) or @w:val="1" or @w:val="true" or @w:val="on"]])',
            $paragraph
        );

        return $boldTextRuns === $totalTextRuns;
    }

    /**
     * Konservative Textheuristik: Erkennt eigenständige hervorgehobene Zwischenmarker
     * (wie „Instagram" oder „Pinterest") ohne formalen DOCX-Heading-Style.
     *
     * Schlägt nicht an bei:
     * - längeren Fließtexten
     * - nummerierten Ausdrücken (Heading-Erkennung übernimmt diese)
     * - Quellenangaben, Abbildungs-/Tabellenbeschriftungen
     * - Fließtext mit Satzzeichen am Ende
     */
    private function looksLikeBoldSectionMarkerText(string $text): bool
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return false;
        }

        // Maximale Wortanzahl: konservativ auf 5 begrenzt
        $words = array_values(array_filter(preg_split('/\s+/u', $trimmed) ?: []));
        $wordCount = count($words);
        if ($wordCount === 0 || $wordCount > 5) {
            return false;
        }

        // Maximale Zeichenanzahl
        if (mb_strlen($trimmed) > 50) {
            return false;
        }

        // Kein Satzzeichen am Ende (kein Fließtextfragment)
        if (preg_match('/[.!?,;:]\s*$/u', $trimmed) === 1) {
            return false;
        }

        // Nicht mit Ziffern beginnen (nummerierte Überschriften werden anderweitig erkannt)
        if (preg_match('/^\d/u', $trimmed) === 1) {
            return false;
        }

        // Keine Quellenangabe: beginnt mit ( oder enthält typische Zitatmarker
        if (preg_match('/^\(|vgl\.|ebd\.|bzw\.|et\s+al\./iu', $trimmed) === 1) {
            return false;
        }

        // Keine Abbildungs- oder Tabellenbeschriftung
        if (preg_match('/^(Abbildung|Abb\.|Tabelle|Tab\.|Figure|Fig\.)\s+/iu', $trimmed) === 1) {
            return false;
        }

        // Muss mindestens einen Buchstaben enthalten
        if (preg_match('/\p{L}/u', $trimmed) !== 1) {
            return false;
        }

        return true;
    }

    private function resolveWordHeadingLevel(
        string $styleValue,
        string $outlineLevelValue,
        string $text,
        bool $allowNumericFallback = true
    ): ?int {
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

        if ($allowNumericFallback && $this->looksLikeNumberedHeading($text)) {
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

        return preg_match('/^(toc|inhaltsverzeichnis|verzeichnis)/iu', $normalized) === 1
            || str_contains($normalized, 'toc')
            || str_contains($normalized, 'verzeichnis');
    }

    private function looksLikeNumberedHeading(string $text): bool
    {
        return $this->matchesNumericHeadingPrefix($text)
            || preg_match('/^\s*[IVXLCDM]+\.\s+[\p{L}]/iu', $text) === 1
            || $this->looksLikeNamedChapterHeading($text);
    }

    private function matchesNumericHeadingPrefix(string $text): bool
    {
        if (preg_match('/^\s*(\d+(?:\.\d+){0,5})\.?\s+[\p{L}]/u', $text, $matches) !== 1) {
            return false;
        }

        $segments = explode('.', (string) ($matches[1] ?? ''));
        $segments = array_values(array_filter($segments, fn (string $segment): bool => trim($segment) !== ''));
        if ($segments === []) {
            return false;
        }

        $first = (int) ($segments[0] ?? 0);

        return ! (count($segments) === 1 && $first >= 100);
    }

    private function looksLikeNamedChapterHeading(string $text): bool
    {
        return $this->matchesNamedChapterHeadingStructure($text);
    }

    private function matchesNamedChapterHeadingStructure(string $text): bool
    {
        if (preg_match('/^\s*(kapitel|chapter)\s+(\d+)\s*(.*)$/iu', $text, $matches) !== 1) {
            return false;
        }

        $remainder = trim((string) ($matches[3] ?? ''));
        if ($remainder === '') {
            return true;
        }

        if (preg_match('/^[:\-–]\s*[^.!?]{1,120}$/u', $remainder) === 1) {
            return true;
        }

        if (preg_match('/^[.!?]/u', $remainder) === 1) {
            return false;
        }

        if (preg_match('/^[a-zäöü]/u', $remainder) === 1) {
            return false;
        }

        if (preg_match('/[.!?]\s*$/u', $remainder) === 1) {
            return false;
        }

        $wordCount = count(array_values(array_filter(preg_split('/\s+/u', $remainder) ?: [])));

        return $wordCount > 0 && $wordCount <= 12;
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

        if (
            preg_match('/^\s*\d+(?:\.\d+){0,5}\s+[^\n]{2,140}\D\d{1,3}(?:[-–]\d{1,3})?\s*$/u', $value) === 1
            && preg_match('/[.!?]\s*$/u', $value) !== 1
        ) {
            return true;
        }

        return false;
    }

    private function knownSectionType(string $title): ?string
    {
        $configuredType = $this->documentRuleService->resolveSectionTypeFromTitle(
            $title,
            ['abstract', 'foreword', 'table_of_contents', 'bibliography', 'figure_index', 'consent_declaration']
        );
        if ($configuredType !== null) {
            return $configuredType;
        }

        if (
            $this->documentRuleService->isConfiguredChapterHeading($title)
            || preg_match('/^\s*(?:einleitung|introduction|fazit|schluss(?:folgerung)?|res[üu]mee|conclusion)(?:\s*\/\s*(?:fazit|schluss(?:folgerung)?|res[üu]mee|conclusion))?\s*(?:$|[:\-–]\s*[^.!?]{0,120}$)/iu', $title) === 1
            || preg_match('/^\s*(?:fazit|schluss(?:folgerung)?|res[üu]mee|conclusion)\s*\/\s*(?:fazit|schluss(?:folgerung)?|res[üu]mee|conclusion)\s*$/iu', $title) === 1
            || $this->matchesNamedChapterHeadingStructure($title)
        ) {
            return 'chapter';
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
        if (! config('aba_analysis.debug_log_enabled')) {
            return;
        }

        try {
            $safeContext = app(DiagnosticLogContextSanitizer::class)->sanitize($context);
            Log::channel('aba-run-debug')->debug($message, $safeContext);
        } catch (\Throwable) {
            Log::debug($message, $safeContext ?? []);
        }
    }
}
