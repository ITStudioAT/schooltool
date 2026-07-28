<?php

namespace App\Services;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\ImageManager;
use JsonException;
use ZipArchive;

class FileUploadService
{
    private const METADATA_FILE = 'upload.json';

    private const PART_FILE = 'file.part';

    private const LOCK_SECONDS = 15;

    private const LOCK_WAIT_SECONDS = 5;

    private const MAX_XLSX_ARCHIVE_ENTRIES = 20_000;

    private const MAX_XLSX_UNCOMPRESSED_BYTES = 536_870_912;

    private const MAX_XLSX_COMPRESSION_RATIO = 200;

    /**
     * @return array{
     *     max_bytes: int,
     *     allowed_extensions: list<string>
     * }
     */
    public function profile(string $profile, ?int $maxBytes = null): array
    {
        $profiles = config('schooltool.chunk_uploads.profiles', []);
        $configuration = is_array($profiles[$profile] ?? null) ? $profiles[$profile] : [];
        $defaultMaxKb = (int) config('schooltool.chunk_uploads.default_max_size_kb', 102400);
        $configuredMaxKb = (int) ($configuration['max_size_kb'] ?? $defaultMaxKb);
        $configuredExtensions = is_array($configuration['allowed_extensions'] ?? null)
            ? $configuration['allowed_extensions']
            : [];

        return [
            'max_bytes' => $maxBytes ?? max(1, $configuredMaxKb) * 1024,
            'allowed_extensions' => collect($configuredExtensions)
                ->filter(fn (mixed $extension): bool => is_string($extension))
                ->map(fn (string $extension): string => Str::lower(ltrim(trim($extension), '.')))
                ->filter()
                ->unique()
                ->values()
                ->all(),
        ];
    }

    public function upload(
        ?Request $request = null,
        string $profile = 'default',
        ?int $maxBytes = null,
    ): string {
        $request ??= request();
        $configuration = $this->profile($profile, $maxBytes);
        $declaredLength = $this->declaredUploadLength($request);

        if ($declaredLength !== null && $declaredLength > $configuration['max_bytes']) {
            $this->throwFileTooLarge();
        }

        $id = Str::uuid()->toString();
        $directory = $this->createUploadDirectory($id);
        $originalName = $this->normalizedOriginalName($request->header('Upload-Name'));
        $this->assertAllowedExtension($originalName, $configuration['allowed_extensions']);

        $metadata = [
            'id' => $id,
            'owner' => $this->requestOwner($request),
            'endpoint' => $this->requestEndpoint($request),
            'profile' => $profile,
            'max_bytes' => $configuration['max_bytes'],
            'allowed_extensions' => $configuration['allowed_extensions'],
            'declared_length' => $declaredLength,
            'original_name' => $originalName,
            'created_at' => now()->toIso8601String(),
            'last_activity_at' => now()->toIso8601String(),
        ];

        $bytes = $request->getContent();
        $bytes = is_string($bytes) ? $bytes : '';

        if (strlen($bytes) > $configuration['max_bytes']) {
            $this->discardUpload($directory);
            $this->throwFileTooLarge();
        }

        if ($declaredLength !== null && strlen($bytes) > $declaredLength) {
            $this->discardUpload($directory);
            $this->throwInvalidUpload('Der Upload enthält mehr Daten als angekündigt.');
        }

        $this->writeMetadata($directory, $metadata);

        if ($bytes !== '' && file_put_contents($this->partPath($directory), $bytes, LOCK_EX) === false) {
            $this->discardUpload($directory);
            abort(500, 'Der Upload konnte nicht zwischengespeichert werden.');
        }

        return $id;
    }

    /**
     * @param  array{width?: int, height?: int}|null  $fit
     */
    public function uploadNext(
        Request $request,
        string $uploadPath,
        ?string $newName = null,
        ?array $fit = null,
        string $profile = 'default',
        ?int $maxBytes = null,
    ): string|Response {
        $id = $this->validatedUploadId($request->query('patch'));
        $directory = $this->existingUploadDirectory($id);

        try {
            return Cache::lock("chunk-upload:{$id}", self::LOCK_SECONDS)
                ->block(self::LOCK_WAIT_SECONDS, function () use (
                    $request,
                    $directory,
                    $uploadPath,
                    $newName,
                    $fit,
                    $profile,
                    $maxBytes,
                ): string|Response {
                    return $this->appendChunk(
                        $request,
                        $directory,
                        $uploadPath,
                        $newName,
                        $fit,
                        $profile,
                        $maxBytes,
                    );
                });
        } catch (LockTimeoutException) {
            abort(423, 'Der Upload wird bereits verarbeitet. Bitte erneut versuchen.');
        }
    }

