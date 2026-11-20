<?php

namespace App\Services;

use App\Models\DropboxConnection;
use App\Models\TeachingCourseWork;

class DropboxFolderContents
{
    public function __construct(private DropboxWorkImport $dropbox) {}

    public function assignment(DropboxConnection $connection, string $folderId): array
    {
        $root = $this->read($connection, $folderId);
        abort_if($root['cursor'], 422, 'Der Aufgabenordner ist für eine begrenzte Prüfung zu groß.');
        $locations = [$root];
        foreach ($root['entries'] as $entry) {
            if ($entry['type'] === 'folder' && in_array(mb_strtolower($entry['name']), ['angaben', 'quellunterlagen'], true)) {
                $locations[] = $this->read($connection, $folderId, $entry['id']);
            }
        }
        $tasks = [];
        foreach ($locations as $location) {
            abort_if($location['cursor'], 422, 'Die Aufgabenquelle ist nicht vollständig aufgelistet.');
            foreach ($location['entries'] as $entry) {
                if ($entry['type'] === 'file' && preg_match('/\A(?:Aufgabenstellung|Leistungsfeststellung).*\.pdf\z/ui', $entry['name'])) {
                    $tasks[] = ['entry' => $entry, 'folder' => $location['current']['id']];
                }
            }
        }
        abort_unless(count($tasks) === 1, 422, 'Bitte genau ein eindeutiges Aufgabenblatt im bestehenden Leistungsfeststellungsordner bereitstellen.');
        abort_if(($tasks[0]['entry']['size'] ?? PHP_INT_MAX) > 5 * 1024 * 1024, 422, 'Das Aufgabenblatt überschreitet 5 MB.');

        return $this->pdf($connection, $tasks[0]['folder'], $tasks[0]['entry']['id']);
    }

    /**
     * @param  array<string, mixed>  $exercise
     * @return array{name: string, bytes: string}
     */
    public function preparedPdf(DropboxModuleLinks $modules, DropboxConnection $connection,
        TeachingCourseWork $work, array $exercise, string $section, ?string $date): array
    {
        $folder = $exercise['folder'];
        $preparedFiles = $exercise['preparation']['pdf_files'] ?? null;
        $files = $preparedFiles[$section] ?? [];
        if ($preparedFiles === null) {
            $contents = $this->read($connection, $folder['id']);
            abort_unless($contents['cursor'] === null, 409, 'Die Prüfungsunterlagen konnten nicht vollständig gelesen werden.');
            $files = array_values(array_filter($contents['entries'], fn (array $entry): bool => $entry['type'] === 'file'
                && ($section === 'Angaben' ? preg_match('/\A(?:LF|Leistungsfeststellung)_.+\.pdf\z/iu', $entry['name']) : $entry['name'] === 'Beurteilung.pdf')));
        }
        if ($section === 'Angaben' && $files === []) {
            abort_if(($exercise['preparation']['components']['multiple_choice']['included'] ?? null) !== false
                || ($preparedFiles !== null && ! in_array('Angaben', $exercise['preparation']['document_sections'] ?? [], true)),
                404, 'Im verknüpften Prüfungsordner ist keine Aufgaben-PDF vorhanden.');
            abort_unless($this->dropbox->metadata($connection, $folder['id'])['path_lower'] === mb_strtolower($folder['path']),
                409, 'Der Prüfungsordner wurde verschoben. Bitte den Status erneut prüfen.');
            $shared = $modules->preparedSharedAssignment($connection, $work, $exercise, $date);
            abort_unless(is_array($shared), 409, 'Die gemeinsamen Angaben fehlen oder sind unter den persönlichen Aufgaben-PDFs nicht eindeutig.');
            $pdf = $this->pdf($connection, $shared['folder_id'], $shared['file_id'], expectedName: $shared['name']);
            $after = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $shared['file_id']])->json();
            abort_unless(($after['content_hash'] ?? null) === $shared['content_hash'] && strlen($pdf['bytes']) === $shared['size']
                && $this->dropbox->metadata($connection, $folder['id'])['path_lower'] === mb_strtolower($folder['path']),
                409, 'Das gemeinsame Aufgabenblatt wurde verändert. Bitte erneut prüfen.');

