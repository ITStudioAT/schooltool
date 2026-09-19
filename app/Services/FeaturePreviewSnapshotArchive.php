<?php

namespace App\Services;

use Generator;
use RuntimeException;
use SensitiveParameter;
use Throwable;

class FeaturePreviewSnapshotArchive
{
    private const MAGIC = "STPREVIEW\x01";

    public const MAX_RECORD_BYTES = 32 * 1024 * 1024;

    private const FAILURE = 'The encrypted preview snapshot could not be processed safely.';

    public static function generateKeyPair(): string
    {
        self::assertSodium();

        return sodium_crypto_box_keypair();
    }

    public static function publicKey(#[SensitiveParameter] string $keyPair): string
    {
        self::assertKeyPair($keyPair);

        return sodium_bin2hex(sodium_crypto_box_publickey($keyPair));
    }

    /**
     * The caller must supply a trusted, existing private parent directory.
     *
     * @param  iterable<array<array-key, mixed>>  $records
     */
    public function write(string $path, string $publicKeyHex, #[SensitiveParameter] iterable $records): void
    {
        $stream = null;
        $streamKey = null;
        $state = null;
        $created = false;
        $completed = false;

        try {
            self::assertSodium();
            $this->assertLocalPath($path);

            if (file_exists($path)) {
                throw new RuntimeException(self::FAILURE);
            }

            if (strlen($publicKeyHex) !== SODIUM_CRYPTO_BOX_PUBLICKEYBYTES * 2 || ! ctype_xdigit($publicKeyHex)) {
                throw new RuntimeException(self::FAILURE);
            }

            $streamKey = sodium_crypto_secretstream_xchacha20poly1305_keygen();
            $sealedKey = sodium_crypto_box_seal($streamKey, sodium_hex2bin($publicKeyHex));
            [$state, $streamHeader] = sodium_crypto_secretstream_xchacha20poly1305_init_push($streamKey);
            sodium_memzero($streamKey);
            $header = self::MAGIC.$sealedKey.$streamHeader;
            $previousMask = umask(0077);

            try {
                $stream = @fopen($path, 'x+b');
            } finally {
                umask($previousMask);
            }

            if ($stream === false) {
                throw new RuntimeException(self::FAILURE);
            }

            $created = true;

            if (! @chmod($path, 0600) || ! flock($stream, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException(self::FAILURE);
            }

            $this->writeBytes($stream, $header);

            foreach ($records as $record) {
                $json = $this->encodeRecord($record);
                $this->writeFrame($stream, $state, $header, $json, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE);
                sodium_memzero($json);
            }

            $this->writeFrame($stream, $state, $header, '', SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);

            if (! fflush($stream) || (function_exists('fsync') && ! fsync($stream))) {
                throw new RuntimeException(self::FAILURE);
            }

            $completed = true;
        } catch (Throwable) {
            throw new RuntimeException(self::FAILURE);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }

            if ($created && ! $completed) {
                @unlink($path);
            }

            $this->erase($streamKey);
            $this->erase($state);
        }
    }

    /**
     * Exhaust a complete validation pass before importing any record. Early
     * iteration alone cannot establish that the authenticated ending exists.
     * Sender authorization and record semantics belong to the caller.
     *
     * @return Generator<int, array<array-key, mixed>, mixed, void>
     */
    public function read(string $path, #[SensitiveParameter] string $keyPair): Generator
    {
        $stream = null;
        $streamKey = null;
        $state = null;

        try {
            self::assertKeyPair($keyPair);
            $this->assertLocalPath($path);

            if (! is_file($path)) {
                throw new RuntimeException(self::FAILURE);
            }

            $stream = @fopen($path, 'rb');

            if ($stream === false || ! flock($stream, LOCK_SH | LOCK_NB)) {
                throw new RuntimeException(self::FAILURE);
            }

            $stat = fstat($stream);

            if ($stat === false || ($stat['mode'] & 0170000) !== 0100000) {
                throw new RuntimeException(self::FAILURE);
            }

            $magic = $this->readBytes($stream, strlen(self::MAGIC));

            if ($magic !== self::MAGIC) {
                throw new RuntimeException(self::FAILURE);
            }

            $sealedKey = $this->readBytes($stream, SODIUM_CRYPTO_BOX_SEALBYTES + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES);
            $streamHeader = $this->readBytes($stream, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
            $streamKey = sodium_crypto_box_seal_open($sealedKey, $keyPair);

            if ($streamKey === false) {
                throw new RuntimeException(self::FAILURE);
            }

            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($streamHeader, $streamKey);
            sodium_memzero($streamKey);
            $header = $magic.$sealedKey.$streamHeader;

            while (true) {
                $lengthBytes = $this->readBytes($stream, 4);
                $length = unpack('Nlength', $lengthBytes)['length'];

                if ($length < SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES
                    || $length > self::MAX_RECORD_BYTES + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES) {
                    throw new RuntimeException(self::FAILURE);
                }

                $result = sodium_crypto_secretstream_xchacha20poly1305_pull(
                    $state,
                    $this->readBytes($stream, $length),
                    $header.$lengthBytes,
                );

                if ($result === false) {
                    throw new RuntimeException(self::FAILURE);
                }

                [$json, $tag] = $result;
                unset($result);

                if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                    if ($json !== '' || fread($stream, 1) !== '' || ! feof($stream)) {
                        throw new RuntimeException(self::FAILURE);
                    }

                    return;
                }

                if ($tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE) {
                    throw new RuntimeException(self::FAILURE);
                }

                $record = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
                sodium_memzero($json);

                if (! is_array($record)) {
                    throw new RuntimeException(self::FAILURE);
                }

                yield $record;
            }
        } catch (Throwable) {
            throw new RuntimeException(self::FAILURE);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }

            $this->erase($streamKey);
            $this->erase($state);
        }
    }

    private static function assertSodium(): void
    {
        if (! extension_loaded('sodium')) {
            throw new RuntimeException(self::FAILURE);
        }
    }

    private static function assertKeyPair(#[SensitiveParameter] string $keyPair): void
    {
        self::assertSodium();

        if (strlen($keyPair) !== SODIUM_CRYPTO_BOX_KEYPAIRBYTES
            || ! hash_equals(sodium_crypto_box_publickey($keyPair), sodium_crypto_box_publickey_from_secretkey(sodium_crypto_box_secretkey($keyPair)))) {
            throw new RuntimeException(self::FAILURE);
        }
    }

    private function assertLocalPath(string $path): void
    {
        if ($path === '' || str_contains($path, "\0") || str_contains($path, '://')
            || ! is_dir(dirname($path)) || is_link($path)) {
            throw new RuntimeException(self::FAILURE);
        }
    }

    private function encodeRecord(#[SensitiveParameter] mixed $record): string
    {
        if (! is_array($record)) {
            throw new RuntimeException(self::FAILURE);
        }

        $json = json_encode($record, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION, 64);

        if (strlen($json) > self::MAX_RECORD_BYTES) {
            throw new RuntimeException(self::FAILURE);
        }

        return $json;
    }

    /** @param resource $stream */
    private function writeFrame($stream, #[SensitiveParameter] string &$state, string $header, #[SensitiveParameter] string $json, int $tag): void
    {
        $length = pack('N', strlen($json) + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES);
        $ciphertext = sodium_crypto_secretstream_xchacha20poly1305_push($state, $json, $header.$length, $tag);
        $this->writeBytes($stream, $length);
        $this->writeBytes($stream, $ciphertext);
    }

    /** @param resource $stream */
    private function writeBytes($stream, string $bytes): void
    {
        $offset = 0;
        $length = strlen($bytes);

        while ($offset < $length) {
            $written = @fwrite($stream, substr($bytes, $offset, 65536));

            if ($written === false || $written === 0) {
                throw new RuntimeException(self::FAILURE);
            }

            $offset += $written;
        }
    }

    /** @param resource $stream */
    private function readBytes($stream, int $length): string
    {
        $bytes = '';

        while (strlen($bytes) < $length) {
            $chunk = @fread($stream, min(65536, $length - strlen($bytes)));

            if ($chunk === false || $chunk === '') {
                throw new RuntimeException(self::FAILURE);
            }

            $bytes .= $chunk;
        }

        return $bytes;
    }

    private function erase(#[SensitiveParameter] mixed &$secret): void
    {
        if (is_string($secret)) {
            sodium_memzero($secret);
        }
    }
}
