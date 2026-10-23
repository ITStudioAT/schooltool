<?php

use App\Models\DropboxConnection;
use App\Services\DropboxFolderContents;
use App\Services\DropboxWorkImport;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class);

function contentsFolder(string $id = 'id:root', string $path = '/materials/work'): array
{
    return ['id' => $id, 'name' => basename($path), 'path_lower' => $path, 'path_display' => $path];
}

function contentsResponse(array $entries, bool $more = false, ?string $cursor = null): Response
{
    return new Response(new PsrResponse(200, [], json_encode(['entries' => $entries, 'has_more' => $more, 'cursor' => $cursor])));
}

function contentsPdfMetadata(array $override = []): array
{
    return array_replace(['.tag' => 'file', 'id' => 'id:pdf', 'name' => 'Aufgabe.pdf', 'rev' => 'revision1',
        'path_lower' => '/materials/work/aufgabe.pdf', 'size' => strlen("%PDF-1.7\n%%EOF\n")], $override);
}

test('PDF reading uses the checked revision and rechecks file and folder before returning bytes', function (): void {
    $dropbox = Mockery::mock(DropboxWorkImport::class);
    $connection = new DropboxConnection;
    $dropbox->shouldReceive('metadata')->with($connection, 'id:root')->twice()->andReturn(contentsFolder());
    $dropbox->shouldReceive('readModuleApi')->with($connection, 'files/get_metadata', ['path' => 'id:pdf'])->twice()
        ->andReturn(new Response(new PsrResponse(200, [], json_encode(contentsPdfMetadata()))));
    $dropbox->shouldReceive('readModuleApi')->with($connection, 'files/download', ['path' => 'rev:revision1'])->once()
        ->andReturn(new Response(new PsrResponse(200, [], "%PDF-1.7\n%%EOF\n")));

    expect((new DropboxFolderContents($dropbox))->pdf($connection, 'id:root', 'id:pdf'))
        ->toBe(['name' => 'Aufgabe.pdf', 'bytes' => "%PDF-1.7\n%%EOF\n"]);
});

test('PDF reading refuses foreign files non PDFs and oversized files before downloading', function (array $override): void {
    $dropbox = Mockery::mock(DropboxWorkImport::class);
    $connection = new DropboxConnection;
    $dropbox->shouldReceive('metadata')->with($connection, 'id:root')->once()->andReturn(contentsFolder());
    $dropbox->shouldReceive('readModuleApi')->with($connection, 'files/get_metadata', ['path' => 'id:pdf'])->once()
        ->andReturn(new Response(new PsrResponse(200, [], json_encode(contentsPdfMetadata($override)))));

    expect(fn () => (new DropboxFolderContents($dropbox))->pdf($connection, 'id:root', 'id:pdf'))->toThrow(HttpException::class);
})->with([
    'other folder' => [['path_lower' => '/other/aufgabe.pdf']],
    'other file' => [['id' => 'id:other']],
    'non PDF' => [['name' => 'Aufgabe.html']],
    'too large' => [['size' => 20971521]],
]);

test('folder contents include files and folders and preserve pagination without downloading files', function (): void {
    $dropbox = Mockery::mock(DropboxWorkImport::class);
    $connection = new DropboxConnection;
    $dropbox->shouldReceive('metadata')->with($connection, 'id:root')->twice()->andReturn(contentsFolder());
    $dropbox->shouldReceive('readModuleApi')->once()->with($connection, 'files/list_folder',
        ['path' => 'id:root', 'recursive' => false, 'limit' => 200])->andReturn(contentsResponse([
            ['.tag' => 'folder', 'id' => 'id:child', 'name' => 'Angaben', 'path_lower' => '/materials/work/angaben'],
            ['.tag' => 'file', 'id' => 'id:pdf', 'name' => 'Aufgabe.pdf', 'path_lower' => '/materials/work/aufgabe.pdf', 'size' => 0],
        ], true, 'next-page'));

    $result = (new DropboxFolderContents($dropbox))->read($connection, 'id:root');

    expect(array_column($result['entries'], 'type'))->toBe(['folder', 'file'])
        ->and($result['entries'][1]['size'])->toBe(0)
        ->and($result['cursor'])->toBe('next-page');
});

test('folder contents refuse folders outside the linked root including shared path prefixes', function (): void {
    $dropbox = Mockery::mock(DropboxWorkImport::class);
    $connection = new DropboxConnection;
    $dropbox->shouldReceive('metadata')->with($connection, 'id:root')->once()->andReturn(contentsFolder());
    $dropbox->shouldReceive('metadata')->with($connection, 'id:other')->once()->andReturn(contentsFolder('id:other', '/materials/work-copy/private'));
    $dropbox->shouldNotReceive('readModuleApi');

    try {
        (new DropboxFolderContents($dropbox))->read($connection, 'id:root', 'id:other');
        $this->fail('Outside folder must be rejected');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }
});

test('a foreign pagination cursor cannot expose entries from another folder', function (): void {
    $dropbox = Mockery::mock(DropboxWorkImport::class);
    $connection = new DropboxConnection;
    $dropbox->shouldReceive('metadata')->with($connection, 'id:root')->once()->andReturn(contentsFolder());
    $dropbox->shouldReceive('readModuleApi')->once()->with($connection, 'files/list_folder/continue', ['cursor' => 'foreign'])
        ->andReturn(contentsResponse([['.tag' => 'file', 'id' => 'id:private', 'name' => 'private.pdf', 'path_lower' => '/other/private.pdf']]));

    expect(fn () => (new DropboxFolderContents($dropbox))->read($connection, 'id:root', null, 'foreign'))->toThrow(HttpException::class);
});

test('folder navigation stays within the root and rechecks both folder locations', function (): void {
    $dropbox = Mockery::mock(DropboxWorkImport::class);
    $connection = new DropboxConnection;
    $dropbox->shouldReceive('metadata')->with($connection, 'id:root')->twice()->andReturn(contentsFolder());
    $dropbox->shouldReceive('metadata')->with($connection, 'id:child')->twice()->andReturn(contentsFolder('id:child', '/materials/work/angaben'));
    $dropbox->shouldReceive('readModuleApi')->once()->with($connection, 'files/list_folder',
        ['path' => 'id:child', 'recursive' => false, 'limit' => 200])->andReturn(contentsResponse([]));

    expect((new DropboxFolderContents($dropbox))->read($connection, 'id:root', 'id:child')['current']['id'])->toBe('id:child');
});

test('folder contents stop when the root moves during the request', function (): void {
    $dropbox = Mockery::mock(DropboxWorkImport::class);
    $connection = new DropboxConnection;
    $dropbox->shouldReceive('metadata')->with($connection, 'id:root')->twice()->andReturn(contentsFolder(), contentsFolder('id:root', '/moved/work'));
    $dropbox->shouldReceive('readModuleApi')->once()->andReturn(contentsResponse([]));

    expect(fn () => (new DropboxFolderContents($dropbox))->read($connection, 'id:root'))->toThrow(HttpException::class);
});

test('an incomplete provider listing is never presented as complete', function (): void {
    $dropbox = Mockery::mock(DropboxWorkImport::class);
    $connection = new DropboxConnection;
    $dropbox->shouldReceive('metadata')->with($connection, 'id:root')->once()->andReturn(contentsFolder());
    $dropbox->shouldReceive('readModuleApi')->once()->andReturn(contentsResponse([], true));

    expect(fn () => (new DropboxFolderContents($dropbox))->read($connection, 'id:root'))->toThrow(HttpException::class);
});
