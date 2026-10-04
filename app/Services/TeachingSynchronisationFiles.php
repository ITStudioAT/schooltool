<?php

namespace App\Services;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Aws\S3\S3ClientInterface;
use RuntimeException;

class TeachingSynchronisationFiles
{
    public const int MAX_BYTES = 256 * 1024 * 1024;

    public function __construct(private ?S3ClientInterface $sourceClient = null) {}

    public static function assertPath(string $path): void
    {
        if (strlen($path) > 1024 || ! preg_match('~\A[^\x00-\x1f\x7f\\\\:]+\z~u', $path)
            || array_intersect(explode('/', $path), ['', '.', '..']) !== []) {
            throw new RuntimeException('Ein Unterrichtsdateipfad ist unsicher.');
        }
    }

    /** Snapshot only referenced files, including imported curriculum material and import reports. */
    public function capture(array $tables, array $configuration, bool $live = false, bool $collectConflicts = false): array
    {
        $references = [];
        $walk = function (array $row, ?string $preferred = null) use (&$walk, &$references): void {
            $preferred = $row['storage_disk'] ?? $preferred;
            foreach ($row as $key => $value) {
                if (in_array($key, ['file_path', 'source_path'], true) && is_string($value) && $value !== '') {
                    self::assertPath($value);
                    $references[($preferred ?? '').':'.$value] = ['disk' => $preferred, 'path' => $value,
                        'historical_label' => $key === 'source_path' && ! str_contains($value, '/')];
                } elseif ($key === 'report_paths' && $value) {
                    foreach (is_string($value) ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : $value as $path) {
                        if (is_string($path) && $path !== '') {
                            self::assertPath($path);
                            $references['local:'.$path] = ['disk' => 'local', 'path' => $path];
                        }
                    }
                } elseif (is_array($value)) {
                    $walk($value, $preferred);
                } elseif (in_array($key, ['materials', 'topics', 'status'], true) && is_string($value) && $value !== ''
                    && ($key !== 'status' || preg_match('/\A\s*[\[{]/', $value))) {
                    $walk(json_decode($value, true, 512, JSON_THROW_ON_ERROR), $preferred);
                }
            }
        };
        foreach ($tables as $table => $rows) {
            foreach ($rows as $row) {
                $walk($row, $table === 'teaching_curriculum_documents' ? null : 'local');
            }
        }
        $files = [];
        $total = 0;
        foreach ($references as $key => $reference) {
            try {
                $candidates = array_values(array_unique(array_filter([$reference['disk'], $configuration['default'], 'local', 'public'])));
                if ($reference['disk']) {
                    $candidates = [$reference['disk']];
                }
                $contents = null;
                $selected = null;
                foreach ($candidates as $disk) {
                    if ($disk === 's3' && ! $live) {
                        throw new RuntimeException('Lokale Unterrichtsdateien verweisen auf S3; zuerst privat lokal zuordnen.');
                    }
                    $config = $configuration['disks'][$disk] ?? [];
                    if (($config['driver'] ?? null) === 'local') {
                        $path = $reference['path'];
                        // Legacy import source paths are relative to storage/, not the configured disk.
                        $absolute = str_starts_with($path, 'app/') ? dirname($configuration['disks']['local']['root'], 2).'/'.$path : $config['root'].'/'.$path;
                        if (! is_file($absolute)) {
                            continue;
                        }
                        $root = str_starts_with($path, 'app/') ? dirname($configuration['disks']['local']['root'], 2).'/app' : $config['root'];
                        $this->assertCanonicalFile($absolute, $root);
                        if (filesize($absolute) > self::MAX_BYTES - $total) {
                            throw new RuntimeException('Unterrichtsdateien überschreiten die sichere Übertragungsgrenze von 256 MiB.');
                        }
                        $before = stat($absolute);
                        $contents = file_get_contents($absolute);
                        clearstatcache(true, $absolute);
                        $statFields = array_flip(['dev', 'ino', 'size', 'mtime', 'ctime']);
                        if ($contents === false || array_intersect_key($before, $statFields) !== array_intersect_key(stat($absolute), $statFields)
                            || hash_file('sha256', $absolute) !== hash('sha256', $contents)) {
                            throw new RuntimeException('Eine Unterrichtsdatei wurde während des Lesens geändert.');
                        }
                    } elseif ($disk === 's3' && ($config['driver'] ?? null) === 's3' && $live) {
                        $contents = $this->readS3($reference['path'], $config, self::MAX_BYTES - $total);
                        if ($contents === null) {
                            continue;
                        }
                    } else {
                        throw new RuntimeException('Ein Unterrichtsspeicher ist nicht unterstützt.');
                    }
                    $selected = $disk;
                    break;
                }
                if ($contents === null) {
                    throw new RuntimeException('Eine referenzierte Unterrichtsdatei fehlt oder ist nicht lesbar: '.$reference['path']);
                }
                $total += strlen($contents);
                $files[$key] = ['disk' => $selected, 'path' => $reference['path'], 'size' => strlen($contents),
                    'sha256' => hash('sha256', $contents), 'content' => base64_encode($contents)];
            } catch (\Throwable $exception) {
                if (! $collectConflicts) {
                    throw $exception;
                }
                $files[$key] = ['disk' => $reference['disk'], 'path' => $reference['path'], 'size' => null,
                    'error' => $exception::class === RuntimeException::class ? $exception->getMessage() : 'Dateizugriff fehlgeschlagen.'];
                // Older import runs stored just the original filename, outside the current authorized archive directory.
                // Preserve that historical label explicitly; it never offered a downloadable archived source file.
                if (($reference['historical_label'] ?? false) && $exception::class === RuntimeException::class
                    && str_starts_with($exception->getMessage(), 'Eine referenzierte Unterrichtsdatei fehlt')) {
                    $files[$key]['warning'] = 'Historischer Importdateiname ohne verfügbare Quelldatei: '.$reference['path'];
                    unset($files[$key]['error']);
                }
            }
        }

        return $files;
    }

