<?php

namespace App\ABA\Services;

use Illuminate\Support\Facades\File;

/**
 * Liest Metadaten und Vorschau aus aba-knowledge-seed-report.md.
 *
 * Parst den YAML-Frontmatter-Block und gibt die ersten Inhaltszeilen
 * als Preview zurück. Robust bei fehlender oder beschädigter Datei.
 *
 * @return array{
 *   found: bool,
 *   path: string,
 *   size_bytes: int|null,
 *   modified_at: string|null,
 *   meta: array<string, string>,
 *   preview: string,
 *   error: string|null
 * }
 */
class ReadSeedReportMeta
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
            $stat = stat($this->filePath);

            return [
                'found' => true,
                'path' => 'ai/knowledge/aba/sources/aba-knowledge-seed-report.md',
                'size_bytes' => $stat ? (int) $stat['size'] : null,
                'modified_at' => $stat ? date('Y-m-d H:i:s', $stat['mtime']) : null,
                'meta' => $this->parseFrontmatter($raw),
                'preview' => $this->extractPreview($raw),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'found' => true,
                'path' => 'ai/knowledge/aba/sources/aba-knowledge-seed-report.md',
                'size_bytes' => null,
                'modified_at' => null,
                'meta' => [],
                'preview' => '',
                'error' => 'Datei konnte nicht gelesen werden: '.$e->getMessage(),
            ];
        }
    }

    /** @return array<string, string> */
    private function parseFrontmatter(string $content): array
    {
        if (! preg_match('/^---\r?\n(.*?)\r?\n---/s', $content, $matches)) {
            return [];
        }

        $block = $matches[1];
        $result = [];

        foreach (explode("\n", $block) as $line) {
            if (! preg_match('/^(\w+):\s*(.+)$/', trim($line), $pair)) {
                continue;
            }
            $key = $pair[1];
            $value = trim($pair[2], '"\'');
            $result[$key] = $value;
        }

        return $result;
    }

    private function extractPreview(string $content, int $maxLength = 1200): string
    {
        // Strip YAML frontmatter
        $body = preg_replace('/^---\r?\n.*?\r?\n---\r?\n/s', '', $content) ?? $content;

        return mb_substr(trim($body), 0, $maxLength);
    }

    private function notFound(): array
    {
        return [
            'found' => false,
            'path' => 'ai/knowledge/aba/sources/aba-knowledge-seed-report.md',
            'size_bytes' => null,
            'modified_at' => null,
            'meta' => [],
            'preview' => '',
            'error' => 'Hauptdatei nicht gefunden.',
        ];
    }
}
