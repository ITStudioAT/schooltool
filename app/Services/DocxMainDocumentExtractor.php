<?php

namespace App\Services;

use App\Models\AbaAttachment;

class DocxMainDocumentExtractor
{
    public function __construct(
        private readonly AbaLocalDocumentTextExtractor $textExtractor,
        private readonly AbaLocalDocumentStructureExtractor $structureExtractor,
    ) {}

    public function supports(AbaAttachment $attachment): bool
    {
        $extension = $this->resolveExtension($attachment);
        $mimeType = mb_strtolower(trim((string) ($attachment->mime_type ?? '')));

        return in_array($extension, ['docx', 'docm', 'dotx'], true)
            || in_array($mimeType, [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-word.document.macroenabled.12',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
            ], true);
    }

    /**
     * @return array<string,mixed>
     */
    public function extract(AbaAttachment $attachment): array
    {
        if (! $this->supports($attachment)) {
            throw new \RuntimeException('Für die ABA-Extraktion wird derzeit ein DOCX-Hauptdokument benötigt.');
        }

        $document = $this->textExtractor->extractDocument($attachment);
        $text = trim((string) ($document['text'] ?? ''));
        if ($text === '') {
            throw new \RuntimeException('Aus dem Hauptdokument konnte kein auswertbarer Text extrahiert werden.');
        }

        $sections = $this->structureExtractor->extractSections($text, [
            'outline' => is_array($document['outline'] ?? null) ? $document['outline'] : [],
            'toc_lines' => is_array($document['toc_lines'] ?? null) ? $document['toc_lines'] : [],
            'selected_candidate' => $document['selected_candidate'] ?? null,
            'extraction_candidates' => is_array($document['candidates'] ?? null) ? $document['candidates'] : [],
        ]);
        if ($sections === []) {
            $sections = [$this->fallbackSection($text)];
        }

        return [
            'document' => [
                'source_original_name' => $attachment->original_name,
                'source_mime_type' => $attachment->mime_type,
                'selected_candidate' => $document['selected_candidate'] ?? null,
                'path_type' => is_array($document['metadata'] ?? null) ? ($document['metadata']['path_type'] ?? null) : null,
                'text_length' => mb_strlen($text),
                'text_length_without_spaces' => $this->textLengthWithoutSpaces($text),
                'page_image_counts' => is_array($document['metadata']['page_image_counts'] ?? null)
                    ? $document['metadata']['page_image_counts']
                    : [],
                'total_image_count' => (int) ($document['metadata']['total_image_count'] ?? 0),
                'image_count_method' => $document['metadata']['image_count_method'] ?? null,
            ],
            'text' => $text,
            'outline' => is_array($document['outline'] ?? null) ? array_values($document['outline']) : [],
            'toc_lines' => is_array($document['toc_lines'] ?? null) ? array_values($document['toc_lines']) : [],
            'candidates' => is_array($document['candidates'] ?? null) ? array_values($document['candidates']) : [],
            'diagnostics' => $this->structureExtractor->lastDiagnostics(),
            'sections' => array_values($sections),
        ];
    }

    private function resolveExtension(AbaAttachment $attachment): string
    {
        foreach ([
            (string) ($attachment->stored_name ?? ''),
            (string) ($attachment->original_name ?? ''),
            (string) ($attachment->path ?? ''),
        ] as $candidate) {
            $extension = mb_strtolower(trim((string) pathinfo($candidate, PATHINFO_EXTENSION)));
            if ($extension !== '') {
                return $extension;
            }
        }

        return '';
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
            'metadata' => ['fallback' => true],
        ];
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
