<?php

use App\Services\FeaturePreviewSnapshotArchive;

beforeEach(function (): void {
    $this->directory = sys_get_temp_dir().'/schooltool-snapshot-'.bin2hex(random_bytes(8));
    mkdir($this->directory, 0700);
    $this->path = $this->directory.'/snapshot.bin';
    $this->archive = new FeaturePreviewSnapshotArchive;
    $this->keyPair = FeaturePreviewSnapshotArchive::generateKeyPair();
    $this->publicKey = FeaturePreviewSnapshotArchive::publicKey($this->keyPair);
});

afterEach(function (): void {
    foreach (glob($this->directory.'/*') as $path) {
        unlink($path);
    }

    rmdir($this->directory);
});

/** @param list<array{0: string, 1: int}> $messages */
function previewSnapshotFixture(string $publicKeyHex, array $messages): string
{
    $key = sodium_crypto_secretstream_xchacha20poly1305_keygen();
    [$state, $streamHeader] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
    $header = "STPREVIEW\x01".sodium_crypto_box_seal($key, sodium_hex2bin($publicKeyHex)).$streamHeader;
    $bytes = $header;

    foreach ($messages as [$json, $tag]) {
        $length = pack('N', strlen($json) + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES);
        $bytes .= $length.sodium_crypto_secretstream_xchacha20poly1305_push($state, $json, $header.$length, $tag);
    }

    return $bytes;
}

test('snapshot records round trip without plaintext files and can be read repeatedly', function (): void {
    $records = [
        ['type' => 'manifest', 'feature_id' => str_repeat('a', 32)],
        ['id' => 42, 'email' => 'private-snapshot-person@example.test', 'nested' => ['name' => 'Schülerin'], 'float' => 1.0],
        ['binary' => base64_encode("\x00\xff\x80")],
        [],
    ];

    $this->archive->write($this->path, $this->publicKey, (function () use ($records): Generator {
        yield from $records;
    })());
    $bytes = file_get_contents($this->path);

    expect($bytes)->not->toContain('private-snapshot-person', 'Schülerin', 'feature_id')
        ->and(iterator_to_array($this->archive->read($this->path, $this->keyPair)))->toBe($records)
        ->and(iterator_to_array($this->archive->read($this->path, $this->keyPair)))->toBe($records)
        ->and(file_get_contents($this->path))->toBe($bytes)
        ->and(glob($this->directory.'/*'))->toHaveCount(1);

    if (PHP_OS_FAMILY !== 'Windows') {
        expect(fileperms($this->path) & 0777)->toBe(0600);
    }
});

test('snapshot uses fresh randomness for identical records', function (): void {
    $this->archive->write($this->path, $this->publicKey, [['id' => 1]]);
    $this->archive->write($this->directory.'/second.bin', $this->publicKey, [['id' => 1]]);

    expect(file_get_contents($this->path))->not->toBe(file_get_contents($this->directory.'/second.bin'));
});

test('empty snapshots still require an authenticated final frame', function (): void {
    $this->archive->write($this->path, $this->publicKey, []);

    expect(iterator_to_array($this->archive->read($this->path, $this->keyPair)))->toBe([]);

    file_put_contents($this->path, substr(file_get_contents($this->path), 0, -21));

    expect(fn () => iterator_to_array($this->archive->read($this->path, $this->keyPair)))->toThrow(RuntimeException::class);
});

