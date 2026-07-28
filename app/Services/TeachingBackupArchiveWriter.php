<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use JsonException;
use RuntimeException;
use Throwable;
use ZipArchive;

class TeachingBackupArchiveWriter
{
    private const MAX_ENTRIES = TeachingBackupArchiveReader::MAX_ENTRIES;

    private const MAX_TABLE_BYTES = TeachingBackupArchiveReader::MAX_TABLE_BYTES;

    private const MAX_FILE_BYTES = TeachingBackupArchiveReader::MAX_FILE_BYTES;

    private const MAX_TOTAL_UNCOMPRESSED_BYTES = TeachingBackupArchiveReader::MAX_TOTAL_UNCOMPRESSED_BYTES;

    private const MAX_JSON_LINE_BYTES = TeachingBackupArchiveReader::MAX_JSON_LINE_BYTES;

    public const FORMAT_VERSION = 2;

    public const TABLE_NAMES = [
        'schools',
        'schoolyears',
        'school_tools',
        'users',
        'teaching_courses',
        'teaching_course_students',
        'teaching_course_dates',
        'teaching_course_date_materials',
        'teaching_course_date_material_attachments',
        'teaching_course_works',
        'teaching_course_work_group_students',
        'teaching_course_student_entries',
        'teaching_course_behaviour_entries',
        'teaching_course_student_category_evaluations',
        'teaching_curricula',
        'teaching_curriculum_documents',
        'teaching_imported_curricula',
        'teaching_schemas',
        'teaching_holidays',
        'teaching_school_hours',
        'import116',
        'import116_runs',
        'import116_run_changes',
        'user_groups',
        'user_group_members',
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function write(array $payload, string $diskName, string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The ZIP extension is required to create teaching backups.');
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'schooltool-teaching-backup-');

        if (! is_string($temporaryPath)) {
            throw new RuntimeException('A temporary teaching backup file could not be created.');
        }

        $archive = new ZipArchive;
        $archiveIsOpen = false;
        $temporaryStreams = [];
        $partPath = "{$path}.part-".bin2hex(random_bytes(8));

        try {
            if ($archive->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('The teaching backup archive could not be opened.');
            }

            $archiveIsOpen = true;
            $tableManifest = $this->addTables($archive, $payload['tables'] ?? [], $temporaryStreams);
            $fileManifest = $this->addFiles($archive, $payload['files'] ?? [], $temporaryStreams);
            $uniqueFileEntries = collect($fileManifest)->pluck('entry')->filter()->unique();
            $totalUncompressedBytes = collect($tableManifest)->sum('size_bytes')
                + $uniqueFileEntries->sum(function (string $entry) use ($fileManifest): int {
                    return (int) (collect($fileManifest)->firstWhere('entry', $entry)['size_bytes'] ?? 0);
                });

            if (
                count($tableManifest) + $uniqueFileEntries->count() + 1 > self::MAX_ENTRIES
                || collect($tableManifest)->sum('size_bytes') > TeachingBackupArchiveReader::MAX_TOTAL_TABLE_BYTES
                || $totalUncompressedBytes > self::MAX_TOTAL_UNCOMPRESSED_BYTES
            ) {
                throw new RuntimeException('The teaching backup archive exceeds the configured limits.');
            }

            $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
            $meta['format_version'] = self::FORMAT_VERSION;

            $manifest = [
                'format' => 'schooltool-teaching-backup',
                'format_version' => self::FORMAT_VERSION,
                'meta' => $meta,
                'tables' => $tableManifest,
                'files' => $fileManifest,
            ];
            $manifest['content_hash'] = hash(
                'sha256',
                json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            );

            $archive->addFromString(
                'manifest.json',
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            );

            $archiveClosed = $archive->close();
            $archiveIsOpen = false;

            if (! $archiveClosed) {
                throw new RuntimeException('The teaching backup archive could not be finalized.');
            }

            $stream = fopen($temporaryPath, 'rb');

            if ($stream === false) {
                throw new RuntimeException('The teaching backup archive could not be read.');
            }

            try {
                if (! Storage::disk($diskName)->put($partPath, $stream)) {
                    throw new RuntimeException('The teaching backup archive could not be stored.');
                }
            } finally {
                fclose($stream);
            }

            if (! Storage::disk($diskName)->move($partPath, $path)) {
                throw new RuntimeException('The teaching backup archive could not be finalized in storage.');
            }

            return [
                'format_version' => self::FORMAT_VERSION,
                'backup_created_at' => $meta['created_at'] ?? null,
                'scope' => $meta['scope'] ?? null,
                'content_hash' => $manifest['content_hash'],
                'validation' => [
                    'status' => collect($fileManifest)->contains(fn (array $file): bool => ! $file['exists'])
                        ? 'warning'
                        : 'valid',
                    'is_valid' => true,
                    'issues' => [],
                    'warnings' => $this->missingFileWarnings($fileManifest),
                ],
                'table_counts' => collect($tableManifest)
                    ->map(fn (array $table): int => $table['row_count'])
                    ->all(),
                'total_rows' => collect($tableManifest)->sum('row_count'),
                'file_count' => count($fileManifest),
                'missing_file_count' => collect($fileManifest)->where('exists', false)->count(),
                'container_format' => 'zip',
            ];
        } catch (Throwable $exception) {
            if ($archiveIsOpen) {
                $archive->close();
            }

            Storage::disk($diskName)->delete([$partPath, $path]);

            throw $exception;
        } finally {
            foreach ($temporaryStreams as $temporaryStream) {
                if (is_resource($temporaryStream)) {
                    fclose($temporaryStream);
                }
            }

            @unlink($temporaryPath);
        }
    }