    public function cleanupExpiredUploads(?int $maxAgeHours = null): int
    {
        $maxAgeHours ??= (int) config('schooltool.chunk_uploads.expiry_hours', 24);
        $expiresBefore = now()->subHours(max(1, $maxAgeHours));
        $root = $this->tempRoot();

        if (! is_dir($root)) {
            return 0;
        }

        $deleted = 0;

        foreach (File::directories($root) as $directory) {
            $id = basename($directory);
            if (! $this->isValidUploadId($id) || ! $this->pathIsWithin($directory, $root)) {
                continue;
            }

            $metadata = $this->readMetadata($directory, false);
            $lastActivity = is_string($metadata['last_activity_at'] ?? null)
                ? rescue(fn () => Carbon::parse($metadata['last_activity_at']), report: false)
                : null;
            $fallbackTimestamp = filemtime($directory);
            $expired = $lastActivity
                ? $lastActivity->isBefore($expiresBefore)
                : $fallbackTimestamp !== false && $fallbackTimestamp < $expiresBefore->getTimestamp();

            if (! $expired) {
                continue;
            }

            $this->discardUpload($directory);
            $deleted++;
        }

        return $deleted;
    }

    /**
     * @param  array{width?: int, height?: int}|null  $fit
     */
    private function appendChunk(
        Request $request,
        string $directory,
        string $uploadPath,
        ?string $newName,
        ?array $fit,
        string $profile,
        ?int $maxBytes,
    ): string|Response {
        $metadata = $this->readMetadata($directory);
        $this->assertUploadAccess($request, $metadata, $profile);

        $configuration = $this->profile($profile, $maxBytes);
        $effectiveMaxBytes = min(
            (int) ($metadata['max_bytes'] ?? $configuration['max_bytes']),
            $configuration['max_bytes'],
        );
        $allowedExtensions = is_array($metadata['allowed_extensions'] ?? null)
            ? $metadata['allowed_extensions']
            : $configuration['allowed_extensions'];

        $declaredLength = $this->declaredUploadLength($request);
        $storedDeclaredLength = is_int($metadata['declared_length'] ?? null)
            ? $metadata['declared_length']
            : null;

        if ($declaredLength !== null && $declaredLength > $effectiveMaxBytes) {
            $this->discardUpload($directory);
            $this->throwFileTooLarge();
        }

        if (
            $storedDeclaredLength !== null
            && $declaredLength !== null
            && $declaredLength !== $storedDeclaredLength
        ) {
            $this->throwInvalidUpload('Die angekündigte Uploadgröße hat sich geändert.');
        }

        $metadata['declared_length'] = $storedDeclaredLength ?? $declaredLength;
        $originalName = $this->resolveOriginalName($request, $metadata);
        $this->assertAllowedExtension($originalName, $allowedExtensions);
        $metadata['original_name'] = $originalName;
        $metadata['last_activity_at'] = now()->toIso8601String();

        $bytes = $request->getContent();
        $bytes = is_string($bytes) ? $bytes : '';

        if ($bytes === '') {
            $this->writeMetadata($directory, $metadata);

            return response('NO_CONTENT', 204);
        }

        $partPath = $this->partPath($directory);
        clearstatcache(true, $partPath);
        $currentSize = is_file($partPath) ? (int) (filesize($partPath) ?: 0) : 0;
        $nextSize = $currentSize + strlen($bytes);
        $expectedSize = $metadata['declared_length'];

        if ($nextSize > $effectiveMaxBytes) {
            $this->discardUpload($directory);
            $this->throwFileTooLarge();
        }

        if (is_int($expectedSize) && $nextSize > $expectedSize) {
            $this->discardUpload($directory);
            $this->throwInvalidUpload('Der Upload enthält mehr Daten als angekündigt.');
        }

        if (file_put_contents($partPath, $bytes, FILE_APPEND | LOCK_EX) === false) {
            abort(500, 'Der Upload-Teil konnte nicht gespeichert werden.');
        }

        $this->writeMetadata($directory, $metadata);

        if (! is_int($expectedSize) || $nextSize < $expectedSize) {
            return response('OK', 200);
        }

        $this->validateCompletedFile($partPath, $originalName, $allowedExtensions);
        $request->attributes->set('upload_original_name', $originalName);

        $destinationDirectory = $this->destinationDirectory($uploadPath);
        $destinationName = $this->destinationName($originalName, $newName);
        $destinationPath = $this->joinPath($destinationDirectory, $destinationName);

        $this->moveFile($partPath, $destinationPath);

        if ($fit !== null) {
            $this->resizeImage($destinationPath, $fit);
        }

        $this->discardUpload($directory);

        return $destinationName;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function assertUploadAccess(Request $request, array $metadata, string $profile): void
    {
        $storedOwner = is_array($metadata['owner'] ?? null) ? $metadata['owner'] : [];
        $currentOwner = $this->requestOwner($request);
        $storedUserId = $storedOwner['user_id'] ?? null;
        $storedSessionHash = $storedOwner['session_hash'] ?? null;

        if ($storedUserId !== null && ! hash_equals((string) $storedUserId, (string) ($currentOwner['user_id'] ?? ''))) {
            abort(403, 'Dieser Upload gehört einem anderen Benutzer.');
        }

        if (
            $storedUserId === null
            && $storedSessionHash !== null
            && ! hash_equals((string) $storedSessionHash, (string) ($currentOwner['session_hash'] ?? ''))
        ) {
            abort(403, 'Dieser Upload gehört einer anderen Sitzung.');
        }

        if (! hash_equals((string) ($metadata['endpoint'] ?? ''), $this->requestEndpoint($request))) {
            abort(403, 'Der Upload wurde für einen anderen Endpunkt gestartet.');
        }

        if (! hash_equals((string) ($metadata['profile'] ?? ''), $profile)) {
            abort(403, 'Der Upload wurde mit einem anderen Dateiprofil gestartet.');
        }
    }

    /**
     * @return array{user_id: ?string, session_hash: ?string}
     */
    private function requestOwner(Request $request): array
    {
        $userId = $request->user()?->getAuthIdentifier();
        $sessionHash = null;

        if ($userId === null && $request->hasSession()) {
            $sessionId = $request->session()->getId();
            $sessionHash = $sessionId !== '' ? hash('sha256', $sessionId) : null;
        }

        return [
            'user_id' => $userId !== null ? (string) $userId : null,
            'session_hash' => $sessionHash,
        ];
    }

    private function requestEndpoint(Request $request): string
    {
        return '/'.trim($request->path(), '/');
    }

    private function validatedUploadId(mixed $id): string
    {
        $id = is_string($id) ? Str::lower(trim($id)) : '';

        if (! $this->isValidUploadId($id)) {
            $this->throwInvalidUpload('Ungültige Upload-ID.');
        }

        return $id;
    }

    private function isValidUploadId(string $id): bool
    {
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',
            $id,
        ) === 1 && Str::isUuid($id);
    }

