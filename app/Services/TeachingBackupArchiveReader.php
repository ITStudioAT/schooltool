<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use JsonException;
use RuntimeException;
use Throwable;
use ZipArchive;

class TeachingBackupArchiveReader
{
    public const MAX_ENTRIES = 1_000;

    public const MAX_MANIFEST_BYTES = 256 * 1024;

    public const MAX_TABLE_BYTES = 8 * 1024 * 1024;

    public const MAX_TOTAL_TABLE_BYTES = 32 * 1024 * 1024;

    public const MAX_FILE_BYTES = 100 * 1024 * 1024;

    public const MAX_TOTAL_UNCOMPRESSED_BYTES = 256 * 1024 * 1024;

    public const MAX_JSON_LINE_BYTES = 1024 * 1024;

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function readStorage(string $diskName, string $path): array
    {
        $stream = Storage::disk($diskName)->readStream($path);

        if ($stream === false) {
            throw new JsonException('Backup file could not be read.');
        }

        try {
            $temporaryPath = $this->copyToTemporaryPath($stream);
        } finally {
            fclose($stream);
        }

        try {
            if (! $this->isZipPath($temporaryPath)) {
                return $this->readPath($temporaryPath);
            }

            return $this->readZip($temporaryPath, [
                'disk' => $diskName,
                'path' => $path,
            ]);
        } finally {
            @unlink($temporaryPath);
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function readPath(string $path): array
    {
        $stream = fopen($path, 'rb');

        if ($stream === false) {
            throw new JsonException('Backup file could not be read.');
        }

        $magic = fread($stream, 4);
        fclose($stream);

        if (is_string($magic) && str_starts_with($magic, "PK\x03\x04")) {
            return $this->readZip($path, [
                'source_path' => $path,
            ]);
        }

        $content = file_get_contents($path);

        if (! is_string($content) || trim($content) === '') {
            throw new JsonException('Backup file is empty.');
        }

        $payload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($payload)) {
            throw new JsonException('Backup file does not contain a JSON object.');
        }

        if (($payload['meta']['format_version'] ?? null) !== 1) {
            throw new JsonException('Raw JSON backups must use the legacy version one format.');
        }

        return $payload;
    }

    public function isZipPath(string $path): bool
    {
        $stream = @fopen($path, 'rb');

        if ($stream === false) {
            return false;
        }

        $magic = fread($stream, 4);
        fclose($stream);

        return is_string($magic) && str_starts_with($magic, "PK\x03\x04");
    }

    /**
     * @param  array<string, mixed>  $file
     *
     * @throws JsonException
     */
    public function copyFileToStorage(array $file, string $targetDisk, string $targetPath): void
    {
        $archivePath = null;
        $deleteArchivePath = false;

        if (is_string($file['_archive_source_path'] ?? null)) {
            $archivePath = $file['_archive_source_path'];
        } elseif (is_string($file['_archive_disk'] ?? null) && is_string($file['_archive_path'] ?? null)) {
            $archiveStream = Storage::disk($file['_archive_disk'])->readStream($file['_archive_path']);

            if ($archiveStream === false) {
                throw new JsonException('Backup archive could not be read while restoring a file.');
            }

            try {
                $archivePath = $this->copyToTemporaryPath($archiveStream);
                $deleteArchivePath = true;
            } finally {
                fclose($archiveStream);
            }
        }

        if (! is_string($archivePath) || ! is_string($file['_archive_entry'] ?? null)) {
            throw new JsonException('Backup file stream metadata is invalid.');
        }

        try {
            $this->copyVerifiedArchiveEntry(
                $archivePath,
                $file['_archive_entry'],
                (string) ($file['_archive_sha256'] ?? ''),
                (int) ($file['size_bytes'] ?? -1),
                $targetDisk,
                $targetPath,
            );
        } finally {
            if ($deleteArchivePath) {
                @unlink($archivePath);
            }
        }
    }

    /**
     * @param  array{disk?:string,path?:string,source_path?:string}  $source
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function readZip(string $path, array $source): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new JsonException('The ZIP extension is required to read this backup.');
        }

        $archive = new ZipArchive;

        if ($archive->open($path, ZipArchive::CHECKCONS) !== true) {
            throw new JsonException('Backup archive is invalid.');
        }

        try {
            $entries = $this->validateArchiveEntries($archive);
            $manifest = $this->manifest($archive, $entries);
            $tables = $this->tables($archive, $manifest, $entries);
            $files = $this->files($archive, $manifest, $entries, $source);
            $expectedEntries = collect($manifest['tables'])
                ->pluck('entry')
                ->merge(collect($manifest['files'])->pluck('entry')->filter())
                ->push('manifest.json')
                ->unique()
                ->sort()
                ->values()
                ->all();
            $actualEntries = collect(array_keys($entries))->sort()->values()->all();

            if ($actualEntries !== $expectedEntries) {
                throw new JsonException('Backup archive contains unexpected entries.');
            }

            $meta = $manifest['meta'];
            $meta['format_version'] = TeachingBackupArchiveWriter::FORMAT_VERSION;
            $meta['content_hash'] = $manifest['content_hash'];

            return [
                'meta' => $meta,
                'tables' => $tables,
                'files' => $files,
            ];
        } finally {
            $archive->close();
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     *
     * @throws JsonException
     */
    private function validateArchiveEntries(ZipArchive $archive): array
    {
        if ($archive->numFiles < 1 || $archive->numFiles > self::MAX_ENTRIES) {
            throw new JsonException('Backup archive contains an invalid number of entries.');
        }

        $entries = [];
        $totalSize = 0;
        $totalTableSize = 0;

        for ($index = 0; $index < $archive->numFiles; $index++) {
            $statistics = $archive->statIndex($index);

            if (! is_array($statistics) || ! is_string($statistics['name'] ?? null)) {
                throw new JsonException('Backup archive entry metadata is invalid.');
            }

            $name = $statistics['name'];
            $this->assertSafeEntryName($name);

            if (isset($entries[$name])) {
                throw new JsonException('Backup archive contains duplicate entries.');
            }

            $size = (int) ($statistics['size'] ?? -1);

            if (
                $size < 0
                || $size > self::MAX_FILE_BYTES
                || (int) ($statistics['encryption_method'] ?? ZipArchive::EM_NONE) !== ZipArchive::EM_NONE
            ) {
                throw new JsonException('Backup archive entry has invalid size or encryption metadata.');
            }

            $totalSize += $size;

            if ($totalSize > self::MAX_TOTAL_UNCOMPRESSED_BYTES) {
                throw new JsonException('Backup archive is too large when decompressed.');
            }

            if (str_starts_with($name, 'tables/')) {
                $totalTableSize += $size;

                if ($totalTableSize > self::MAX_TOTAL_TABLE_BYTES) {
                    throw new JsonException('Backup archive table data is too large when decompressed.');
                }
            }

            $entries[$name] = $statistics;
        }

        return $entries;
    }

    /**
     * @param  array<string, array<string, mixed>>  $entries
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function manifest(ZipArchive $archive, array $entries): array
    {
        if (! isset($entries['manifest.json']) || (int) $entries['manifest.json']['size'] > self::MAX_MANIFEST_BYTES) {
            throw new JsonException('Backup archive manifest is missing or too large.');
        }

        $content = $archive->getFromName('manifest.json');

        if (! is_string($content)) {
            throw new JsonException('Backup archive manifest could not be read.');
        }

        $manifest = json_decode($content, true, 128, JSON_THROW_ON_ERROR);

        if (
            ! is_array($manifest)
            || ($manifest['format'] ?? null) !== 'schooltool-teaching-backup'
            || ($manifest['format_version'] ?? null) !== TeachingBackupArchiveWriter::FORMAT_VERSION
            || ! is_array($manifest['meta'] ?? null)
            || ! is_array($manifest['tables'] ?? null)
            || ! is_array($manifest['files'] ?? null)
            || ! is_string($manifest['content_hash'] ?? null)
        ) {
            throw new JsonException('Backup archive manifest is invalid.');
        }

        $contentHash = $manifest['content_hash'];
        unset($manifest['content_hash']);
        $actualHash = hash(
            'sha256',
            json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );

        if (! hash_equals($contentHash, $actualHash)) {
            throw new JsonException('Backup archive manifest hash does not match.');
        }

        $manifest['content_hash'] = $contentHash;

        return $manifest;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, array<string, mixed>>  $entries
     * @return array<string, array<int, array<string, mixed>>>
     *
     * @throws JsonException
     */
    private function tables(ZipArchive $archive, array $manifest, array $entries): array
    {
        $tableNames = array_keys($manifest['tables']);
        $requiredTableNames = TeachingBackupArchiveWriter::TABLE_NAMES;
        sort($tableNames);
        sort($requiredTableNames);

        if ($tableNames !== $requiredTableNames) {
            throw new JsonException('Backup archive table list is invalid.');
        }

        $tables = [];

        foreach (TeachingBackupArchiveWriter::TABLE_NAMES as $tableName) {
            $descriptor = $manifest['tables'][$tableName] ?? null;
            $expectedEntry = "tables/{$tableName}.jsonl";

            if (
                ! is_array($descriptor)
                || ($descriptor['entry'] ?? null) !== $expectedEntry
                || ! isset($entries[$expectedEntry])
                || (int) $entries[$expectedEntry]['size'] > self::MAX_TABLE_BYTES
            ) {
                throw new JsonException("Backup table {$tableName} is invalid.");
            }

            $stream = $archive->getStream($expectedEntry);

            if ($stream === false) {
                throw new JsonException("Backup table {$tableName} could not be read.");
            }

            try {
                $tables[$tableName] = $this->readJsonLines($stream, $descriptor, $tableName);
            } finally {
                fclose($stream);
            }
        }

        return $tables;
    }

    /**
     * @param  resource  $stream
     * @param  array<string, mixed>  $descriptor
     * @return array<int, array<string, mixed>>
     *
     * @throws JsonException
     */
    private function readJsonLines($stream, array $descriptor, string $tableName): array
    {
        $rows = [];
        $hash = hash_init('sha256');
        $sizeBytes = 0;

        while (($line = fgets($stream, self::MAX_JSON_LINE_BYTES + 1)) !== false) {
            if (strlen($line) > self::MAX_JSON_LINE_BYTES || ! str_ends_with($line, "\n")) {
                throw new JsonException("Backup table {$tableName} contains an invalid row.");
            }

            $row = json_decode(rtrim($line, "\r\n"), true, 128, JSON_THROW_ON_ERROR);

            if (! is_array($row)) {
                throw new JsonException("Backup table {$tableName} contains a non-object row.");
            }

            hash_update($hash, $line);
            $sizeBytes += strlen($line);
            $rows[] = $row;
        }

        if (
            count($rows) !== (int) ($descriptor['row_count'] ?? -1)
            || $sizeBytes !== (int) ($descriptor['size_bytes'] ?? -1)
            || ! is_string($descriptor['sha256'] ?? null)
            || ! hash_equals($descriptor['sha256'], hash_final($hash))
        ) {
            throw new JsonException("Backup table {$tableName} integrity check failed.");
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, array<string, mixed>>  $entries
     * @param  array{disk?:string,path?:string,source_path?:string}  $source
     * @return array<int, array<string, mixed>>
     *
     * @throws JsonException
     */
    private function files(ZipArchive $archive, array $manifest, array $entries, array $source): array
    {
        $files = [];
        $logicalPaths = [];

        foreach ($manifest['files'] as $descriptor) {
            if (! is_array($descriptor) || ! is_string($descriptor['path'] ?? null)) {
                throw new JsonException('Backup file descriptor is invalid.');
            }

            $this->assertSafeLogicalPath($descriptor['path']);

            if (isset($logicalPaths[$descriptor['path']])) {
                throw new JsonException('Backup archive contains duplicate logical file paths.');
            }

            $logicalPaths[$descriptor['path']] = true;
            $exists = ($descriptor['exists'] ?? false) === true;

            if (! $exists) {
                if (($descriptor['entry'] ?? null) !== null || ($descriptor['sha256'] ?? null) !== null) {
                    throw new JsonException('Missing backup file descriptor is invalid.');
                }

                $files[] = [
                    'path' => $descriptor['path'],
                    'storage' => null,
                    'exists' => false,
                    'mime_type' => $descriptor['mime_type'] ?? null,
                    'size_bytes' => null,
                ];

                continue;
            }

            $sha256 = $descriptor['sha256'] ?? null;
            $entry = $descriptor['entry'] ?? null;

            if (
                ! is_string($sha256)
                || preg_match('/\A[a-f0-9]{64}\z/', $sha256) !== 1
                || $entry !== "files/{$sha256}"
                || ! isset($entries[$entry])
                || (int) $entries[$entry]['size'] !== (int) ($descriptor['size_bytes'] ?? -1)
            ) {
                throw new JsonException('Backup file descriptor integrity metadata is invalid.');
            }

            $stream = $archive->getStream($entry);

            if ($stream === false) {
                throw new JsonException('Backup file could not be read.');
            }

            $this->verifyFileStream($stream, $sha256, (int) $descriptor['size_bytes']);

            $files[] = [
                'path' => $descriptor['path'],
                'storage' => 'archive',
                'exists' => true,
                'mime_type' => $descriptor['mime_type'] ?? null,
                'size_bytes' => (int) $descriptor['size_bytes'],
                '_archive_entry' => $entry,
                '_archive_sha256' => $sha256,
                '_archive_disk' => $source['disk'] ?? null,
                '_archive_path' => $source['path'] ?? null,
                '_archive_source_path' => $source['source_path'] ?? null,
            ];
        }

        return $files;
    }

    /**
     * @param  resource  $stream
     *
     * @throws JsonException
     */
    private function verifyFileStream($stream, string $expectedHash, int $expectedSize): void
    {
        $hash = hash_init('sha256');
        $size = 0;

        try {
            while (! feof($stream)) {
                $chunk = fread($stream, 1024 * 1024);

                if ($chunk === false) {
                    throw new JsonException('Backup file could not be streamed.');
                }

                if ($chunk === '') {
                    continue;
                }

                $size += strlen($chunk);

                if ($size > self::MAX_FILE_BYTES) {
                    throw new JsonException('Backup file exceeds the configured size limit.');
                }

                hash_update($hash, $chunk);
            }
        } finally {
            fclose($stream);
        }

        if ($size !== $expectedSize || ! hash_equals($expectedHash, hash_final($hash))) {
            throw new JsonException('Backup file integrity check failed.');
        }
    }

    /**
     * @throws JsonException
     */
    private function copyVerifiedArchiveEntry(
        string $archivePath,
        string $entry,
        string $expectedHash,
        int $expectedSize,
        string $targetDisk,
        string $targetPath,
    ): void {
        if (
            preg_match('/\Afiles\/[a-f0-9]{64}\z/', $entry) !== 1
            || preg_match('/\A[a-f0-9]{64}\z/', $expectedHash) !== 1
            || $expectedSize < 0
            || $expectedSize > self::MAX_FILE_BYTES
        ) {
            throw new JsonException('Backup file stream metadata is invalid.');
        }

        $archive = new ZipArchive;

        if ($archive->open($archivePath, ZipArchive::CHECKCONS) !== true) {
            throw new JsonException('Backup archive could not be opened while restoring a file.');
        }

        $temporaryStream = tmpfile();

        if ($temporaryStream === false) {
            $archive->close();

            throw new JsonException('A temporary restore stream could not be created.');
        }

        try {
            $entryStream = $archive->getStream($entry);

            if ($entryStream === false) {
                throw new JsonException('Backup file entry could not be opened.');
            }

            $hash = hash_init('sha256');
            $size = 0;

            try {
                while (! feof($entryStream)) {
                    $chunk = fread($entryStream, 1024 * 1024);

                    if ($chunk === false) {
                        throw new JsonException('Backup file entry could not be streamed.');
                    }

                    if ($chunk === '') {
                        continue;
                    }

                    $size += strlen($chunk);

                    if ($size > self::MAX_FILE_BYTES || fwrite($temporaryStream, $chunk) !== strlen($chunk)) {
                        throw new JsonException('Backup file entry exceeds the restore limits.');
                    }

                    hash_update($hash, $chunk);
                }
            } finally {
                fclose($entryStream);
            }

            if ($size !== $expectedSize || ! hash_equals($expectedHash, hash_final($hash)) || ! rewind($temporaryStream)) {
                throw new JsonException('Backup file entry integrity check failed.');
            }

            $partPath = "{$targetPath}.part-".bin2hex(random_bytes(8));

            try {
                if (
                    ! Storage::disk($targetDisk)->put($partPath, $temporaryStream)
                    || ! Storage::disk($targetDisk)->move($partPath, $targetPath)
                ) {
                    throw new JsonException('Restored backup file could not be stored.');
                }
            } finally {
                Storage::disk($targetDisk)->delete($partPath);
            }
        } finally {
            fclose($temporaryStream);
            $archive->close();
        }
    }

    /**
     * @throws JsonException
     */
    private function assertSafeEntryName(string $name): void
    {
        if (
            $name === ''
            || str_contains($name, "\0")
            || str_contains($name, '\\')
            || str_starts_with($name, '/')
            || preg_match('/\A[A-Za-z]:/', $name) === 1
            || in_array('..', explode('/', $name), true)
            || str_ends_with($name, '/')
        ) {
            throw new JsonException('Backup archive contains an unsafe entry name.');
        }
    }

    /**
     * @throws JsonException
     */
    private function assertSafeLogicalPath(string $path): void
    {
        if (
            trim($path) === ''
            || str_contains($path, "\0")
            || str_contains($path, '\\')
            || str_starts_with($path, '/')
            || preg_match('/\A[A-Za-z]:/', $path) === 1
            || in_array('..', explode('/', $path), true)
        ) {
            throw new JsonException('Backup contains an unsafe logical file path.');
        }
    }

    /**
     * @param  resource  $source
     */
    private function copyToTemporaryPath($source): string
    {
        $path = tempnam(sys_get_temp_dir(), 'schooltool-teaching-backup-read-');

        if (! is_string($path)) {
            throw new RuntimeException('A temporary backup file could not be created.');
        }

        $target = fopen($path, 'w+b');

        if ($target === false) {
            @unlink($path);

            throw new RuntimeException('A temporary backup file could not be opened.');
        }

        try {
            if (stream_copy_to_stream($source, $target) === false) {
                throw new RuntimeException('The backup stream could not be copied.');
            }
        } catch (Throwable $exception) {
            fclose($target);
            @unlink($path);

            throw $exception;
        }

        fclose($target);

        return $path;
    }
}
