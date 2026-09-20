<?php

namespace App\Services;

use Aws\S3\S3ClientInterface;
use FilesystemIterator;
use Generator;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\StreamInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SensitiveParameter;
use Throwable;

class FeaturePreviewSnapshotFiles
{
    public const CHUNK_BYTES = 1024 * 1024;

    private const FAILURE = 'The preview file snapshot could not be processed safely.';

    private string $storageRoot;

    /** @var array<string, mixed> */
    private array $diskConfigs;

    /** @param array<string, mixed>|null $diskConfigs */
    public function __construct(?string $storageRoot = null, ?array $diskConfigs = null, private ?S3ClientInterface $sourceClient = null)
    {
        $this->storageRoot = rtrim($storageRoot ?? storage_path('app'), '/\\');
        $this->diskConfigs = $diskConfigs ?? config('filesystems');
    }

    public function assertConfigurationSafe(): void
    {
        if (! in_array($this->diskConfigs['default'] ?? null, ['local', 'public'], true)) {
            throw new RuntimeException(self::FAILURE);
        }

        $this->assertLocalRootsSafe();
    }

    public function assertSourceConfigurationSafe(): void
    {
        if (($this->diskConfigs['default'] ?? null) !== 's3') {
            $this->assertConfigurationSafe();

            return;
        }

        $this->assertLocalRootsSafe();
        $configuration = $this->diskConfigs['disks']['s3'] ?? null;
        if (! is_array($configuration) || ($configuration['driver'] ?? null) !== 's3'
            || ! is_string($configuration['bucket'] ?? null) || $configuration['bucket'] === ''
            || ($configuration['scheme'] ?? 'https') !== 'https'
            || ($configuration['options'] ?? []) !== []
            || isset($configuration['http']['verify']) && $configuration['http']['verify'] !== true) {
            throw new RuntimeException(self::FAILURE);
        }
        if (isset($configuration['endpoint']) && $configuration['endpoint'] !== ''
            && (! is_string($configuration['endpoint']) || parse_url($configuration['endpoint'], PHP_URL_SCHEME) !== 'https'
                || parse_url($configuration['endpoint'], PHP_URL_USER) !== null || parse_url($configuration['endpoint'], PHP_URL_PASS) !== null)) {
            throw new RuntimeException(self::FAILURE);
        }
        $this->sourcePrefix();
    }

    private function assertLocalRootsSafe(): void
    {
        $this->assertSafeDirectory($this->storageRoot);

        foreach (['local' => 'private', 'public' => 'public'] as $disk => $directory) {
            $config = $this->diskConfigs['disks'][$disk] ?? null;
            $expectedRoot = $this->storageRoot.'/'.$directory;

            if (! is_array($config) || ($config['driver'] ?? null) !== 'local'
                || ! is_string($config['root'] ?? null)
                || $this->normalizedPath($config['root']) !== $this->normalizedPath($expectedRoot)) {
                throw new RuntimeException(self::FAILURE);
            }

            if (file_exists($expectedRoot) || is_link($expectedRoot)) {
                $this->assertSafeDirectory($expectedRoot);
            }
        }
    }