    private function createUploadDirectory(string $id): string
    {
        $root = $this->tempRoot();
        $this->ensureDirectory($root);

        $directory = $this->joinPath($root, $id);
        if (! $this->pathIsWithin($directory, $root)) {
            abort(500, 'Ungültiger Upload-Pfad.');
        }

        if (! mkdir($directory, 0775) && ! is_dir($directory)) {
            abort(500, 'Das Upload-Verzeichnis konnte nicht erstellt werden.');
        }

        return $directory;
    }

    private function existingUploadDirectory(string $id): string
    {
        $root = $this->tempRoot();
        $directory = $this->joinPath($root, $id);

        if (! $this->pathIsWithin($directory, $root) || ! is_dir($directory)) {
            abort(404, 'Upload nicht gefunden oder abgelaufen.');
        }

        return $directory;
    }

    private function tempRoot(): string
    {
        return $this->normalizeAbsolutePath(storage_path('app/private/temp'));
    }

    private function destinationDirectory(string $uploadPath): string
    {
        $storageRoot = $this->normalizeAbsolutePath(storage_path());
        $relativePath = str_replace('\\', '/', trim($uploadPath));
        $relativePath = trim($relativePath, '/');

        if (
            $relativePath === ''
            || str_contains($relativePath, "\0")
            || preg_match('/^[a-z]:/i', $relativePath) === 1
        ) {
            $this->throwInvalidUpload('Ungültiger Zielpfad.');
        }

        $segments = explode('/', $relativePath);
        if (in_array('..', $segments, true)) {
            $this->throwInvalidUpload('Ungültiger Zielpfad.');
        }

        $directory = $this->normalizeAbsolutePath($this->joinPath($storageRoot, implode('/', $segments)));

        if (! $this->pathIsWithin($directory, $storageRoot)) {
            $this->throwInvalidUpload('Ungültiger Zielpfad.');
        }

        $this->ensureDirectory($directory);

        $canonicalStorageRoot = realpath($storageRoot);
        $canonicalDirectory = realpath($directory);

        if (
            ! is_string($canonicalStorageRoot)
            || ! is_string($canonicalDirectory)
            || ! $this->pathIsWithin($canonicalDirectory, $canonicalStorageRoot)
        ) {
            $this->throwInvalidUpload('Ungültiger Zielpfad.');
        }

        return $this->normalizeAbsolutePath($canonicalDirectory);
    }

