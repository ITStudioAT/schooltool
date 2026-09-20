<?php

use App\Services\FeaturePreviewSnapshotArchive;
use App\Services\FeaturePreviewSnapshotFiles;
use Aws\CommandInterface;
use Aws\Result;
use Aws\S3\S3Client;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Filesystem\Filesystem;
use Psr\Http\Message\RequestInterface;

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

/** @param array<string, mixed> $configuration */
function previewS3FileFixture(string $source, array $configuration, Closure $handler): FeaturePreviewSnapshotFiles
{
    $configuration['default'] = 's3';
    $configuration['disks']['s3'] ??= ['driver' => 's3', 'bucket' => 'schooltool-fixture'];
    $client = new S3Client([
        'version' => 'latest', 'region' => 'eu-central-1',
        'credentials' => ['key' => 'fixture-access', 'secret' => 'fixture-secret'],
        'handler' => static fn (CommandInterface $command) => Create::promiseFor(new Result($handler($command))),
    ]);

    return new FeaturePreviewSnapshotFiles($source, $configuration, $client);
}

test('S3 source streams paginated scoped objects into isolated private copies without remote writes', function (): void {
    $binary = str_repeat("\x00\xff\x80", 800000);
    $objects = ['application/tenant/Schüler/file.bin' => $binary, 'application/tenant/empty' => ''];
    $commands = [];
    $configuration = $this->config;
    $configuration['disks']['s3'] = ['driver' => 's3', 'bucket' => 'schooltool-fixture', 'root' => 'application', 'prefix' => 'tenant'];
    file_put_contents($this->source.'/private/local.txt', 'local private');
    file_put_contents($this->source.'/public/image.txt', 'local public');
    $files = previewS3FileFixture($this->source, $configuration, function (CommandInterface $command) use ($objects, &$commands): array {
        $commands[] = $command->getName();
        expect($command['Bucket'])->toBe('schooltool-fixture');
        if ($command->getName() === 'ListObjectsV2') {
            expect($command['Prefix'])->toBe('application/tenant/');
            $second = isset($command['ContinuationToken']);
            if ($second) {
                expect($command['ContinuationToken'])->toBe('next-page');
            }
            $key = $second ? 'application/tenant/empty' : 'application/tenant/Schüler/file.bin';

            return ['Contents' => [
                ['Key' => $key, 'Size' => strlen($objects[$key]), 'ETag' => '"'.md5($objects[$key]).'"'],
                ...($second ? [['Key' => 'application/tenant/markers/', 'Size' => 0, 'ETag' => '"marker"']] : []),
            ], 'IsTruncated' => ! $second, ...($second ? [] : ['NextContinuationToken' => 'next-page'])];
        }
        expect($command->getName())->toBe('GetObject')
            ->and($command['@http']['stream'])->toBeTrue()
            ->and($command['IfMatch'])->toBe('"'.md5($objects[$command['Key']]).'"');

        return ['Body' => Utils::streamFor($objects[$command['Key']]), 'ContentLength' => strlen($objects[$command['Key']]), 'ETag' => $command['IfMatch']];
    });
    $files->assertSourceConfigurationSafe();
    expect(fn () => $files->assertConfigurationSafe())->toThrow(RuntimeException::class)
        ->and(fn () => iterator_to_array($files->records()))->toThrow(RuntimeException::class);
    $fingerprint = $files->fingerprint(true);
    $archive = new FeaturePreviewSnapshotArchive;
    $key = FeaturePreviewSnapshotArchive::generateKeyPair();
    $archivePath = $this->directory.'/snapshot.stpreview';
    $archive->write($archivePath, FeaturePreviewSnapshotArchive::publicKey($key), $files->records(true));
    $this->files->restore($archive->read($archivePath, $key), $this->staging);

    expect($files->fingerprint(true))->toBe($fingerprint)
        ->and(file_get_contents($this->staging.'/private/Schüler/file.bin'))->toBe($binary)
        ->and(file_get_contents($this->staging.'/private/local.txt'))->toBe('local private')
        ->and(file_get_contents($this->staging.'/public/image.txt'))->toBe('local public')
        ->and(filesize($this->staging.'/private/empty'))->toBe(0)
        ->and(file_get_contents($archivePath))->not->toContain('local private', 'local public')
        ->and(array_values(array_unique($commands)))->toBe(['ListObjectsV2', 'GetObject']);
});

