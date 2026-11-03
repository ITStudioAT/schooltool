<?php

namespace App\Services;

use App\Models\DropboxConnection;

class DropboxFolderContents
{
    public function __construct(private DropboxWorkImport $dropbox) {}

    /** @return array{name: string, bytes: string} */
    public function pdf(DropboxConnection $connection, string $folderId, string $fileId): array
    {
        $folder = $this->dropbox->metadata($connection, $folderId);
        $metadata = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $fileId])->json();
        abort_unless(($metadata['.tag'] ?? null) === 'file' && ($metadata['id'] ?? null) === $fileId
            && is_string($metadata['path_lower'] ?? null) && dirname($metadata['path_lower']) === $folder['path_lower']
            && is_string($metadata['name'] ?? null) && preg_match('/\.pdf\z/i', $metadata['name'])
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