    /**
     * @param  array<string, mixed>  $tables
     * @param  array<int, resource>  $temporaryStreams
     * @return array<string, array{entry:string,row_count:int,size_bytes:int,sha256:string}>
     */
    private function addTables(ZipArchive $archive, array $tables, array &$temporaryStreams): array
    {
        $manifest = [];

        foreach (self::TABLE_NAMES as $tableName) {
            $stream = tmpfile();

            if ($stream === false) {
                throw new RuntimeException('A temporary table stream could not be created.');
            }

            $temporaryStreams[] = $stream;
            $hash = hash_init('sha256');
            $rowCount = 0;
            $sizeBytes = 0;

            foreach ($tables[$tableName] ?? [] as $row) {
                $line = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";

                if (
                    strlen($line) > self::MAX_JSON_LINE_BYTES
                    || $sizeBytes + strlen($line) > self::MAX_TABLE_BYTES
                    || fwrite($stream, $line) !== strlen($line)
                ) {
                    throw new RuntimeException("The {$tableName} backup stream could not be written.");
                }

                hash_update($hash, $line);
                $sizeBytes += strlen($line);
                $rowCount++;
            }

            $streamPath = stream_get_meta_data($stream)['uri'] ?? null;
            $entry = "tables/{$tableName}.jsonl";

            if (! is_string($streamPath) || ! $archive->addFile($streamPath, $entry)) {
                throw new RuntimeException("The {$tableName} table could not be added to the archive.");
            }

            $manifest[$tableName] = [
                'entry' => $entry,
                'row_count' => $rowCount,
                'size_bytes' => $sizeBytes,
                'sha256' => hash_final($hash),
            ];
        }

        return $manifest;
    }

    /**
     * @param  array<int, array<string, mixed>>  $files
     * @param  array<int, resource>  $temporaryStreams
     * @return array<int, array<string, mixed>>
     */
    private function addFiles(ZipArchive $archive, array $files, array &$temporaryStreams): array
    {
        $manifest = [];
        $archiveEntries = [];

        foreach ($files as $file) {
            $logicalPath = (string) ($file['path'] ?? '');
            $this->assertSafeLogicalPath($logicalPath);
            $entry = [
                'path' => $logicalPath,
                'exists' => (bool) ($file['exists'] ?? false),
                'mime_type' => $file['mime_type'] ?? null,
                'size_bytes' => $file['size_bytes'] ?? null,
                'entry' => null,
                'sha256' => null,
            ];

            if (! $entry['exists']) {
                $manifest[] = $entry;

                continue;
            }

            $sourceStream = $this->sourceStream($file);
            $temporaryStream = tmpfile();

            if ($sourceStream === false || $temporaryStream === false) {
                if (is_resource($sourceStream)) {
                    fclose($sourceStream);
                }

                throw new RuntimeException("The backup file {$logicalPath} could not be streamed.");
            }

            $temporaryStreams[] = $temporaryStream;
            $hash = hash_init('sha256');
            $sizeBytes = 0;

            try {
                while (! feof($sourceStream)) {
                    $chunk = fread($sourceStream, 1024 * 1024);

                    if ($chunk === false) {
                        throw new RuntimeException("The backup file {$logicalPath} could not be read.");
                    }

                    if ($chunk === '') {
                        continue;
                    }

                    if (fwrite($temporaryStream, $chunk) !== strlen($chunk)) {
                        throw new RuntimeException("The backup file {$logicalPath} could not be buffered.");
                    }

                    hash_update($hash, $chunk);
                    $sizeBytes += strlen($chunk);

                    if ($sizeBytes > self::MAX_FILE_BYTES) {
                        throw new RuntimeException("The backup file {$logicalPath} exceeds the configured size limit.");
                    }
                }
            } finally {
                fclose($sourceStream);
            }

            $sha256 = hash_final($hash);
            $archiveEntry = "files/{$sha256}";
            $temporaryPath = stream_get_meta_data($temporaryStream)['uri'] ?? null;

            if (! isset($archiveEntries[$archiveEntry])) {
                if (! is_string($temporaryPath) || ! $archive->addFile($temporaryPath, $archiveEntry)) {
                    throw new RuntimeException("The backup file {$logicalPath} could not be added to the archive.");
                }

                $archiveEntries[$archiveEntry] = true;
            }

            $entry['entry'] = $archiveEntry;
            $entry['sha256'] = $sha256;
            $entry['size_bytes'] = $sizeBytes;
            $manifest[] = $entry;
        }

        return $manifest;
    }

    /**
     * @param  array<string, mixed>  $file
     * @return resource|false
     */
    private function sourceStream(array $file)
    {
        if (is_string($file['_absolute_path'] ?? null)) {
            return fopen($file['_absolute_path'], 'rb');
        }

        if (is_string($file['_source_disk'] ?? null) && is_string($file['_source_path'] ?? null)) {
            return Storage::disk($file['_source_disk'])->readStream($file['_source_path']);
        }

        if (is_string($file['base64'] ?? null)) {
            $decoded = base64_decode($file['base64'], true);

            if (! is_string($decoded)) {
                return false;
            }

            $stream = fopen('php://temp', 'w+b');

            if ($stream === false || fwrite($stream, $decoded) !== strlen($decoded) || ! rewind($stream)) {
                return false;
            }

            return $stream;
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $files
     * @return array<int, string>
     */
    private function missingFileWarnings(array $files): array
    {
        $missing = collect($files)->where('exists', false)->count();

        return $missing > 0
            ? ["{$missing} referenzierte Datei(en) konnten nicht eingebettet werden."]
            : [];
    }

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
            throw new RuntimeException('Teaching backup contains an unsafe logical file path.');
        }
    }
}