test('S3 source accepts canonical decimal sizes and writes integer archive lengths', function (bool $stringLength): void {
    $files = previewS3FileFixture($this->source, $this->config, function (CommandInterface $command) use ($stringLength): array {
        if ($command->getName() === 'ListObjectsV2') {
            return ['IsTruncated' => false, 'Contents' => [
                ['Key' => 'file', 'Size' => '3', 'ETag' => '"file"'],
                ['Key' => 'empty', 'Size' => '0', 'ETag' => '"empty"'],
                ['Key' => 'folder/', 'Size' => '0', 'ETag' => '"folder"'],
            ]];
        }
        $body = $command['Key'] === 'file' ? 'abc' : '';

        return ['Body' => Utils::streamFor($body), 'ContentLength' => $stringLength ? (string) strlen($body) : strlen($body), 'ETag' => $command['IfMatch']];
    });
    $records = iterator_to_array($files->records(true), false);
    $starts = array_values(array_filter($records, fn (array $record): bool => $record['kind'] === 'file_start'));

    expect(array_column($starts, 'size'))->toBe([0, 3]);
    $this->files->restore($records, $this->staging);
    expect(file_get_contents($this->staging.'/private/file'))->toBe('abc')
        ->and(filesize($this->staging.'/private/empty'))->toBe(0);
})->with([false, true]);

test('S3 source handles file sizes returned by the real SDK XML parser', function (): void {
    $configuration = $this->config;
    $configuration['default'] = 's3';
    $configuration['disks']['s3'] = ['driver' => 's3', 'bucket' => 'schooltool-fixture'];
    $requests = [];
    $client = new S3Client([
        'version' => 'latest', 'region' => 'eu-central-1',
        'credentials' => ['key' => 'fixture-access', 'secret' => 'fixture-secret'],
        'http_handler' => static function (RequestInterface $request) use (&$requests) {
            $requests[] = $request->getMethod();
            if (str_contains($request->getUri()->getQuery(), 'list-type=2')) {
                return Create::promiseFor(new Response(200, ['Content-Type' => 'application/xml'],
                    '<ListBucketResult xmlns="http://s3.amazonaws.com/doc/2006-03-01/"><IsTruncated>false</IsTruncated><Contents><Key>file</Key><Size>3</Size><ETag>&quot;fixture&quot;</ETag></Contents></ListBucketResult>'));
            }
            expect($request->getHeaderLine('If-Match'))->toBe('"fixture"');

            return Create::promiseFor(new Response(200, ['Content-Length' => '3', 'ETag' => '"fixture"'], 'abc'));
        },
    ]);
    $files = new FeaturePreviewSnapshotFiles($this->source, $configuration, $client);
    $this->files->restore($files->records(true), $this->staging);

    expect(file_get_contents($this->staging.'/private/file'))->toBe('abc')
        ->and($requests)->toBe(['GET', 'GET']);
});

test('S3 inventory preserves the largest supported integer size without truncation', function (): void {
    $files = previewS3FileFixture($this->source, $this->config, function (CommandInterface $command): array {
        expect($command->getName())->toBe('ListObjectsV2');

        return ['IsTruncated' => false, 'Contents' => [
            ['Key' => 'large', 'Size' => (string) PHP_INT_MAX, 'ETag' => '"large"'],
        ]];
    });
    $inventory = (new ReflectionMethod($files, 'sourceInventory'))->invoke($files);

    expect($inventory['private/large']['size'])->toBe(PHP_INT_MAX);
});

