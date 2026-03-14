<?php

namespace App\ABA\Services;

use Illuminate\Support\Facades\File;

class SaveSeedProposalEdits
{
    private string $proposalsDir;

    private const MIN_NON_EMPTY_LINES = 10;

    /** @var array<int, string> */
    private const EDITABLE_PREFIXES = [
        'seed-replacement-draft-',
        'seed-editorial-cleanup-',
        'seed-proposal-',
    ];

    public function __construct()
    {
        $this->proposalsDir = base_path('ai/knowledge/aba/sources/proposals');
    }

    /**
     * @return array{
     *   success: bool,
     *   filename: string|null,
     *   modified_at: string|null,
     *   line_count: int,
     *   error: string|null,
     *   message: string|null
     * }
     */
    public function save(string $filename, string $content): array
    {
        $filename = basename($filename);

        if (! preg_match('/^[a-zA-Z0-9_\-]+\.md$/', $filename)) {
            return $this->fail('Ungültiger Dateiname.');
        }

        if (! $this->isEditableFilename($filename)) {
            return $this->fail('Diese Datei ist nicht bearbeitbar. Nur echte Vorschläge können bearbeitet werden.');
        }

        $path = $this->proposalsDir.'/'.$filename;

        if (! File::exists($path)) {
            return $this->fail('Vorschlagsdatei nicht gefunden.');
        }

        if (! $this->isPathInsideProposalsDirectory($path)) {
            return $this->fail('Ungültiger Dateipfad.');
        }

        $normalizedContent = str_replace(["\r\n", "\r"], "\n", $content);

        $validationError = $this->validateContent($filename, $normalizedContent);
        if ($validationError !== null) {
            return $this->fail($validationError);
        }

        File::put($path, $normalizedContent);

        return [
            'success' => true,
            'filename' => $filename,
            'modified_at' => date('c', (int) File::lastModified($path)),
            'line_count' => substr_count($normalizedContent, "\n") + 1,
            'error' => null,
            'message' => 'Vorschlag erfolgreich gespeichert.',
        ];
    }

    private function isEditableFilename(string $filename): bool
    {
        foreach (self::EDITABLE_PREFIXES as $prefix) {
            if (str_starts_with($filename, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function isPathInsideProposalsDirectory(string $path): bool
    {
        $proposalsRealPath = realpath($this->proposalsDir);
        $fileRealPath = realpath($path);

        if ($proposalsRealPath === false || $fileRealPath === false) {
            return false;
        }

        return $fileRealPath === $proposalsRealPath
            || str_starts_with($fileRealPath, $proposalsRealPath.DIRECTORY_SEPARATOR);
    }

    private function validateContent(string $filename, string $content): ?string
    {
        if (trim($content) === '') {
            return 'Leerer Inhalt kann nicht gespeichert werden.';
        }

        $nonEmptyLineCount = count(array_filter(
            explode("\n", $content),
            fn (string $line): bool => trim($line) !== '',
        ));

        if ($nonEmptyLineCount < self::MIN_NON_EMPTY_LINES) {
            return 'Der Vorschlag wirkt unvollständig (zu wenige Inhaltszeilen).';
        }

        if (! preg_match('/^#{1,6}\s+/m', $content)) {
            return 'Der Vorschlag enthält keine gültige Überschrift.';
        }

        if (str_starts_with($filename, 'seed-replacement-draft-')) {
            if (! preg_match('/^---\R.*?\R---\R/s', $content)) {
                return 'Der Vorschlag enthält kein gültiges YAML-Frontmatter.';
            }

            if (! str_contains($content, 'draft_type: seed-replacement')) {
                return 'Der Vorschlag enthält keinen gültigen draft_type für die Übernahme.';
            }
        }

        return null;
    }

    /**
     * @return array{
     *   success: bool,
     *   filename: string|null,
     *   modified_at: string|null,
     *   line_count: int,
     *   error: string,
     *   message: string|null
     * }
     */
    private function fail(string $error): array
    {
        return [
            'success' => false,
            'filename' => null,
            'modified_at' => null,
            'line_count' => 0,
            'error' => $error,
            'message' => null,
        ];
    }
}
