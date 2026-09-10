<?php

use App\Http\Controllers\Admin\Teaching\TeachingBackupController;
use App\Models\TeachingBackup;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->controller = Mockery::mock(TeachingBackupController::class)->makePartial();
    $this->controller->shouldReceive('userHasRole')
        ->once()
        ->with(['admin', 'teaching_admin'])
        ->andReturn(new User(['school_id' => 10, 'schoolyear_id' => 20]));
    $this->backup = new TeachingBackup([
        'school_id' => 10,
        'schoolyear_id' => 20,
        'disk' => 'local',
        'path' => 'teaching-backups/test.zip',
        'filename' => 'Sicherung.zip',
        'summary' => ['container_format' => 'zip'],
    ]);
});

test('backup downloads preserve binary bytes across multiple chunks and private headers', function (string $format, string $contentType) {
    $content = $format === 'zip'
        ? "PK\x03\x04".str_repeat("\x00\xff\x80\r\n", 5000)
        : json_encode(['data' => str_repeat('Unterricht', 3000)], JSON_THROW_ON_ERROR);
    $this->backup->filename = "Sicherung.{$format}";
    $this->backup->summary = ['container_format' => $format];
    Storage::disk('local')->put($this->backup->path, $content);

    $response = TestResponse::fromBaseResponse($this->controller->download($this->backup));

    $response->assertOk()
        ->assertDownload("Sicherung.{$format}")
        ->assertHeader('Content-Type', $contentType)
        ->assertHeader('Content-Length', (string) strlen($content))
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->headers->get('Cache-Control'))->toContain('private', 'no-store')
        ->and($response->streamedContent())->toBe($content);
})->with([
    'ZIP' => ['zip', 'application/zip'],
    'legacy JSON' => ['json', 'application/json'],
]);

test('backup downloads reject another school or schoolyear', function (string $scope) {
    $this->backup->{$scope} = 99;

    try {
        $this->controller->download($this->backup);
        $this->fail('A backup outside the active scope was downloadable.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }
})->with(['school_id', 'schoolyear_id']);

test('backup downloads return not found for a missing file', function () {
    try {
        $this->controller->download($this->backup);
        $this->fail('A missing backup did not return 404.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(404);
    }
});