test('S3 source rejects ambiguous negative and overflowing sizes', function (mixed $size, string $field): void {
    $reads = 0;
    $files = previewS3FileFixture($this->source, $this->config, function (CommandInterface $command) use ($size, $field, &$reads): array {
        if ($command->getName() === 'ListObjectsV2') {
            return ['IsTruncated' => false, 'Contents' => [
                ['Key' => 'file', 'Size' => $field === 'Size' ? $size : 3, 'ETag' => '"file"'],
            ]];
        }
        $reads++;

        return ['Body' => Utils::streamFor('abc'), 'ContentLength' => $size, 'ETag' => '"file"'];
    });

    expect(fn () => iterator_to_array($files->records(true), false))->toThrow(RuntimeException::class);
    if ($field === 'Size') {
        expect($reads)->toBe(0);
    }
})->with([
    'negative integer' => [-1], 'float' => [3.0], 'boolean' => [true], 'null' => [null],
    'empty' => [''], 'signed' => ['+3'], 'negative string' => ['-1'], 'leading zero' => ['03'],
    'whitespace' => [' 3'], 'trailing whitespace' => ["3\n"], 'fraction' => ['3.0'],
    'exponent' => ['3e0'], 'overflow' => [(string) PHP_INT_MAX.'0'], 'array' => [[]],
])->with(['Size', 'ContentLength']);

test('S3 source configuration cannot relax local target roots or transport safety', function (string $scenario): void {
    $configuration = $this->config;
    $configuration['disks']['s3'] = ['driver' => 's3', 'bucket' => 'schooltool-fixture'];
    match ($scenario) {
        'external local' => $configuration['disks']['local']['driver'] = 's3',
        'external public' => $configuration['disks']['public']['driver'] = 's3',
        'displaced local' => $configuration['disks']['local']['root'] = $this->directory,
        'insecure endpoint' => $configuration['disks']['s3']['endpoint'] = 'http://storage.example.test',
        'insecure scheme' => $configuration['disks']['s3']['scheme'] = 'http',
        'endpoint credentials' => $configuration['disks']['s3']['endpoint'] = 'https://user:secret@storage.example.test',
        'unverified TLS' => $configuration['disks']['s3']['http'] = ['verify' => false],
        'unsafe options' => $configuration['disks']['s3']['options'] = ['Bucket' => 'other'],
        'missing bucket' => $configuration['disks']['s3']['bucket'] = '',
        'unsafe root' => $configuration['disks']['s3']['root'] = '../other',
        'unsafe prefix' => $configuration['disks']['s3']['prefix'] = '/other',
    };
    $calls = 0;
    $files = previewS3FileFixture($this->source, $configuration, function () use (&$calls): array {
        $calls++;

        return [];
    });
    expect(fn () => $files->assertSourceConfigurationSafe())->toThrow(RuntimeException::class)
        ->and(fn () => iterator_to_array($files->records(true)))->toThrow(RuntimeException::class)
        ->and($calls)->toBe(0);
})->with(['external local', 'external public', 'displaced local', 'insecure endpoint', 'insecure scheme', 'endpoint credentials', 'unverified TLS', 'unsafe options', 'missing bucket', 'unsafe root', 'unsafe prefix']);

test('S3 source validates the complete destination inventory before reading object bodies', function (string $scenario): void {
    $keys = match ($scenario) {
        'same local path' => ['local.txt'],
        'local case collision' => ['LOCAL.txt'],
        'local directory collision' => ['folder'],
        'local file parent' => ['local.txt/child'],
        'object case collision' => ['one/File', 'one/file'],
        'object parent collision' => ['one', 'one/file'],
        'directory case collision' => ['Folder/object'],
    };
    file_put_contents($this->source.'/private/local.txt', 'keep');
    mkdir($this->source.'/private/folder');
    $reads = 0;
    $files = previewS3FileFixture($this->source, $this->config, function (CommandInterface $command) use ($keys, &$reads): array {
        if ($command->getName() !== 'ListObjectsV2') {
            $reads++;
        }

        return ['IsTruncated' => false, 'Contents' => array_map(fn (string $key): array => ['Key' => $key, 'Size' => 1, 'ETag' => '"etag"'], $keys)];
    });
    expect(fn () => iterator_to_array($files->records(true)))->toThrow(RuntimeException::class)
        ->and($reads)->toBe(0)
        ->and(file_get_contents($this->source.'/private/local.txt'))->toBe('keep');
})->with(['same local path', 'local case collision', 'local directory collision', 'local file parent', 'object case collision', 'object parent collision', 'directory case collision']);

