<?php

namespace App\Services;

use App\Models\AbaAttachment;
use Shipfastlabs\Parsel;

class AbaParselDocumentExtractor
{
    public function __construct(
        private readonly AbaLocalDocumentTextExtractor $textExtractor,
        private readonly AbaLocalDocumentStructureExtractor $structureExtractor,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function extract(AbaAttachment $attachment): array
    {
        $prepared = $this->textExtractor->prepareLocalFile($attachment);
        $absolutePath = $prepared['absolute_path'];
        $isTemp = $prepared['is_temp'];

        try {
            $this->ensureLibreOfficeIsReachable();

            $text = $this->normalizeText(
                Parsel::file($absolutePath)
                    ->withoutOcr()
                    ->withTimeout(120)
                    ->text()
            );
        } finally {
            if ($isTemp) {
                @unlink($absolutePath);
            }
        }

        if ($text === '') {
            throw new \RuntimeException('Parsel konnte keinen auswertbaren Text extrahieren.');
        }

        $sections = $this->structureExtractor->extractSections($text);
        if ($sections === []) {
            $sections = [$this->fallbackSection($text)];
        }

        return [
            'document' => [
                'source_original_name' => $attachment->original_name,
                'source_mime_type' => $attachment->mime_type,
                'parser' => 'shipfastlabs/parsel',
                'path_type' => 'parsel_text',
                'text_length' => mb_strlen($text),
                'text_length_without_spaces' => $this->textLengthWithoutSpaces($text),
                'page_image_counts' => [],
                'total_image_count' => 0,
                'image_count_method' => null,
            ],
            'text' => $text,
            'outline' => [],
            'toc_lines' => [],
            'candidates' => [[
                'id' => 'parsel_text',
                'status' => 'ok',
                'metrics' => [
                    'text_length' => mb_strlen($text),
                    'text_length_without_spaces' => $this->textLengthWithoutSpaces($text),
                ],
                'selection_reason' => 'parsel_text',
            ]],
            'diagnostics' => $this->structureExtractor->lastDiagnostics(),
            'sections' => array_values($sections),
            'title_page_processing' => [],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function fallbackSection(string $text): array
    {
        $lines = preg_split('/\R/u', $text) ?: [];

        return [
            'section_key' => 'document-body',
            'parent_key' => null,
            'section_type' => 'other_section',
            'section_title' => 'Dokument',
            'extracted_text' => $text,
            'hierarchy_level' => 1,
            'start_line' => 1,
            'end_line' => max(1, count($lines)),
            'start_page' => null,
            'end_page' => null,
            'anchor' => ['line_start' => 1],
            'metadata' => ['fallback' => true, 'parser' => 'shipfastlabs/parsel'],
        ];
    }

    private function normalizeText(string $value): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $value);
        $text = preg_replace('/\x{FEFF}/u', '', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function ensureLibreOfficeIsReachable(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return;
        }

        $programDirectories = [
            'C:\\Program Files\\LibreOffice\\program',
            'C:\\Program Files (x86)\\LibreOffice\\program',
        ];

        foreach ($programDirectories as $programDirectory) {
            if (! is_file($programDirectory.'\\soffice.exe')) {
                continue;
            }

            $path = (string) getenv('PATH');
            if (str_contains($path, $programDirectory)) {
                return;
            }

            putenv("PATH={$programDirectory};{$path}");
            $_SERVER['PATH'] = "{$programDirectory};".(string) ($_SERVER['PATH'] ?? $path);
            $_ENV['PATH'] = "{$programDirectory};".(string) ($_ENV['PATH'] ?? $path);

            return;
        }
    }

    private function textLengthWithoutSpaces(string $text): int
    {
        $normalized = preg_replace('/\s+/u', '', $text);
        if (! is_string($normalized)) {
            return 0;
        }

        return mb_strlen($normalized);
    }
}
