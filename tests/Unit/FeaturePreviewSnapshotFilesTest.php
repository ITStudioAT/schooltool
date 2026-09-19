<?php

use App\Services\FeaturePreviewSnapshotFiles;
use Illuminate\Filesystem\Filesystem;

beforeEach(function (): void {
    $this->directory = sys_get_temp_dir().'/schooltool-file-snapshot-'.bin2hex(random_bytes(8));
    $this->source = $this->directory.'/source';
    $this->staging = $this->directory.'/staging';
    mkdir($this->source.'/private', 0700, true);
    mkdir($this->source.'/public', 0700);
    mkdir($this->staging, 0700);
    $this->config = [
        'default' => 'local',
        'disks' => [
            'local' => ['driver' => 'local', 'root' => $this->source.'/private'],
            'public' => ['driver' => 'local', 'root' => $this->source.'/public'],
        ],
    ];
    $this->files = new FeaturePreviewSnapshotFiles($this->source, $this->config);
});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory($this->directory);
});

/** @return list<array<string, mixed>> */
function previewFileFixture(string $path, string $content = 'file contents'): array
{
    $records = [['kind' => 'file_start', 'path' => $path, 'size' => strlen($content)]];

    if ($content !== '') {
        $records[] = ['kind' => 'file_chunk', 'data' => base64_encode($content)];
    }

    $records[] = ['kind' => 'file_end', 'sha256' => hash('sha256', $content)];

    return $records;
}

test('private and public files round trip as independent private copies with bounded chunks', function (): void {
    mkdir($this->source.'/private/Schüler', 0700);
    $binary = str_repeat("\x00\xff\x80", 800000);
    file_put_contents($this->source.'/private/Schüler/document.bin', $binary);
    file_put_contents($this->source.'/public/photo.txt', 'public content');
    file_put_contents($this->source.'/private/empty', '');
    file_put_contents($this->source.'/not-copied.env', 'private configuration');
    $maxChunk = 0;
    $chunks = 0;
    $records = (function () use (&$maxChunk, &$chunks): Generator {
        foreach ($this->files->records() as $record) {
            if ($record['kind'] === 'file_chunk') {
                $maxChunk = max($maxChunk, strlen(base64_decode($record['data'], true)));
                $chunks++;
            }

            yield $record;
        }
    })();

    $this->files->restore($records, $this->staging);

    expect(file_get_contents($this->staging.'/private/Schüler/document.bin'))->toBe($binary)
        ->and(file_get_contents($this->staging.'/public/photo.txt'))->toBe('public content')
        ->and(filesize($this->staging.'/private/empty'))->toBe(0)
        ->and(file_exists($this->staging.'/not-copied.env'))->toBeFalse()
        ->and($maxChunk)->toBe(FeaturePreviewSnapshotFiles::CHUNK_BYTES)
        ->and($chunks)->toBe(4);

    file_put_contents($this->staging.'/public/photo.txt', 'changed preview');
    expect(file_get_contents($this->source.'/public/photo.txt'))->toBe('public content');

    if (PHP_OS_FAMILY !== 'Windows') {
        expect(fileperms($this->staging.'/private/Schüler') & 0777)->toBe(0700)
            ->and(fileperms($this->staging.'/public/photo.txt') & 0777)->toBe(0600);
    }
});

test('empty source disks restore only their isolated roots', function (): void {
    $this->files->restore($this->files->records(), $this->staging);

    expect(is_dir($this->staging.'/private'))->toBeTrue()
        ->and(is_dir($this->staging.'/public'))->toBeTrue();
});

test('file tree fingerprints detect additions removals renames and same size content changes', function (string $change): void {
    file_put_contents($this->source.'/private/one', 'abc');
    $before = $this->files->fingerprint();

    expect($this->files->fingerprint())->toBe($before);

    match ($change) {
        'add file' => file_put_contents($this->source.'/public/two', 'def'),
        'remove file' => unlink($this->source.'/private/one'),
        'rename file' => rename($this->source.'/private/one', $this->source.'/private/renamed'),
        'replace contents' => file_put_contents($this->source.'/private/one', 'xyz'),
        'add directory' => mkdir($this->source.'/public/empty', 0700),
        'remove root' => rmdir($this->source.'/public'),
    };

    expect($this->files->fingerprint())->not->toBe($before);
})->with(['add file', 'remove file', 'rename file', 'replace contents', 'add directory', 'remove root']);