test('S3 source rejects unsafe keys and objects outside its configured prefix', function (string $key): void {
    $configuration = $this->config;
    $configuration['disks']['s3'] = ['driver' => 's3', 'bucket' => 'schooltool-fixture', 'root' => 'app'];
    $files = previewS3FileFixture($this->source, $configuration, function (CommandInterface $command) use ($key): array {
        expect($command->getName())->toBe('ListObjectsV2');

        return ['IsTruncated' => false, 'Contents' => [['Key' => $key, 'Size' => 1, 'ETag' => '"etag"']]];
    });
    expect(fn () => iterator_to_array($files->records(true)))->toThrow(RuntimeException::class);
})->with(['other/file', 'application/file', 'app/../secret', 'app//absolute', 'app/dir\\file', 'app/CON.txt', 'app/file:', "app/invalid\xff", 'app/nonempty-marker/', 'app/']);

test('S3 export fails closed on incomplete pagination and changed or truncated objects', function (string $scenario): void {
    $files = previewS3FileFixture($this->source, $this->config, function (CommandInterface $command) use ($scenario): array {
        if ($command->getName() === 'ListObjectsV2') {
            return ['Contents' => [['Key' => 'file', 'Size' => 3, 'ETag' => '"initial"']],
                'IsTruncated' => in_array($scenario, ['missing continuation', 'repeated continuation'], true),
                ...($scenario === 'repeated continuation' ? ['NextContinuationToken' => 'same'] : [])];
        }
        if ($scenario === 'conditional failure') {
            throw new RuntimeException('AccessKey=fixture-secret sensitive upstream URL');
        }

        return [
            'Body' => Utils::streamFor(match ($scenario) {
                'short body' => 'ab', 'long body' => 'abcd', default => 'abc'
            }),
            'ContentLength' => $scenario === 'wrong content length' ? 4 : 3,
            'ETag' => $scenario === 'changed etag' ? '"changed"' : '"initial"',
        ];
    });
    try {
        iterator_to_array($files->records(true));
        $this->fail('Invalid S3 source was accepted.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('The preview file snapshot could not be processed safely.')
            ->and($exception->getPrevious())->toBeNull();
    }
})->with(['missing continuation', 'repeated continuation', 'conditional failure', 'short body', 'long body', 'wrong content length', 'changed etag']);

test('S3 fingerprints detect inventory and same size content changes independently of enumeration order', function (string $change): void {
    $objects = ['z' => 'abc', 'a' => 'def'];
    $files = previewS3FileFixture($this->source, $this->config, function (CommandInterface $command) use (&$objects): array {
        if ($command->getName() === 'ListObjectsV2') {
            $contents = [];
            foreach ($objects as $key => $bytes) {
                $contents[] = ['Key' => $key, 'Size' => strlen($bytes), 'ETag' => '"'.md5($bytes).'"'];
            }

            return ['IsTruncated' => false, 'Contents' => $contents];
        }

        return ['Body' => Utils::streamFor($objects[$command['Key']]), 'ContentLength' => strlen($objects[$command['Key']]), 'ETag' => $command['IfMatch']];
    });
    $before = $files->fingerprint(true);
    $objects = array_reverse($objects, true);
    expect($files->fingerprint(true))->toBe($before);
    match ($change) {
        'add' => $objects['new'] = 'new',
        'remove' => $objects = ['a' => 'def'],
        'same size' => $objects['z'] = 'xyz',
    };
    expect($files->fingerprint(true))->not->toBe($before);
})->with(['add', 'remove', 'same size']);