    public function assertCanonicalFile(string $path, string $root): void
    {
        $normalize = fn (string $value): string => rtrim(str_replace('\\', '/', $value), '/');
        $canonical = realpath($path);
        $canonicalRoot = realpath($root);
        if (! $canonical || ! $canonicalRoot || is_link($path) || is_link($root)
            || strcasecmp($normalize($canonical), $normalize($path)) !== 0
            || strcasecmp($normalize($canonicalRoot), $normalize($root)) !== 0
            || ! str_starts_with(strtolower($normalize($canonical)), strtolower($normalize($canonicalRoot)).'/')) {
            throw new RuntimeException('Dateizugriff außerhalb des kanonischen lokalen Speichers.');
        }
    }

    private function readS3(string $path, array $configuration, int $remaining): ?string
    {
        if (($configuration['scheme'] ?? 'https') !== 'https'
            || ! empty($configuration['options'])
            || isset($configuration['http']['verify']) && $configuration['http']['verify'] !== true
            || ! empty($configuration['endpoint']) && parse_url($configuration['endpoint'], PHP_URL_SCHEME) !== 'https') {
            throw new RuntimeException('Der Cloud-Dateispeicher ist nicht sicher konfiguriert.');
        }
        $client = $this->sourceClient ?? new S3Client(array_filter([
            'version' => 'latest', 'region' => $configuration['region'], 'endpoint' => $configuration['endpoint'] ?? null,
            'use_path_style_endpoint' => $configuration['use_path_style_endpoint'] ?? false,
            'credentials' => ['key' => $configuration['key'], 'secret' => $configuration['secret']],
        ], fn ($value): bool => $value !== null));
        $prefix = '';
        foreach (['root', 'prefix'] as $part) {
            if (! empty($configuration[$part])) {
                self::assertPath(rtrim($configuration[$part], '/'));
                $prefix .= rtrim($configuration[$part], '/').'/';
            }
        }
        $parameters = ['Bucket' => $configuration['bucket'], 'Key' => $prefix.$path];
        try {
            $before = $client->headObject($parameters);
        } catch (S3Exception $exception) {
            if ($exception->getStatusCode() === 404) {
                return null;
            }
            throw new RuntimeException('Eine Cloud-Datei konnte nicht gelesen werden.');
        }
        if ((int) $before['ContentLength'] > $remaining || ! is_string($before['ETag'])) {
            throw new RuntimeException('Cloud-Datei überschreitet die sichere Übertragungsgrenze.');
        }
        $result = $client->getObject([...$parameters, 'IfMatch' => $before['ETag']]);
        $body = $result['Body'];
        try {
            $contents = '';
            while (! $body->eof()) {
                $contents .= $body->read(1024 * 1024);
                if (strlen($contents) > $remaining) {
                    throw new RuntimeException('Cloud-Datei überschreitet die sichere Übertragungsgrenze.');
                }
            }
        } finally {
            $body->close();
        }
        $after = $client->headObject($parameters);
        if ($before['ETag'] !== $after['ETag'] || $before['ContentLength'] !== $after['ContentLength']
            || ($before['VersionId'] ?? null) !== ($after['VersionId'] ?? null)
            || strlen($contents) !== (int) $before['ContentLength']) {
            throw new RuntimeException('Eine Cloud-Datei wurde während des Lesens geändert.');
        }

        return $contents;
    }

