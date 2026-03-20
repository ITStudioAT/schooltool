<?php

namespace App\ABA\Services;

use Illuminate\Support\Facades\File;

/**
 * Liest den vollständigen Inhalt von aba-knowledge-seed-report.md.
 *
 * Gibt sowohl den kompletten Rohinhalt als auch den Body-Bereich
 * (ohne YAML-Frontmatter) sowie den Frontmatter-Block separat zurück.
 *
 * @return array{
 *   found: bool,
 *   full_content: string|null,
 *   body_content: string|null,
 *   frontmatter_raw: string|null,
 *   line_count: int,
 *   body_line_count: int,
 *   char_count: int,
 *   error: string|null
 * }
 */
class ReadSeedReportContent
{
    private string $filePath;

    public function __construct()
    {
        $this->filePath = base_path('ai/knowledge/aba/sources/aba-knowledge-seed-report.md');
    }

    public function read(): array
    {
        if (! File::exists($this->filePath)) {
            return $this->notFound();
        }

        try {
            $raw = File::get($this->filePath);

            $frontmatterRaw = $this->extractFrontmatterRaw($raw);
            $body = $this->extractBody($raw);

            return [
                'found' => true,
                'full_content' => $raw,
                'body_content' => $body,
                'frontmatter_raw' => $frontmatterRaw,
                'line_count' => substr_count($raw, "\n") + 1,
                'body_line_count' => $body !== '' ? substr_count($body, "\n") + 1 : 0,
                'char_count' => mb_strlen($raw),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'found' => true,
                'full_content' => null,
                'body_content' => null,
                'frontmatter_raw' => null,
                'line_count' => 0,
                'body_line_count' => 0,
                'char_count' => 0,
                'error' => 'Datei konnte nicht gelesen werden: '.$e->getMessage(),
            ];
        }
    }

    private function extractFrontmatterRaw(string $content): ?string
    {
        if (preg_match('/^---\r?\n(.*?)\r?\n---/s', $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function extractBody(string $content): string
    {
        $body = preg_replace('/^---\r?\n.*?\r?\n---\r?\n/s', '', $content) ?? $content;

        return trim($body);
    }

    private function notFound(): array
    {
        return [
            'found' => false,
            'full_content' => null,
            'body_content' => null,
            'frontmatter_raw' => null,
            'line_count' => 0,
            'body_line_count' => 0,
            'char_count' => 0,
            'error' => null,
        ];
    }
}
