<?php

namespace App\Services;

use Generator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class TeachingSynchronisationBackup
{
    private const CHUNK_BYTES = 512 * 1024;

    private const MAX_JSON_BYTES = 512 * 1024 * 1024;

    public function __construct(private FeaturePreviewSnapshotArchive $archive) {}

    public function write(array $payload, string $path): void
    {
        $disk = Storage::disk('local');
        $part = $path.'.part-'.bin2hex(random_bytes(8));
        $metadata = array_intersect_key($payload, array_flip(['format', 'school', 'actor', 'created_at']));
        $metadata['sha256'] = $this->fingerprint($payload);
        $keyPair = $this->keyPair();

        try {
            if ($disk->exists($path) || ! $disk->makeDirectory(dirname($path))) {
                throw new RuntimeException('Sicherheitsbackup konnte nicht vorbereitet werden.');
            }
            $this->archive->write($disk->path($part), sodium_bin2hex(sodium_crypto_box_publickey($keyPair)),
                $this->records($payload, $metadata));
            if ($this->metadata($part) !== $metadata || ! $disk->move($part, $path)) {
                throw new RuntimeException('Sicherheitsbackup konnte nicht vollständig gespeichert werden.');
            }
        } finally {
            sodium_memzero($keyPair);
            $disk->delete($part);
        }
    }

    /** Validate every encrypted frame and the authenticated ending before returning metadata. */
    public function metadata(string $path): array
    {
        $records = $this->readRecords($path);
        $records->rewind();
        $metadata = $records->current();
        foreach ($this->verifiedChunks($records) as $chunk) {
            // Exhaust validation without retaining the recovered payload.
        }

        return $metadata;
    }

    /** Recover the complete state; legacy installation-key backups remain readable. */
    public function read(string $path): array
    {
        if (! $this->isStreamed($path)) {
            return json_decode(Crypt::decryptString(Storage::disk('local')->get($path)), true, 512, JSON_THROW_ON_ERROR);
        }
        $json = '';
        foreach ($this->verifiedChunks($this->readRecords($path)) as $chunk) {
            $json .= $chunk;
        }

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    public function isStreamed(string $path): bool
    {
        $stream = Storage::disk('local')->readStream($path);
        if (! is_resource($stream)) {
            throw new RuntimeException('Sicherheitsbackup nicht lesbar.');
        }
        try {
            return fread($stream, 10) === "STPREVIEW\x01";
        } finally {
            fclose($stream);
        }
    }

    /** Preserve the existing JSON fingerprint without materializing the complete JSON string. */
    public function fingerprint(array $payload): string
    {
        $hash = hash_init('sha256');
        foreach ($this->jsonChunks($payload) as $chunk) {
            hash_update($hash, $chunk);
        }

        return hash_final($hash);
    }

    private function keyPair(): string
    {
        return sodium_crypto_box_seed_keypair(hash('sha256', 'schooltool-teaching-sync-safety-v2:'.Crypt::getKey(), true));
    }

    private function records(array $payload, array $metadata): Generator
    {
        yield $metadata;
        foreach ($this->jsonChunks($payload) as $chunk) {
            yield ['content' => base64_encode($chunk)];
        }
    }

    private function readRecords(string $path): Generator
    {
        $keyPair = $this->keyPair();
        try {
            yield from $this->archive->read(Storage::disk('local')->path($path), $keyPair);
        } finally {
            sodium_memzero($keyPair);
        }
    }

    private function verifiedChunks(Generator $records): Generator
    {
        $records->rewind();
        $metadata = $records->current();
        if (! is_array($metadata) || ($metadata['format'] ?? null) !== 'schooltool-teaching-sync-safety-v2'
            || ! is_string($metadata['sha256'] ?? null)) {
            throw new RuntimeException('Sicherheitsbackup hat ein ungültiges Format.');
        }
        $hash = hash_init('sha256');
        $size = 0;
        $records->next();
        while ($records->valid()) {
            $record = $records->current();
            $chunk = is_string($record['content'] ?? null) ? base64_decode($record['content'], true) : false;
            if ($chunk === false || strlen($chunk) > self::CHUNK_BYTES || ($size += strlen($chunk)) > self::MAX_JSON_BYTES) {
                throw new RuntimeException('Sicherheitsbackup enthält ungültige Daten.');
            }
            hash_update($hash, $chunk);
            yield $chunk;
            $records->next();
        }
        if (! hash_equals($metadata['sha256'], hash_final($hash))) {
            throw new RuntimeException('Sicherheitsbackup ist unvollständig.');
        }
    }

    private function jsonChunks(array $payload): Generator
    {
        $buffer = '';
        $size = 0;
        foreach ($this->jsonTokens($payload) as $token) {
            $buffer .= $token;
            while (strlen($buffer) >= self::CHUNK_BYTES) {
                $size += self::CHUNK_BYTES;
                if ($size > self::MAX_JSON_BYTES) {
                    throw new RuntimeException('Sicherheitsbackup überschreitet die sichere Archivgröße.');
                }
                yield substr($buffer, 0, self::CHUNK_BYTES);
                $buffer = substr($buffer, self::CHUNK_BYTES);
            }
        }
        if ($size + strlen($buffer) > self::MAX_JSON_BYTES) {
            throw new RuntimeException('Sicherheitsbackup überschreitet die sichere Archivgröße.');
        }
        if ($buffer !== '') {
            yield $buffer;
        }
    }

    private function jsonTokens(mixed $value, int $depth = 0): Generator
    {
        if ($depth >= 512) {
            throw new RuntimeException('Sicherheitsbackup enthält zu tief verschachtelte Daten.');
        }
        if (is_array($value)) {
            $list = array_is_list($value);
            yield $list ? '[' : '{';
            $first = true;
            foreach ($value as $key => $item) {
                if (! $first) {
                    yield ',';
                }
                $first = false;
                if (! $list) {
                    yield json_encode((string) $key, JSON_THROW_ON_ERROR).':';
                }
                yield from $this->jsonTokens($item, $depth + 1);
            }
            yield $list ? ']' : '}';

            return;
        }
        if (is_string($value)) {
            yield '"';
            for ($offset = 0, $length = strlen($value); $offset < $length;) {
                $chunk = mb_strcut($value, $offset, self::CHUNK_BYTES, 'UTF-8');
                if ($chunk === '') {
                    throw new RuntimeException('Sicherheitsbackup enthält ungültigen Text.');
                }
                $offset += strlen($chunk);
                yield substr(json_encode($chunk, JSON_THROW_ON_ERROR), 1, -1);
            }
            yield '"';

            return;
        }
        yield json_encode($value, JSON_THROW_ON_ERROR);
    }
}