test('snapshot refuses corruption truncation appending and hostile frame lengths', function (string $scenario): void {
    $this->archive->write($this->path, $this->publicKey, [['id' => 1], ['id' => 2]]);
    $bytes = file_get_contents($this->path);
    $headerLength = 10 + SODIUM_CRYPTO_BOX_SEALBYTES + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES;

    $bytes = match ($scenario) {
        'wrong magic' => 'X'.substr($bytes, 1),
        'wrong version' => substr_replace($bytes, "\x02", 9, 1),
        'sealed key' => substr_replace($bytes, $bytes[15] ^ "\x01", 15, 1),
        'stream header' => substr_replace($bytes, $bytes[$headerLength - 1] ^ "\x01", $headerLength - 1, 1),
        'ciphertext' => substr_replace($bytes, $bytes[$headerLength + 9] ^ "\x01", $headerLength + 9, 1),
        'missing final' => substr($bytes, 0, -21),
        'partial header' => substr($bytes, 0, $headerLength - 1),
        'partial length' => substr($bytes, 0, $headerLength + 2),
        'partial frame' => substr($bytes, 0, -1),
        'trailing bytes' => $bytes.'x',
        'concatenated archives' => $bytes.$bytes,
        'huge length' => substr_replace($bytes, pack('N', 0xFFFFFFFF), $headerLength, 4),
        'zero length' => substr_replace($bytes, pack('N', 0), $headerLength, 4),
        'undersized length' => substr_replace($bytes, pack('N', 16), $headerLength, 4),
    };
    file_put_contents($this->path, $bytes);

    try {
        iterator_to_array($this->archive->read($this->path, $this->keyPair));
        $this->fail('Invalid snapshot was accepted.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('The encrypted preview snapshot could not be processed safely.')
            ->and($exception->getPrevious())->toBeNull();
    }
})->with([
    'wrong magic', 'wrong version', 'sealed key', 'stream header', 'ciphertext', 'missing final',
    'partial header', 'partial length', 'partial frame', 'trailing bytes', 'concatenated archives',
    'huge length', 'zero length', 'undersized length',
]);

test('snapshot rejects reordered and duplicated authenticated records', function (bool $duplicate): void {
    $this->archive->write($this->path, $this->publicKey, [['id' => 1], ['id' => 2]]);
    $bytes = file_get_contents($this->path);
    $headerLength = 114;
    $frameLength = 4 + unpack('Nlength', substr($bytes, $headerLength, 4))['length'];
    $first = substr($bytes, $headerLength, $frameLength);
    $second = substr($bytes, $headerLength + $frameLength, $frameLength);
    file_put_contents($this->path, substr($bytes, 0, $headerLength).($duplicate ? $first.$first : $second.$first).substr($bytes, $headerLength + 2 * $frameLength));

    expect(fn () => iterator_to_array($this->archive->read($this->path, $this->keyPair)))->toThrow(RuntimeException::class);
})->with([true, false]);

test('authenticated but invalid record envelopes are rejected', function (string $json, int $tag): void {
    file_put_contents($this->path, previewSnapshotFixture($this->publicKey, [
        [$json, $tag],
        ['', SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL],
    ]));

    expect(fn () => iterator_to_array($this->archive->read($this->path, $this->keyPair)))->toThrow(RuntimeException::class);
})->with([
    'invalid JSON' => ['{"broken":', SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE],
    'scalar JSON' => ['"private-value"', SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE],
    'null JSON' => ['null', SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE],
    'deep JSON' => [str_repeat('[', 70).str_repeat(']', 70), SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE],
    'unsupported push tag' => ['[]', SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_PUSH],
    'nonempty final' => ['[]', SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL],
]);

test('wrong recipient and malformed key pairs fail without exposing secrets', function (): void {
    $this->archive->write($this->path, $this->publicKey, [['secret' => 'never disclosed']]);

    foreach ([FeaturePreviewSnapshotArchive::generateKeyPair(), '', str_repeat('x', 64)] as $keyPair) {
        expect(fn () => iterator_to_array($this->archive->read($this->path, $keyPair)))
            ->toThrow(RuntimeException::class, 'The encrypted preview snapshot could not be processed safely.');
    }
});

test('snapshot does not overwrite an existing file', function (): void {
    file_put_contents($this->path, 'existing private backup');

    expect(fn () => $this->archive->write($this->path, $this->publicKey, [['id' => 1]]))->toThrow(RuntimeException::class)
        ->and(file_get_contents($this->path))->toBe('existing private backup');
});

test('snapshot requires an existing local parent and rejects stream wrappers', function (): void {
    foreach ([$this->directory.'/absent/snapshot.bin', 'php://memory', "bad\0path"] as $path) {
        expect(fn () => $this->archive->write($path, $this->publicKey, []))->toThrow(RuntimeException::class)
            ->and(fn () => iterator_to_array($this->archive->read($path, $this->keyPair)))->toThrow(RuntimeException::class);
    }

    expect(glob($this->directory.'/*'))->toBe([]);
});

test('invalid public keys leave no snapshot behind', function (string $key): void {
    expect(fn () => $this->archive->write($this->path, $key, []))->toThrow(RuntimeException::class)
        ->and(file_exists($this->path))->toBeFalse();
})->with(['', 'not-hex', str_repeat('g', 64), str_repeat('0', 64)]);

test('a source failure deletes its incomplete ciphertext without retaining the cause', function (): void {
    $records = (function (): Generator {
        yield ['id' => 1];
        throw new RuntimeException('private database connection secret');
    })();

    try {
        $this->archive->write($this->path, $this->publicKey, $records);
        $this->fail('Export should fail.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->not->toContain('private database')
            ->and($exception->getPrevious())->toBeNull()
            ->and(file_exists($this->path))->toBeFalse();
    }
});

test('invalid source records delete incomplete snapshots', function (mixed $record): void {
    expect(fn () => $this->archive->write($this->path, $this->publicKey, [['id' => 1], $record]))->toThrow(RuntimeException::class)
        ->and(file_exists($this->path))->toBeFalse();
})->with([
    'scalar' => ['plaintext'],
    'invalid UTF8' => [['value' => "\xff"]],
    'nonfinite number' => [['value' => INF]],
]);

test('oversized source records delete incomplete snapshots', function (): void {
    expect(fn () => $this->archive->write($this->path, $this->publicKey, [['payload' => str_repeat('x', FeaturePreviewSnapshotArchive::MAX_RECORD_BYTES)]]))
        ->toThrow(RuntimeException::class)
        ->and(file_exists($this->path))->toBeFalse();
});

test('snapshot streams its source and validates its ending only upon exhaustion', function (): void {
    $produced = 0;
    $records = (function () use (&$produced): Generator {
        for ($index = 0; $index < 1000; $index++) {
            $produced++;
            yield ['id' => $index];
        }
    })();
    $this->archive->write($this->path, $this->publicKey, $records);
    file_put_contents($this->path, substr(file_get_contents($this->path), 0, -21));
    $reader = $this->archive->read($this->path, $this->keyPair);

    expect($produced)->toBe(1000)
        ->and($reader->current())->toBe(['id' => 0])
        ->and(fn () => iterator_to_array($reader))->toThrow(RuntimeException::class);
});
