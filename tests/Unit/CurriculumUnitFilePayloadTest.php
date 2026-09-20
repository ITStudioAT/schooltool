<?php

use App\Http\Controllers\Admin\Teaching\CurriculumController;
use App\Models\TeachingCurriculum;
use App\Models\TeachingCurriculumDocument;
use App\Models\User;
use App\Services\Teaching\CurriculumUnitFileService;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->previousConnectionResolver = Model::getConnectionResolver();
    $this->previousContainer = Container::getInstance();
    Container::setInstance(new Application);
    Container::getInstance()->instance('config', new Repository);
    $this->connection = new SQLiteConnection(new PDO('sqlite::memory:'), ':memory:');
    $resolver = new ConnectionResolver(['curriculum_unit' => $this->connection]);
    $resolver->setDefaultConnection('curriculum_unit');
    Model::setConnectionResolver($resolver);
    $this->connection->statement('CREATE TABLE teaching_curriculum_documents (id INTEGER PRIMARY KEY, teaching_curriculum_id INTEGER, topic_id TEXT, unit_id TEXT, source_type TEXT, name TEXT, file_path TEXT, storage_disk TEXT, mime_type TEXT, size_bytes INTEGER, created_at TEXT, updated_at TEXT)');

    $this->curriculum = new TeachingCurriculum;
    $this->curriculum->forceFill([
        'id' => 18,
        'school_id' => 1,
        'user_id' => 2,
        'title' => 'Deutsch',
        'topics' => [[
            'id' => 'topic-1',
            'title' => 'Grammatik',
            'units' => [
                ['id' => 'unit-1', 'title' => 'Nebensätze'],
                ['id' => 'unit-2', 'title' => 'Satzzeichen'],
            ],
        ]],
    ]);
    $this->service = new CurriculumUnitFileService;
});

afterEach(function () {
    if ($this->previousConnectionResolver) {
        Model::setConnectionResolver($this->previousConnectionResolver);
    } else {
        Model::unsetConnectionResolver();
    }

    Container::setInstance($this->previousContainer);
    Mockery::close();
});

test('curriculum file disk mapping only redirects saved s3 in preview', function (bool $preview, ?string $storedDisk, string $expectedDisk): void {
    config(['schooltool.preview.instance' => $preview]);
    $document = (new TeachingCurriculumDocument)->forceFill(['storage_disk' => $storedDisk]);

    expect($this->service->diskName($document))->toBe($expectedDisk)
        ->and($document->storage_disk)->toBe($storedDisk);
})->with([
    'preview s3 copy' => [true, 's3', 'local'],
    'preview trimmed s3' => [true, ' s3 ', 'local'],
    'main s3' => [false, 's3', 's3'],
    'preview local' => [true, 'local', 'local'],
    'preview public' => [true, 'public', 'public'],
    'preview unknown' => [true, 'unknown-disk', 'unknown-disk'],
    'main unknown' => [false, 'unknown-disk', 'unknown-disk'],
    'preview default' => [true, null, 'local'],
]);

test('curriculum detail groups multiple unit files with protected urls in one query', function () {
    foreach ([
        ['unit_id' => 'unit-1', 'name' => 'Arbeitsblatt.pdf'],
        ['unit_id' => 'unit-1', 'name' => 'Lösung.pdf'],
        ['unit_id' => 'unit-2', 'name' => 'Übung.pdf'],
        ['unit_id' => 'unit-1', 'name' => 'Foreign.pdf', 'teaching_curriculum_id' => 99],
        ['unit_id' => 'unit-1', 'name' => 'Curriculum document.pdf', 'source_type' => 'upload'],
        ['unit_id' => 'missing', 'name' => 'Removed unit.pdf'],
    ] as $file) {
        TeachingCurriculumDocument::create($file + [
            'teaching_curriculum_id' => 18,
            'topic_id' => 'topic-1',
            'source_type' => 'unit_file',
            'file_path' => 'private/storage.pdf',
            'storage_disk' => 'local',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
        ]);
    }

    $this->connection->enableQueryLog();
    $payload = $this->service->curriculumDetailPayload($this->curriculum);

    expect($this->connection->getQueryLog())->toHaveCount(1)
        ->and($payload['unit_file_counts'])->toBe(['topic-1' => ['unit-2' => 1, 'unit-1' => 2]])
        ->and(array_keys($payload['unit_files']['topic-1']))->toBe(['unit-2', 'unit-1'])
        ->and(array_column($payload['unit_files']['topic-1']['unit-1'], 'name'))->toBe(['Lösung.pdf', 'Arbeitsblatt.pdf']);

    $file = $payload['unit_files']['topic-1']['unit-1'][0];
    expect($file['preview_url'])->toBe('/api/admin/teaching/curricula/18/topics/topic-1/units/unit-1/files/2/preview')
        ->and($file['download_url'])->toBe('/api/admin/teaching/curricula/18/topics/topic-1/units/unit-1/files/2/download')
        ->and($file['mime_type'])->toBe('application/pdf')
        ->and($file['size_bytes'])->toBe(1024)
        ->and($file)->not->toHaveKey('file_path')
        ->and($file)->not->toHaveKey('storage_disk');
});

test('curriculum detail without files returns empty attachment maps in one query', function () {
    $this->connection->enableQueryLog();
    $payload = $this->service->curriculumDetailPayload($this->curriculum);

    expect($payload['unit_files'])->toBe([])
        ->and($payload['unit_file_counts'])->toBe([])
        ->and($payload['topics'][0]['units'])->toHaveCount(2)
        ->and($this->connection->getQueryLog())->toHaveCount(1);
});

test('curriculum show provides the detail payload after checking its owner', function () {
    $user = (new User)->forceFill(['id' => 2, 'school_id' => 1]);
    $controller = Mockery::mock(CurriculumController::class)->makePartial();
    $controller->shouldReceive('userHasRole')->once()
        ->with(['admin', 'teaching_admin', 'teacher'])->andReturn($user);
    $factory = Mockery::mock(ResponseFactory::class);
    $factory->shouldReceive('json')->once()->with(Mockery::on(
        fn (array $data): bool => $data['data']['unit_files'] === [] && $data['data']['unit_file_counts'] === []
    ))->andReturn(new JsonResponse(['verified' => true]));
    Container::getInstance()->instance(ResponseFactory::class, $factory);

    expect($controller->show($this->curriculum, $this->service)->getData(true))->toBe(['verified' => true]);
});

test('curriculum show rejects unauthorized users before loading attachment data', function (bool $hasRole, int $userId, int $schoolId) {
    $user = $hasRole ? (new User)->forceFill(['id' => $userId, 'school_id' => $schoolId]) : false;
    $controller = Mockery::mock(CurriculumController::class)->makePartial();
    $controller->shouldReceive('userHasRole')->once()->andReturn($user);
    $this->connection->enableQueryLog();

    try {
        $controller->show($this->curriculum, $this->service);
        $this->fail('Unauthorized curriculum access was accepted.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403)
            ->and($this->connection->getQueryLog())->toBe([]);
    }
})->with([
    'missing role' => [false, 2, 1],
    'foreign owner' => [true, 3, 1],
    'foreign school' => [true, 2, 3],
]);