test('file tree fingerprints do not depend on directory enumeration order', function (): void {
    file_put_contents($this->source.'/private/z', 'last');
    file_put_contents($this->source.'/private/a', 'first');
    $before = $this->files->fingerprint();
    unlink($this->source.'/private/z');
    unlink($this->source.'/private/a');
    file_put_contents($this->source.'/private/a', 'first');
    file_put_contents($this->source.'/private/z', 'last');

    expect($this->files->fingerprint())->toBe($before);
});

test('file snapshot rejects external or displaced disk configuration', function (string $scenario): void {
    match ($scenario) {
        'external default' => $this->config['default'] = 's3',
        'external local' => $this->config['disks']['local']['driver'] = 'sftp',
        'external public' => $this->config['disks']['public']['driver'] = 's3',
        'different local root' => $this->config['disks']['local']['root'] = $this->directory,
        'different public root' => $this->config['disks']['public']['root'] = $this->source.'/private',
        'missing default' => $this->config['default'] = null,
        'missing public config' => $this->config['disks']['public'] = null,
    };
    $files = new FeaturePreviewSnapshotFiles($this->source, $this->config);

    expect(fn () => $files->assertConfigurationSafe())->toThrow(RuntimeException::class)
        ->and(fn () => iterator_to_array($files->records()))->toThrow(RuntimeException::class);
})->with(['external default', 'external local', 'external public', 'different local root', 'different public root', 'missing default', 'missing public config']);

test('snapshot rejects unsafe file names before creating any file', function (string $path): void {
    expect(fn () => $this->files->restore(previewFileFixture($path), $this->staging))->toThrow(RuntimeException::class)
        ->and(glob($this->staging.'/private/*'))->toBe([])
        ->and(glob($this->staging.'/public/*'))->toBe([]);
})->with([
    '../escape', '/private/absolute', 'C:/Windows/file', 'private/../escape', 'private/./file',
    'private//file', 'private/dir\\escape', 'other/file', 'private/file:stream', 'private/CON.txt',
    'public/lpt1', 'private/file.', 'private/file ', "private/null\0byte", "private/control\nline", 'private',
    'private/'.str_repeat('a', 256), "private/invalid\xff", 'private/php://filter',
]);

test('snapshot rejects duplicate and colliding paths across operating systems', function (string $first, string $second): void {
    $records = [...previewFileFixture($first), ...previewFileFixture($second)];

    expect(fn () => $this->files->restore($records, $this->staging))->toThrow(RuntimeException::class);
})->with([
    ['private/file', 'private/file'],
    ['private/File', 'private/file'],
    ['private/file', 'private/file/child'],
    ['private/Folder/child', 'private/Folder'],
    ['private/Folder/one', 'private/folder/two'],
]);

test('snapshot rejects malformed ordering sizes chunks and hashes', function (string $scenario): void {
    $records = previewFileFixture('private/file');

    match ($scenario) {
        'missing start' => array_shift($records),
        'missing end' => array_pop($records),
        'nested start' => array_splice($records, 1, 0, [$records[0]]),
        'negative size' => $records[0]['size'] = -1,
        'string size' => $records[0]['size'] = '13',
        'too short' => $records[0]['size'] = 1,
        'too long' => $records[0]['size'] = 100,
        'invalid base64' => $records[1]['data'] = '%invalid%',
        'noncanonical base64' => $records[1]['data'] .= "\n",
        'empty chunk' => $records[1]['data'] = '',
        'wrong hash' => $records[2]['sha256'] = str_repeat('0', 64),
        'malformed hash' => $records[2]['sha256'] = 'sha256',
        'extra metadata' => $records[0]['symlink'] = '/live',
        'unknown kind' => $records[1]['kind'] = 'database_row',
        'oversized chunk' => $records[1]['data'] = base64_encode(str_repeat('x', FeaturePreviewSnapshotFiles::CHUNK_BYTES + 1)),
        'scalar record' => $records[0] = 'unsafe',
    };

    expect(fn () => $this->files->restore($records, $this->staging))->toThrow(RuntimeException::class);
})->with([
    'missing start', 'missing end', 'nested start', 'negative size', 'string size', 'too short', 'too long',
    'invalid base64', 'noncanonical base64', 'empty chunk', 'wrong hash', 'malformed hash', 'extra metadata',
    'unknown kind', 'oversized chunk', 'scalar record',
]);

