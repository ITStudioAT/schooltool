<?php

use App\Services\TeachingSynchronisationFiles;
use Aws\Result;
use Aws\S3\S3ClientInterface;
use GuzzleHttp\Psr7\Utils;

test('teaching file paths reject traversal absolute paths and Windows separators', function (string $path) {
    expect(fn () => TeachingSynchronisationFiles::assertPath($path))->toThrow(RuntimeException::class);
})->with(['../secret', '/etc/passwd', 'teaching/../secret', 'C:/secret', 'teaching\\secret', 'teaching//secret']);

test('teaching S3 reads use conditional requests and rewrite documents into private local storage', function () {
    $client = Mockery::mock(S3ClientInterface::class);
    $head = new Result(['ETag' => '"v1"', 'ContentLength' => 3, 'VersionId' => 'v1']);
    $client->shouldReceive('headObject')->twice()->with(['Bucket' => 'fixture', 'Key' => 'teaching/file.pdf'])->andReturn($head);
    $client->shouldReceive('getObject')->once()->with(['Bucket' => 'fixture', 'Key' => 'teaching/file.pdf', 'IfMatch' => '"v1"'])
        ->andReturn(new Result(['Body' => Utils::streamFor('PDF')]));
    $service = new TeachingSynchronisationFiles($client);
    $tables = ['teaching_curriculum_documents' => [['id' => 1, 'file_path' => 'teaching/file.pdf', 'storage_disk' => 's3']]];
    $config = ['default' => 'local', 'disks' => ['s3' => ['driver' => 's3', 'region' => 'eu-central-1',
        'bucket' => 'fixture', 'key' => 'fixture', 'secret' => 'fixture']]];
    $files = $service->capture($tables, $config, true);
    $rewritten = $service->rewrite($tables, $files, 1);
    expect($rewritten['tables']['teaching_curriculum_documents'][0]['storage_disk'])->toBe('local')
        ->and($rewritten['files'][0]['path'])->toStartWith('teaching/synchronisation/1/')
        ->and(base64_decode($rewritten['files'][0]['content']))->toBe('PDF');
    expect(fn () => $service->capture($tables, $config))->toThrow(RuntimeException::class, 'S3');
});

test('changed S3 objects abort rather than producing a mixed snapshot', function () {
    $client = Mockery::mock(S3ClientInterface::class);
    $client->shouldReceive('headObject')->andReturn(new Result(['ETag' => '"v1"', 'ContentLength' => 3]), new Result(['ETag' => '"v2"', 'ContentLength' => 3]));
    $client->shouldReceive('getObject')->andReturn(new Result(['Body' => Utils::streamFor('PDF')]));
    $service = new TeachingSynchronisationFiles($client);
    expect(fn () => $service->capture(['teaching_curriculum_documents' => [['file_path' => 'teaching/file.pdf', 'storage_disk' => 's3']]],
        ['default' => 'local', 'disks' => ['s3' => ['driver' => 's3', 'region' => 'eu-central-1', 'bucket' => 'fixture', 'key' => 'fixture', 'secret' => 'fixture']]], true))
        ->toThrow(RuntimeException::class, 'geändert');
});

test('import source downloads retain their authorized year directory after remapping', function () {
    $service = new TeachingSynchronisationFiles;
    $path = 'app/private/1/import116-sources/10/source.xlsx';
    $file = ['disk' => 'local', 'path' => $path, 'size' => 4, 'sha256' => hash('sha256', 'XLSX'), 'content' => base64_encode('XLSX')];
    $result = $service->rewrite(['import116_runs' => [['id' => 1, 'schoolyear_id' => 30, 'source_path' => $path]]], [$file], 1);
    expect($result['tables']['import116_runs'][0]['source_path'])->toStartWith('app/private/1/import116-sources/30/')
        ->and($result['files'][0]['path'])->toStartWith('1/import116-sources/30/');
});

test('file tampering and conflicting disk paths block the transfer', function () {
    $service = new TeachingSynchronisationFiles;
    $file = ['disk' => 's3', 'path' => 'teaching/file.pdf', 'size' => 3, 'sha256' => hash('sha256', 'PDF'), 'content' => base64_encode('BAD')];
    expect(fn () => $service->rewrite([], [$file], 1))->toThrow(RuntimeException::class, 'Dateiprüfsumme');
});