    public function rewrite(array $tables, array $files, int $schoolId): array
    {
        $paths = [];
        $targets = [];
        $sourcePaths = [];
        foreach ($tables['import116_runs'] ?? [] as $run) {
            if (! empty($run['source_path'])) {
                $sourcePaths[$run['source_path']] = "{$schoolId}/import116-sources/{$run['schoolyear_id']}";
            }
        }
        foreach ($files as $file) {
            if (isset($file['warning'])) {
                continue;
            }
            if (isset($file['error'])) {
                throw new RuntimeException($file['error']);
            }
            $bytes = base64_decode($file['content'], true);
            if ($bytes === false || hash('sha256', $bytes) !== $file['sha256'] || strlen($bytes) !== $file['size']) {
                throw new RuntimeException('Dateiprüfsumme oder Dateigröße stimmt nicht.');
            }
            $extension = strtolower(pathinfo($file['path'], PATHINFO_EXTENSION));
            if (! preg_match('/\A[a-z0-9]{0,12}\z/', $extension)) {
                throw new RuntimeException('Unsichere Dateiendung.');
            }
            $target = "teaching/synchronisation/{$schoolId}/{$file['sha256']}".($extension === '' ? '' : '.'.$extension);
            if (isset($sourcePaths[$file['path']])) {
                $target = $sourcePaths[$file['path']].'/'.$file['sha256'].($extension === '' ? '' : '.'.$extension);
            }
            $rewrittenPath = isset($sourcePaths[$file['path']]) ? 'app/private/'.$target : $target;
            if (isset($paths[$file['path']]) && $paths[$file['path']] !== $rewrittenPath) {
                throw new RuntimeException('Gleichnamige Unterrichtsdateien haben unterschiedliche Inhalte.');
            }
            $paths[$file['path']] = $rewrittenPath;
            $targets[$target] = ['path' => $target, 'content' => $file['content'], 'sha256' => $file['sha256']];
        }
        $walk = function (mixed $value, ?string $context = null) use (&$walk, $paths): mixed {
            if (is_string($value) && isset($paths[$value])) {
                return $paths[$value];
            }
            if (! is_array($value)) {
                return $value;
            }
            foreach ($value as $key => $item) {
                if (in_array($key, ['materials', 'topics', 'report_paths', 'status'], true) && is_string($item) && $item !== ''
                    && ($key !== 'status' || preg_match('/\A\s*[\[{]/', $item))) {
                    $value[$key] = json_encode($walk(json_decode($item, true, 512, JSON_THROW_ON_ERROR)), JSON_THROW_ON_ERROR);
                } else {
                    $value[$key] = $walk($item, is_string($key) ? $key : null);
                }
            }
            if (array_key_exists('storage_disk', $value) && isset($value['file_path']) && in_array($value['file_path'], $paths, true)) {
                $value['storage_disk'] = 'local';
            }

            return $value;
        };

        return ['tables' => $walk($tables), 'files' => array_values($targets)];
    }
}