    public function fingerprint(bool $source = false): string
    {
        try {
            if ($source && ($this->diskConfigs['default'] ?? null) === 's3') {
                return $this->sourceFingerprint();
            }
            $this->assertConfigurationSafe();
            $entries = [];

            foreach (['private', 'public'] as $root) {
                $directory = $this->storageRoot.'/'.$root;
                $entries[$root] = ['directory' => is_dir($directory)];

                if (! is_dir($directory)) {
                    continue;
                }

                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST,
                );

                foreach ($iterator as $entry) {
                    $path = $entry->getPathname();
                    $relative = $root.'/'.str_replace('\\', '/', substr($path, strlen($directory) + 1));
                    $this->assertRecordPath($relative);

                    if ($entry->isLink()) {
                        throw new RuntimeException(self::FAILURE);
                    }

                    if ($entry->isDir()) {
                        $this->assertSafeDirectory($path);
                        $entries[$relative] = ['directory' => true];

                        continue;
                    }

                    $metadata = [];
                    foreach ($this->fileRecords($path, $relative) as $record) {
                        if ($record['kind'] === 'file_start') {
                            $metadata['size'] = $record['size'];
                        }
                        if ($record['kind'] === 'file_end') {
                            $metadata['sha256'] = $record['sha256'];
                        }
                    }
                    $entries[$relative] = $metadata;
                }
            }

            ksort($entries, SORT_STRING);
            $hash = hash_init('sha256');
            foreach ($entries as $path => $metadata) {
                hash_update($hash, json_encode([$path, $metadata], JSON_THROW_ON_ERROR)."\n");
            }

            return hash_final($hash);
        } catch (Throwable) {
            throw new RuntimeException(self::FAILURE);
        }
    }

    /** @return Generator<int, array<string, mixed>, mixed, void> */
    public function records(bool $source = false): Generator
    {
        try {
            if ($source && ($this->diskConfigs['default'] ?? null) === 's3') {
                foreach ($this->sourceInventory() as $relative => $entry) {
                    if (! $entry['directory']) {
                        yield from $this->sourceFileRecords($relative, $entry);
                    }
                }

                return;
            }
            $this->assertConfigurationSafe();

            foreach (['private', 'public'] as $root) {
                $directory = $this->storageRoot.'/'.$root;

                if (! is_dir($directory)) {
                    continue;
                }

                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST,
                );

                foreach ($iterator as $entry) {
                    $path = $entry->getPathname();

                    if ($entry->isLink()) {
                        throw new RuntimeException(self::FAILURE);
                    }

                    if ($entry->isDir()) {
                        $this->assertSafeDirectory($path);

                        continue;
                    }

                    $relative = $root.'/'.str_replace('\\', '/', substr($path, strlen($directory) + 1));
                    $this->assertRecordPath($relative);
                    yield from $this->fileRecords($path, $relative);
                }
            }
        } catch (Throwable) {
            throw new RuntimeException(self::FAILURE);
        }
    }

    /**
     * Restore into the caller's existing empty, private staging directory.
     * Failed staging data must never be activated; disposal belongs to the caller.
     *
     * @param  iterable<array<string, mixed>>  $records
     */
    public function restore(#[SensitiveParameter] iterable $records, string $stagingDir): void
    {
        $stream = null;

        try {
            $this->assertSafeDirectory($stagingDir);
            $stagingPath = $this->normalizedPath($stagingDir);
            $sourcePath = $this->normalizedPath($this->storageRoot);

            if ($stagingPath === $sourcePath || str_starts_with($stagingPath.'/', $sourcePath.'/')
                || str_starts_with($sourcePath.'/', $stagingPath.'/')
                || (new FilesystemIterator($stagingDir))->valid()
                || (PHP_OS_FAMILY !== 'Windows' && (fileperms($stagingDir) & 0077) !== 0)) {
                throw new RuntimeException(self::FAILURE);
            }

            foreach (['private', 'public'] as $root) {
                $this->createDirectory($stagingDir.'/'.$root);
            }

            $seen = [];
            $expectedSize = 0;
            $written = 0;
            $hash = null;

            foreach ($records as $record) {
                $record = $this->validatedRecord($record);

                if ($record['kind'] === 'file_start') {
                    if (is_resource($stream)) {
                        throw new RuntimeException(self::FAILURE);
                    }

                    $relative = $record['path'];
                    $this->assertRecordPath($relative);
                    $this->registerPath($relative, $seen);
                    $path = rtrim($stagingDir, '/\\').'/'.$relative;
                    $this->createParents(dirname($path), $stagingDir);

                    if (file_exists($path) || is_link($path)) {
                        throw new RuntimeException(self::FAILURE);
                    }

                    $previousMask = umask(0077);

                    try {
                        $stream = @fopen($path, 'x+b');
                    } finally {
                        umask($previousMask);
                    }

                    if ($stream === false || ! chmod($path, 0600)) {
                        throw new RuntimeException(self::FAILURE);
                    }

                    $expectedSize = $record['size'];
                    $written = 0;
                    $hash = hash_init('sha256');

                    continue;
                }

                if (! is_resource($stream) || $hash === null) {
                    throw new RuntimeException(self::FAILURE);
                }

                if ($record['kind'] === 'file_chunk') {
                    $bytes = base64_decode($record['data'], true);

                    if ($bytes === false || $bytes === '' || strlen($bytes) > self::CHUNK_BYTES
                        || base64_encode($bytes) !== $record['data'] || strlen($bytes) > $expectedSize - $written) {
                        throw new RuntimeException(self::FAILURE);
                    }

                    $this->writeBytes($stream, $bytes);
                    hash_update($hash, $bytes);
                    $written += strlen($bytes);

                    continue;
                }

                if ($written !== $expectedSize || ! hash_equals($record['sha256'], hash_final($hash))
                    || ! fflush($stream) || (function_exists('fsync') && ! fsync($stream))) {
                    throw new RuntimeException(self::FAILURE);
                }

                fclose($stream);
                $stream = null;
                $hash = null;
            }

            if (is_resource($stream)) {
                throw new RuntimeException(self::FAILURE);
            }
        } catch (Throwable) {
            throw new RuntimeException(self::FAILURE);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function sourcePrefix(): string
    {
        $segments = [];
        foreach (['root', 'prefix'] as $name) {
            $value = $this->diskConfigs['disks']['s3'][$name] ?? '';
            if (! is_string($value)) {
                throw new RuntimeException(self::FAILURE);
            }
            if ($value !== '') {
                $value = rtrim($value, '/');
                $this->assertRecordPath('private/'.$value);
                $segments[] = $value;
            }
        }

        return $segments === [] ? '' : implode('/', $segments).'/';
    }

    private function sourceClient(): S3ClientInterface
    {
        if ($this->sourceClient === null) {
            $disk = Storage::build($this->diskConfigs['disks']['s3']);
            if (! $disk instanceof AwsS3V3Adapter) {
                throw new RuntimeException(self::FAILURE);
            }
            $this->sourceClient = $disk->getClient();
        }

        return $this->sourceClient;
    }

    /** @return array<string, array{directory: bool, local?: string, key?: string, size?: int, etag?: string}> */
    private function sourceInventory(): array
    {
        $this->assertSourceConfigurationSafe();
        $entries = [];
        $seen = [];
        foreach (['private', 'public'] as $root) {
            $directory = $this->storageRoot.'/'.$root;
            $entries[$root] = ['directory' => true];
            if (! is_dir($directory)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST,
            );
            foreach ($iterator as $entry) {
                $path = $entry->getPathname();
                $relative = $root.'/'.str_replace('\\', '/', substr($path, strlen($directory) + 1));
                $this->assertRecordPath($relative);
                if ($entry->isLink()) {
                    throw new RuntimeException(self::FAILURE);
                }
                $isDirectory = $entry->isDir();
                if ($isDirectory) {
                    $this->assertSafeDirectory($path);
                }
                $this->registerPath($relative, $seen, $isDirectory);
                $entries[$relative] = ['directory' => $isDirectory, 'local' => $path];
            }
        }

        $prefix = $this->sourcePrefix();
        $continuation = null;
        $tokens = [];
        do {
            $parameters = ['Bucket' => $this->diskConfigs['disks']['s3']['bucket'], 'Prefix' => $prefix, 'MaxKeys' => 1000];
            if ($continuation !== null) {
                $parameters['ContinuationToken'] = $continuation;
            }
            $response = $this->sourceClient()->execute($this->sourceClient()->getCommand('ListObjectsV2', $parameters));
            $contents = $response['Contents'] ?? [];
            if (! is_array($contents) || ! is_bool($response['IsTruncated'] ?? null)) {
                throw new RuntimeException(self::FAILURE);
            }
            foreach ($contents as $object) {
                $key = $object['Key'] ?? null;
                $size = $this->sourceFileSize($object['Size'] ?? null);
                $etag = $object['ETag'] ?? null;
                if (! is_string($key) || ! str_starts_with($key, $prefix)
                    || ! is_string($etag) || preg_match('/\A"[^"\x00-\x20\x7f]+"\z/', $etag) !== 1) {
                    throw new RuntimeException(self::FAILURE);
                }
                $relativeKey = substr($key, strlen($prefix));
                if (str_ends_with($key, '/')) {
                    if ($size !== 0) {
                        throw new RuntimeException(self::FAILURE);
                    }
                    if ($relativeKey !== '') {
                        $this->assertRecordPath('private/'.substr($relativeKey, 0, -1));
                    }

                    continue;
                }
                $relative = 'private/'.$relativeKey;
                $this->assertRecordPath($relative);
                $this->registerPath($relative, $seen);
                $entries[$relative] = ['directory' => false, 'key' => $key, 'size' => $size, 'etag' => $etag];
            }
            $continuation = $response['IsTruncated'] ? ($response['NextContinuationToken'] ?? null) : null;
            if ($response['IsTruncated'] && (! is_string($continuation) || $continuation === '' || isset($tokens[$continuation]))) {
                throw new RuntimeException(self::FAILURE);
            }
            if ($continuation !== null) {
                $tokens[$continuation] = true;
            }
        } while ($continuation !== null);

        ksort($entries, SORT_STRING);

        return $entries;
    }

    private function sourceFingerprint(): string
    {
        $hash = hash_init('sha256');
        foreach ($this->sourceInventory() as $relative => $entry) {
            $metadata = ['directory' => $entry['directory']];
            if (! $entry['directory']) {
                foreach ($this->sourceFileRecords($relative, $entry) as $record) {
                    if ($record['kind'] === 'file_start') {
                        $metadata['size'] = $record['size'];
                    }
                    if ($record['kind'] === 'file_end') {
                        $metadata['sha256'] = $record['sha256'];
                    }
                }
            }
            hash_update($hash, json_encode([$relative, $metadata], JSON_THROW_ON_ERROR)."\n");
        }

        return hash_final($hash);
    }

    /**
     * @param  array{directory: bool, local?: string, key?: string, size?: int, etag?: string}  $entry
     * @return Generator<int, array<string, mixed>, mixed, void>
     */
    private function sourceFileRecords(string $relative, array $entry): Generator
    {
        if (isset($entry['local'])) {
            yield from $this->fileRecords($entry['local'], $relative);

            return;
        }
        $parameters = [
            'Bucket' => $this->diskConfigs['disks']['s3']['bucket'], 'Key' => $entry['key'],
            'IfMatch' => $entry['etag'], '@http' => ['stream' => true],
        ];
        $result = $this->sourceClient()->execute($this->sourceClient()->getCommand('GetObject', $parameters));
        $stream = $result['Body'] ?? null;
        if (! $stream instanceof StreamInterface) {
            throw new RuntimeException(self::FAILURE);
        }
        try {
            if ($this->sourceFileSize($result['ContentLength'] ?? null) !== $entry['size'] || ($result['ETag'] ?? null) !== $entry['etag']) {
                throw new RuntimeException(self::FAILURE);
            }
            yield ['kind' => 'file_start', 'path' => $relative, 'size' => $entry['size']];
            $hash = hash_init('sha256');
            $read = 0;
            while (true) {
                $bytes = $stream->read(self::CHUNK_BYTES);
                if ($bytes === '') {
                    if (! $stream->eof()) {
                        throw new RuntimeException(self::FAILURE);
                    }
                    break;
                }
                $read += strlen($bytes);
                if ($read > $entry['size']) {
                    throw new RuntimeException(self::FAILURE);
                }
                hash_update($hash, $bytes);
                yield ['kind' => 'file_chunk', 'data' => base64_encode($bytes)];
            }
            if ($read !== $entry['size']) {
                throw new RuntimeException(self::FAILURE);
            }
            yield ['kind' => 'file_end', 'sha256' => hash_final($hash)];
        } finally {
            $stream->close();
        }
    }

    private function sourceFileSize(mixed $size): int
    {
        if (is_int($size) && $size >= 0) {
            return $size;
        }

        if (is_string($size) && preg_match('/\A(?:0|[1-9][0-9]*)\z/', $size) === 1
            && (string) (int) $size === $size) {
            return (int) $size;
        }

        throw new RuntimeException(self::FAILURE);
    }

    /** @return Generator<int, array<string, mixed>, mixed, void> */
    private function fileRecords(string $path, string $relative): Generator
    {
        $this->assertSafeDirectory(dirname($path));
        clearstatcache(true, $path);
        $before = lstat($path);

        if ($before === false || ($before['mode'] & 0170000) !== 0100000 || $before['nlink'] !== 1) {
            throw new RuntimeException(self::FAILURE);
        }

        $stream = @fopen($path, 'rb');

        if ($stream === false) {
            throw new RuntimeException(self::FAILURE);
        }

        try {
            if (! flock($stream, LOCK_SH | LOCK_NB) || ! $this->sameFile($before, fstat($stream))) {
                throw new RuntimeException(self::FAILURE);
            }

            yield ['kind' => 'file_start', 'path' => $relative, 'size' => $before['size']];
            $hash = hash_init('sha256');
            $read = 0;

            while (! feof($stream)) {
                $bytes = fread($stream, self::CHUNK_BYTES);

                if ($bytes === false) {
                    throw new RuntimeException(self::FAILURE);
                }

                if ($bytes === '') {
                    if (! feof($stream)) {
                        throw new RuntimeException(self::FAILURE);
                    }

                    break;
                }

                $read += strlen($bytes);

                if ($read > $before['size']) {
                    throw new RuntimeException(self::FAILURE);
                }

                hash_update($hash, $bytes);
                yield ['kind' => 'file_chunk', 'data' => base64_encode($bytes)];
            }

            $checksum = hash_final($hash);
            $currentChecksum = hash_file('sha256', $path);
            clearstatcache(true, $path);

            if ($read !== $before['size'] || ! $this->sameFile($before, fstat($stream))
                || ! $this->sameFile($before, lstat($path)) || is_link($path)
                || ! is_string($currentChecksum) || ! hash_equals($checksum, $currentChecksum)) {
                throw new RuntimeException(self::FAILURE);
            }

            yield ['kind' => 'file_end', 'sha256' => $checksum];
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param  array<string|int, int>  $before
     * @param  array<string|int, int>|false  $after
     */
    private function sameFile(array $before, array|false $after): bool
    {
        if ($after === false) {
            return false;
        }

        foreach (['dev', 'ino', 'mode', 'nlink', 'size', 'mtime', 'ctime'] as $key) {
            if ($before[$key] !== $after[$key]) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, mixed> */
    private function validatedRecord(#[SensitiveParameter] mixed $record): array
    {
        if (! is_array($record)) {
            throw new RuntimeException(self::FAILURE);
        }

        $keys = match ($record['kind'] ?? null) {
            'file_start' => ['kind', 'path', 'size'],
            'file_chunk' => ['data', 'kind'],
            'file_end' => ['kind', 'sha256'],
            default => throw new RuntimeException(self::FAILURE),
        };
        $actualKeys = array_keys($record);
        sort($actualKeys);

        if ($actualKeys !== $keys
            || ($record['kind'] === 'file_start' && (! is_string($record['path']) || ! is_int($record['size']) || $record['size'] < 0))
            || ($record['kind'] === 'file_chunk' && (! is_string($record['data']) || strlen($record['data']) > 4 * (int) ceil(self::CHUNK_BYTES / 3)))
            || ($record['kind'] === 'file_end' && (! is_string($record['sha256']) || ! preg_match('/\A[a-f0-9]{64}\z/', $record['sha256'])))) {
            throw new RuntimeException(self::FAILURE);
        }

        return $record;
    }

    private function assertRecordPath(string $path): void
    {
        $segments = explode('/', $path);

        if (strlen($path) > 4096 || count($segments) < 2 || ! in_array($segments[0], ['private', 'public'], true)
            || ! mb_check_encoding($path, 'UTF-8')) {
            throw new RuntimeException(self::FAILURE);
        }

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || strlen($segment) > 255
                || preg_match('/[\x00-\x1f\x7f\\\\<>:"|?*]/', $segment)
                || str_ends_with($segment, '.') || str_ends_with($segment, ' ')
                || preg_match('/\A(?:con|prn|aux|nul|com[1-9]|lpt[1-9])(?:\.|\z)/i', $segment)) {
                throw new RuntimeException(self::FAILURE);
            }
        }
    }

    /** @param array<string, array{path: string, file: bool}> $seen */
    private function registerPath(string $path, array &$seen, bool $directory = false): void
    {
        $segments = explode('/', $path);
        $current = '';

        foreach ($segments as $index => $segment) {
            $current = $current === '' ? $segment : $current.'/'.$segment;
            $key = mb_strtolower($current, 'UTF-8');
            $isFile = ! $directory && $index === count($segments) - 1;

            if (isset($seen[$key]) && ($seen[$key]['path'] !== $current || $seen[$key]['file'] || $isFile)) {
                throw new RuntimeException(self::FAILURE);
            }

            $seen[$key] = ['path' => $current, 'file' => $isFile];
        }
    }

    private function assertSafeDirectory(string $path): void
    {
        $resolved = realpath($path);

        if ($resolved === false || ! is_dir($path)
            || $this->normalizedPath($resolved) !== $this->normalizedPath($path)) {
            throw new RuntimeException(self::FAILURE);
        }

        $cursor = rtrim($path, '/\\');

        while ($cursor !== '' && dirname($cursor) !== $cursor) {
            if (is_link($cursor)) {
                throw new RuntimeException(self::FAILURE);
            }

            $cursor = dirname($cursor);
        }
    }

    private function normalizedPath(string $path): string
    {
        $normalized = rtrim(str_replace('\\', '/', $path), '/');

        return PHP_OS_FAMILY === 'Windows' ? mb_strtolower($normalized, 'UTF-8') : $normalized;
    }

    private function createParents(string $path, string $stagingDir): void
    {
        if ($this->normalizedPath($path) === $this->normalizedPath($stagingDir)) {
            $this->assertSafeDirectory($path);

            return;
        }

        $this->createParents(dirname($path), $stagingDir);

        if (! file_exists($path)) {
            $this->createDirectory($path);
        }

        $this->assertSafeDirectory($path);
    }

    private function createDirectory(string $path): void
    {
        if (! mkdir($path, 0700) || ! chmod($path, 0700)) {
            throw new RuntimeException(self::FAILURE);
        }
    }

    /** @param resource $stream */
    private function writeBytes($stream, #[SensitiveParameter] string $bytes): void
    {
        $offset = 0;

        while ($offset < strlen($bytes)) {
            $written = fwrite($stream, substr($bytes, $offset, 65536));

            if ($written === false || $written === 0) {
                throw new RuntimeException(self::FAILURE);
            }

            $offset += $written;
        }
    }
}