    private function destinationName(string $originalName, ?string $newName): string
    {
        $extension = Str::lower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $baseName = trim((string) $newName);

        if ($baseName === '') {
            $baseName = Str::slug((string) pathinfo($originalName, PATHINFO_FILENAME), '-');
        }

        if ($baseName === '') {
            $baseName = 'upload';
        }

        if (
            str_contains($baseName, "\0")
            || str_contains($baseName, '/')
            || str_contains($baseName, '\\')
            || in_array($baseName, ['.', '..'], true)
        ) {
            $this->throwInvalidUpload('Ungültiger Zieldateiname.');
        }

        $baseName = mb_substr($baseName, 0, 180);

        return $extension !== '' ? "{$baseName}.{$extension}" : $baseName;
    }

    private function normalizedOriginalName(mixed $name): ?string
    {
        if (! is_string($name) || trim($name) === '') {
            return null;
        }

        $name = trim($name);

        if (
            mb_strlen($name) > 255
            || str_contains($name, "\0")
            || str_contains($name, '/')
            || str_contains($name, '\\')
            || in_array($name, ['.', '..'], true)
        ) {
            $this->throwInvalidUpload('Ungültiger ursprünglicher Dateiname.');
        }

        return $name;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function resolveOriginalName(Request $request, array $metadata): string
    {
        $headerName = $this->normalizedOriginalName($request->header('Upload-Name'));
        $storedName = is_string($metadata['original_name'] ?? null)
            ? $metadata['original_name']
            : null;

        if ($headerName !== null && $storedName !== null && ! hash_equals($storedName, $headerName)) {
            $this->throwInvalidUpload('Der ursprüngliche Dateiname hat sich geändert.');
        }

        return $storedName ?? $headerName ?? 'upload.bin';
    }

    /**
     * @param  list<string>  $allowedExtensions
     */
    private function assertAllowedExtension(?string $originalName, array $allowedExtensions): void
    {
        if ($allowedExtensions === [] || $originalName === null) {
            return;
        }

        $extension = Str::lower((string) pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension === '' || ! in_array($extension, $allowedExtensions, true)) {
            $allowed = Str::upper(implode(', ', $allowedExtensions));
            $this->throwInvalidUpload("Erlaubte Dateitypen: {$allowed}.");
        }
    }

    /**
     * @param  list<string>  $allowedExtensions
     */
    private function validateCompletedFile(
        string $path,
        string $originalName,
        array $allowedExtensions,
    ): void {
        $extension = Str::lower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $this->assertAllowedExtension($originalName, $allowedExtensions);

        if ($allowedExtensions === []) {
            return;
        }

        $mimeType = (string) (mime_content_type($path) ?: 'application/octet-stream');
        $isValid = match ($extension) {
            'jpg', 'jpeg', 'png', 'gif', 'webp' => $this->isValidImage($path, $extension),
            'xlsx' => $this->isValidXlsx($path, $mimeType),
            'xls' => $this->hasBinaryPrefix($path, hex2bin('D0CF11E0A1B11AE1'))
                && in_array($mimeType, [
                    'application/CDFV2',
                    'application/x-cdf',
                    'application/vnd.ms-excel',
                    'application/octet-stream',
                ], true),
            'json' => $this->isValidJson($path)
                && in_array($mimeType, [
                    'application/json',
                    'text/plain',
                    'application/octet-stream',
                ], true),
            'csv' => $this->isSafeTextFile($path)
                && in_array($mimeType, [
                    'text/plain',
                    'text/csv',
                    'application/csv',
                    'application/octet-stream',
                ], true),
            'txt' => $this->isSafeTextFile($path)
                && in_array($mimeType, [
                    'text/plain',
                    'application/octet-stream',
                ], true),
            default => true,
        };

        if ($isValid) {
            return;
        }

        $this->discardUpload(dirname($path));
        $this->throwInvalidUpload('Dateiinhalt und Dateityp stimmen nicht überein.');
    }

    private function isValidImage(string $path, string $extension): bool
    {
        $imageInfo = @getimagesize($path);
        if (! is_array($imageInfo)) {
            return false;
        }

        $expectedMimeTypes = match ($extension) {
            'jpg', 'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'webp' => ['image/webp'],
            default => [],
        };

        return in_array((string) ($imageInfo['mime'] ?? ''), $expectedMimeTypes, true);
    }

    private function isValidJson(string $path): bool
    {
        $contents = file_get_contents($path);
        if (! is_string($contents)) {
            return false;
        }

        try {
            json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

            return true;
        } catch (JsonException) {
            return false;
        }
    }

    private function isValidXlsx(string $path, string $mimeType): bool
    {
        if (
            ! $this->hasPrefix($path, 'PK')
            || ! in_array($mimeType, [
                'application/zip',
                'application/octet-stream',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ], true)
        ) {
            return false;
        }

        $archive = new ZipArchive;
        if ($archive->open($path, ZipArchive::CHECKCONS) !== true) {
            return false;
        }

        try {
            if (
                $archive->numFiles > self::MAX_XLSX_ARCHIVE_ENTRIES
                || $archive->locateName('[Content_Types].xml') === false
                || $archive->locateName('xl/workbook.xml') === false
            ) {
                return false;
            }

            $uncompressedBytes = 0;
            $compressedBytes = 0;

            for ($index = 0; $index < $archive->numFiles; $index++) {
                $statistics = $archive->statIndex($index);
                if (! is_array($statistics)) {
                    return false;
                }

                $name = (string) ($statistics['name'] ?? '');
                if (
                    $name === ''
                    || str_contains($name, "\0")
                    || str_starts_with($name, '/')
                    || in_array('..', explode('/', str_replace('\\', '/', $name)), true)
                ) {
                    return false;
                }

                $size = max(0, (int) ($statistics['size'] ?? 0));
                $compressedSize = max(0, (int) ($statistics['comp_size'] ?? 0));
                $uncompressedBytes += $size;
                $compressedBytes += $compressedSize;

                if ($uncompressedBytes > self::MAX_XLSX_UNCOMPRESSED_BYTES) {
                    return false;
                }
            }

            return $uncompressedBytes === 0
                || (
                    $compressedBytes > 0
                    && $uncompressedBytes / $compressedBytes <= self::MAX_XLSX_COMPRESSION_RATIO
                );
        } finally {
            $archive->close();
        }
    }

    private function isSafeTextFile(string $path): bool
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        $sample = fread($handle, 8192);
        fclose($handle);

        return is_string($sample) && ! str_contains($sample, "\0");
    }