test('snapshot never restores into source or nonempty staging', function (): void {
    file_put_contents($this->staging.'/existing', 'keep');
    file_put_contents($this->source.'/private/live', 'live contents');

    foreach ([$this->source, $this->source.'/private', $this->directory, $this->staging] as $path) {
        expect(fn () => $this->files->restore(previewFileFixture('private/file'), $path))->toThrow(RuntimeException::class);
    }

    expect(file_get_contents($this->source.'/private/live'))->toBe('live contents')
        ->and(file_get_contents($this->staging.'/existing'))->toBe('keep');
});

test('source changes are prevented by the file lock or abort the snapshot', function (string $change): void {
    $path = $this->source.'/private/document';
    file_put_contents($path, str_repeat('a', FeaturePreviewSnapshotFiles::CHUNK_BYTES + 1));
    $records = $this->files->records();
    expect($records->current()['kind'])->toBe('file_start');
    $records->next();
    expect($records->current()['kind'])->toBe('file_chunk');
    $handle = fopen($path, 'r+b');

    if ($change === 'grown') {
        fseek($handle, 0, SEEK_END);
    }

    $lockDeniedWrite = false;
    set_error_handler(function (int $severity, string $message) use (&$lockDeniedWrite): bool {
        if (PHP_OS_FAMILY === 'Windows' && str_starts_with($message, 'fwrite():') && str_contains($message, 'errno=13')) {
            $lockDeniedWrite = true;

            return true;
        }

        return false;
    });

    try {
        $changed = match ($change) {
            'same size' => fwrite($handle, 'b') === 1,
            'truncated' => ftruncate($handle, 10),
            'grown' => fwrite($handle, 'extra') === 5,
        };
    } finally {
        restore_error_handler();
        fclose($handle);
    }

    $consume = function () use ($records): void {
        while ($records->valid()) {
            $records->next();
        }
    };

    if (! $changed) {
        $consume();
        expect($lockDeniedWrite)->toBeTrue()
            ->and(file_get_contents($path))->toBe(str_repeat('a', FeaturePreviewSnapshotFiles::CHUNK_BYTES + 1));

        return;
    }

    expect($consume)->toThrow(RuntimeException::class);
})->with(['same size', 'truncated', 'grown']);

test('source symlinks and hardlinks are refused without copying their targets', function (string $kind): void {
    $target = $this->directory.'/secret';
    file_put_contents($target, 'outside secret');
    $link = $this->source.'/private/link';

    if ($kind === 'symlink' && PHP_OS_FAMILY === 'Windows') {
        $this->markTestSkipped('Creating symbolic links requires a Windows privilege not needed by the application.');
    }

    if ($kind === 'symlink') {
        symlink($target, $link);
    } else {
        link($target, $link);
    }

    try {
        expect(fn () => iterator_to_array($this->files->records()))->toThrow(RuntimeException::class)
            ->and(file_get_contents($target))->toBe('outside secret');
    } finally {
        unlink($link);
    }
})->with(['symlink', 'hardlink']);

test('linked storage roots and staging ancestors are refused', function (): void {
    if (PHP_OS_FAMILY === 'Windows') {
        $this->markTestSkipped('Creating symbolic links requires Windows privileges.');
    }

    $alias = $this->directory.'/alias';
    symlink($this->source, $alias);
    $config = $this->config;
    $config['disks']['local']['root'] = $alias.'/private';
    $config['disks']['public']['root'] = $alias.'/public';
    $files = new FeaturePreviewSnapshotFiles($alias, $config);

    try {
        expect(fn () => $files->assertConfigurationSafe())->toThrow(RuntimeException::class)
            ->and(fn () => $this->files->restore([], $alias.'/private'))->toThrow(RuntimeException::class);
    } finally {
        unlink($alias);
    }
});