            return $pdf;
        }
        abort_if($files === [], 404, 'Dieses PDF ist im Prüfungsordner nicht vorhanden.');
        abort_if(count($files) > 1, 409, 'Im Prüfungsordner sind mehrere passende PDFs vorhanden. Bitte die Zuordnung klären.');

        return $this->pdf($connection, $folder['id'], $files[0]['id'], $folder['path'], $files[0]['name']);
    }

    /** @return array{name: string, bytes: string} */
    public function pdf(DropboxConnection $connection, string $folderId, string $fileId, ?string $expectedFolderPath = null, ?string $expectedName = null): array
    {
        $folder = $this->dropbox->metadata($connection, $folderId);
        abort_unless($expectedFolderPath === null || $folder['path_lower'] === mb_strtolower($expectedFolderPath),
            409, 'Der Prüfungsordner wurde verschoben. Bitte den Status erneut prüfen.');
        $metadata = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $fileId])->json();
        abort_unless(($metadata['.tag'] ?? null) === 'file' && ($metadata['id'] ?? null) === $fileId
            && is_string($metadata['path_lower'] ?? null) && dirname($metadata['path_lower']) === $folder['path_lower']
            && is_string($metadata['name'] ?? null) && preg_match('/\.pdf\z/i', $metadata['name'])
            && ($expectedName === null || $metadata['name'] === $expectedName)
            && ! preg_match('/[\x00-\x1f]/u', $metadata['name'])
            && is_string($metadata['rev'] ?? null) && preg_match('/\A[A-Za-z0-9]+\z/', $metadata['rev'])
            && is_int($metadata['size'] ?? null) && $metadata['size'] > 0 && $metadata['size'] <= 20971520,
            422, 'Die PDF gehört nicht zu diesem Prüfungsbereich oder ist nicht lesbar (maximal 20 MB).');
        $bytes = $this->dropbox->readModuleApi($connection, 'files/download', ['path' => 'rev:'.$metadata['rev']])->body();
        abort_unless(strlen($bytes) === $metadata['size'] && str_starts_with($bytes, '%PDF-'), 422, 'Dropbox hat keine vollständige PDF geliefert.');
        $after = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $fileId])->json();
        abort_unless(($after['rev'] ?? null) === $metadata['rev'] && ($after['path_lower'] ?? null) === $metadata['path_lower']
            && $this->dropbox->metadata($connection, $folderId)['path_lower'] === $folder['path_lower'],
            409, 'Die PDF oder ihr Ordner wurde während des Lesens geändert. Bitte erneut öffnen.');

        return ['name' => $metadata['name'], 'bytes' => $bytes];
    }

    /**
     * @param  list<string>  $excludedFolders
     * @return array<string, mixed>
     */
    public function read(DropboxConnection $connection, string $rootId, ?string $folderId = null, ?string $cursor = null, array $excludedFolders = []): array
    {
        $root = $this->dropbox->metadata($connection, $rootId);
        $current = $folderId && $folderId !== $rootId ? $this->dropbox->metadata($connection, $folderId) : $root;
        $rootPath = $root['path_lower'];
        $currentPath = $current['path_lower'];
        abort_unless($rootPath !== '' && str_starts_with($rootPath, '/')
            && ($currentPath === $rootPath || str_starts_with($currentPath, $rootPath.'/')),
            403, 'Dieser Ordner liegt außerhalb des verknüpften Arbeitsordners.');
        $excludedFolders = array_map('mb_strtolower', $excludedFolders);
        $relativePath = ltrim(substr($currentPath, strlen($rootPath)), '/');
        abort_if($relativePath !== '' && in_array(mb_strtolower(explode('/', $relativePath)[0]), $excludedFolders, true),
            403, 'Dieser Ordner gehört nicht zur angezeigten Prüfung.');

        $data = $this->dropbox->readModuleApi($connection, $cursor ? 'files/list_folder/continue' : 'files/list_folder',
            $cursor ? ['cursor' => $cursor] : ['path' => $current['id'], 'recursive' => false, 'limit' => 200])->json();
        abort_unless(is_array($data['entries'] ?? null) && is_bool($data['has_more'] ?? null)
            && (! $data['has_more'] || (is_string($data['cursor'] ?? null) && $data['cursor'] !== '')),
            422, 'Dropbox hat keine vollständige Dateiliste geliefert.');
        $entries = [];
        foreach ($data['entries'] as $entry) {
            abort_unless(is_array($entry) && in_array($entry['.tag'] ?? null, ['file', 'folder'], true)
                && is_string($entry['id'] ?? null) && preg_match('/\Aid:[A-Za-z0-9_-]+\z/', $entry['id'])
                && is_string($entry['name'] ?? null) && $entry['name'] !== ''
                && ! preg_match('/[\/\\\\\x00-\x1f]/u', $entry['name'])
                && is_string($entry['path_lower'] ?? null) && dirname($entry['path_lower']) === $currentPath,
                409, 'Der Ordnerinhalt hat sich geändert oder gehört nicht zur angezeigten Verbindung.');
            if ($currentPath === $rootPath && $entry['.tag'] === 'folder' && in_array(mb_strtolower($entry['name']), $excludedFolders, true)) {
                continue;
            }
            $entries[] = ['id' => $entry['id'], 'name' => $entry['name'], 'type' => $entry['.tag'],
                'size' => ($entry['.tag'] === 'file' && is_int($entry['size'] ?? null)) ? $entry['size'] : null];
        }
        abort_unless($this->dropbox->metadata($connection, $rootId)['path_lower'] === $rootPath
            && ($current['id'] === $rootId || $this->dropbox->metadata($connection, $current['id'])['path_lower'] === $currentPath),
            409, 'Der Arbeitsordner wurde während des Lesens verschoben. Bitte erneut öffnen.');

        $folder = fn (array $item): array => ['id' => $item['id'], 'name' => $item['name'],
            'path' => $item['path_display'] ?? $item['path_lower']];

        return ['root' => $folder($root), 'current' => $folder($current), 'entries' => $entries,
            'cursor' => $data['has_more'] ? $data['cursor'] : null];
    }
}