    private function hasPrefix(string $path, string $prefix): bool
    {
        return $this->hasBinaryPrefix($path, $prefix);
    }

    private function hasBinaryPrefix(string $path, string|false $prefix): bool
    {
        if (! is_string($prefix) || $prefix === '') {
            return false;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        $actual = fread($handle, strlen($prefix));
        fclose($handle);

        return is_string($actual) && hash_equals($prefix, $actual);
    }

    /**
     * @param  array{width?: int, height?: int}  $fit
     */
    private function resizeImage(string $path, array $fit): void
    {
        if (! isset($fit['width']) && ! isset($fit['height'])) {
            return;
        }

        try {
            $manager = ImageManager::gd();
            $image = $manager->read($path);

            if (isset($fit['width'], $fit['height'])) {
                $image->scale($fit['width'], $fit['height']);
            } elseif (isset($fit['width'])) {
                $image->scale(width: $fit['width']);
            } else {
                $image->scale(height: $fit['height']);
            }

            $image->save();
        } catch (\Throwable) {
            @unlink($path);
            $this->throwInvalidUpload('Das hochgeladene Bild ist ungültig.');
        }
    }

    private function moveFile(string $source, string $destination): void
    {
        if (@rename($source, $destination)) {
            return;
        }

        if (@copy($source, $destination) && @unlink($source)) {
            return;
        }

        abort(500, 'Die hochgeladene Datei konnte nicht übernommen werden.');
    }

    private function declaredUploadLength(Request $request): ?int
    {
        $value = $request->header('Upload-Length');

        if ($value === null || $value === '') {
            return null;
        }

        if (! is_scalar($value) || preg_match('/^\d+$/D', (string) $value) !== 1) {
            $this->throwInvalidUpload('Ungültige Uploadgröße.');
        }

        $length = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0, 'max_range' => PHP_INT_MAX]],
        );

        if ($length === false) {
            $this->throwInvalidUpload('Ungültige Uploadgröße.');
        }

        return $length;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function writeMetadata(string $directory, array $metadata): void
    {
        try {
            $json = json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            abort(500, 'Upload-Metadaten konnten nicht erstellt werden.');
        }

        $temporaryPath = $this->joinPath($directory, self::METADATA_FILE.'.tmp');
        $metadataPath = $this->joinPath($directory, self::METADATA_FILE);

        if (file_put_contents($temporaryPath, $json, LOCK_EX) === false) {
            @unlink($temporaryPath);
            abort(500, 'Upload-Metadaten konnten nicht gespeichert werden.');
        }

        if (is_file($metadataPath)) {
            @unlink($metadataPath);
        }

        if (! @rename($temporaryPath, $metadataPath)) {
            @unlink($temporaryPath);
            abort(500, 'Upload-Metadaten konnten nicht gespeichert werden.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readMetadata(string $directory, bool $required = true): array
    {
        $path = $this->joinPath($directory, self::METADATA_FILE);
        $json = is_file($path) ? file_get_contents($path) : false;

        if (! is_string($json)) {
            if ($required) {
                abort(404, 'Upload nicht gefunden oder abgelaufen.');
            }

            return [];
        }

        try {
            $metadata = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            if ($required) {
                abort(404, 'Upload nicht gefunden oder abgelaufen.');
            }

            return [];
        }

        return is_array($metadata) ? $metadata : [];
    }

    private function partPath(string $directory): string
    {
        return $this->joinPath($directory, self::PART_FILE);
    }

    private function discardUpload(string $directory): void
    {
        $root = $this->tempRoot();

        if (! $this->pathIsWithin($directory, $root) || ! $this->isValidUploadId(basename($directory))) {
            return;
        }

        File::deleteDirectory($directory);
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            abort(500, 'Verzeichnis konnte nicht erstellt werden.');
        }
    }

    private function normalizeAbsolutePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $prefix = preg_match('/^[A-Za-z]:/', $path) === 1 ? Str::lower(substr($path, 0, 2)) : '';
        $rest = $prefix !== '' ? substr($path, 2) : $path;
        $segments = [];

        foreach (explode('/', $rest) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        $normalized = implode('/', $segments);

        return $prefix !== '' ? "{$prefix}/{$normalized}" : "/{$normalized}";
    }

    private function pathIsWithin(string $path, string $root): bool
    {
        $normalizedPath = Str::lower(rtrim($this->normalizeAbsolutePath($path), '/'));
        $normalizedRoot = Str::lower(rtrim($this->normalizeAbsolutePath($root), '/'));

        return $normalizedPath !== $normalizedRoot
            && str_starts_with($normalizedPath.'/', $normalizedRoot.'/');
    }

    private function joinPath(string $left, string $right): string
    {
        return rtrim($left, '/\\').'/'.ltrim($right, '/\\');
    }

    private function throwFileTooLarge(): never
    {
        throw ValidationException::withMessages([
            'file' => 'Datei überschreitet die maximal erlaubte Uploadgröße.',
        ]);
    }

    private function throwInvalidUpload(string $message): never
    {
        throw ValidationException::withMessages([
            'upload' => $message,
        ]);
    }
}
