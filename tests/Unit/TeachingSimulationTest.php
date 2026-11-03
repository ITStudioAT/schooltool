<?php

use App\Ai\Agents\TeachingSimulationAnswerAgent;
use App\Models\DropboxConnection;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseWork;
use App\Models\User;
use App\Services\DropboxFolderContents;
use App\Services\DropboxModuleLinks;
use App\Services\DropboxWorkImport;
use App\Services\TeachingSimulation;
use App\Services\TeachingSimulationAnswers;
use App\Services\TeachingSimulationMailpit;
use App\Services\TeachingWorkAiExport;
use App\Support\SchooltoolAssessmentJson;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\TeachingWorkJsonFixture;
use Tests\TestCase;

uses(TestCase::class);

test('simulator results are adapted only locally for the verified work and retain their test marking', function (): void {
    $run = '9cd76a71-9639-42d8-aa20-3d1b0d00ccba';
    $work = (new TeachingCourseWork)->forceFill(['id' => 86, 'teaching_course_id' => 16]);
    $service = Mockery::mock(TeachingSimulation::class)->makePartial();
    $service->shouldReceive('collectionStatus')->with($work, 7, [1])->andReturn(['run_id' => $run, 'complete' => true,
        'evaluation' => ['completed_count' => 1, 'rows' => [['student_id' => 1, 'state' => 'complete']]]]);
    $package = TeachingWorkJsonFixture::package();
    $package['records'][0]['identity']['schooltool_person_id'] = '1';
    $package['kind'] = 'schooltool.simulation-assessment';
    $package += ['simulation' => true, 'live_import_allowed' => false, 'run_id' => $run, 'work_id' => 86, 'course_id' => 16];
    $package['exercise_id'] = 'simulation:'.$run.':work:86';
    $directory = 'teaching-simulator/runs/7/'.$run.'/Auswertungen';
    $disk = Storage::disk('local');
    $bytes = json_encode($package);
    $disk->put($directory.'/Simulator-Bewertungen.json', $bytes);
    $disk->put($directory.'/Bewertungsdaten_Simulation.json', json_encode(['records' => [['id' => 'FICTION-001', 'total' => '4,75']]]));
    $disk->put($directory.'/PDF-Freigabe_Simulation.json', json_encode(['pdfs' => []]));
    $service->withResultFiles($work, 7, [1], function (array $parameters, array $files): void {
        $parsed = app(SchooltoolAssessmentJson::class)->parse($files['package']->get());
        expect($parameters)->toBe([])->and($parsed['records'][0]['total_minor'])->toBe(475)
            ->and($parsed['records'][0]['comment'])->toStartWith('Simulation · lokale Testbewertung');
    });
    expect($disk->get($directory.'/Simulator-Bewertungen.json'))->toBe($bytes);
    $this->app['env'] = 'production';
    expect(fn () => $service->withResultFiles($work, 7, [1], fn () => null))->toThrow(HttpException::class);
});

beforeEach(function (): void {
    $this->app['env'] = 'local';
    Storage::fake('local');
    Http::preventStrayRequests();
    TeachingSimulationAnswerAgent::fake([['variants' => array_map(fn (string $quality): array => [
        'quality' => $quality, 'subject' => 'Meine Ausarbeitung', 'message' => 'Sehr geehrter Herr Professor, hier ist meine Ausarbeitung.',
        'answer' => $quality.' Phishing: ungewöhnliche Absenderadresse und Zeitdruck. Keine Links öffnen; über einen anderen Kanal nachfragen.', 'files' => [],
    ], ['good', 'partial', 'weak']), 'warnings' => []]])->preventStrayPrompts();
});

function simulationFixture(bool $localCopy = false): array
{
    $actor = (new User)->forceFill(['id' => 7, 'email' => 'teacher@example.test']);
    $work = (new TeachingCourseWork)->forceFill(['id' => 86, 'teaching_course_id' => 16, 'title' => 'E-Mails']);
    $people = array_map(fn (int $id): array => ['participant_id' => 'user:'.$id, 'first_name' => 'Person', 'last_name' => 'Test'.$id,
        'class_name' => '3B', 'schooltool_person_id' => (string) $id, 'email' => 'person'.$id.'@example.test'], range(1, 7));
    $export = Mockery::mock(TeachingWorkAiExport::class);
    $export->shouldReceive('buildSubmissionCollection')->with($work, $actor, false, null)->andReturn(['context' => [
        'course_roster' => ['complete' => true, 'participants' => $people], 'exercise_folder' => ['id' => 'id:exercise'],
        'course' => ['title' => 'DGB - 3B - 1'], 'work' => ['main_deadline' => ['date' => '2026-10-25', 'time' => '10:00']],
    ]]);
    $connection = new DropboxConnection;
    $dropbox = Mockery::mock(DropboxWorkImport::class);
    if (! $localCopy) {
        $dropbox->shouldReceive('connection')->with($actor)->andReturn($connection);
    }
    $folders = Mockery::mock(DropboxFolderContents::class);
    $pdf = Pdf::loadHTML('<p>Erstellen Sie eine PDF über zwei Phishing-Warnzeichen und zwei Verhaltensweisen. Senden Sie sie an teacher@example.test.</p>')->output();
    if ($localCopy) {
        $path = 'teaching/work_tasks/1/86/local-copy.pdf';
        Storage::disk('local')->put($path, $pdf);
        $work->setRelation('teachingCourse', (new TeachingCourse)->forceFill(['school_id' => 1]));
        $work->status = ['local_test_copy' => ['source_work_id' => 90], 'student_task_files' => [
            ['name' => 'Aufgabenstellung.pdf', 'storage_disk' => 'local', 'file_path' => $path, 'sha256' => hash('sha256', $pdf)],
        ]];
    }
    $folders->shouldReceive('read')->with($connection, 'id:exercise')->andReturn(['cursor' => null,
        'current' => ['id' => 'id:exercise'], 'entries' => [['id' => 'id:task', 'name' => 'Aufgabenstellung.pdf', 'size' => strlen($pdf), 'type' => 'file']]]);
    $folders->shouldReceive('pdf')->with($connection, 'id:exercise', 'id:task')->andReturn(['name' => 'Aufgabenstellung.pdf', 'bytes' => $pdf]);
    $mailpit = Mockery::mock(TeachingSimulationMailpit::class);
    $mailpit->shouldReceive('assertSafe')->andReturnNull();
    $service = new TeachingSimulation($export, Mockery::mock(DropboxModuleLinks::class), $dropbox, $folders, app(TeachingSimulationAnswers::class), $mailpit);

    return [$service, $mailpit, $work, $actor];
}

test('a local test copy prepares simulated submissions from its private PDF without Dropbox', function (): void {
    [$service, , $work, $actor] = simulationFixture(localCopy: true);
    $original = $work->getAttributes();
    $run = $service->create($work, $actor, 'mixed', null);
    expect($run['rows'])->toHaveCount(7)
        ->and($work->getAttributes())->toBe($original);
    Http::assertNothingSent();
});

test('a changed local test assignment is rejected before preparing simulated submissions', function (): void {
    [$service, , $work, $actor] = simulationFixture(localCopy: true);
    Storage::disk('local')->put('teaching/work_tasks/1/86/local-copy.pdf', '%PDF-changed');
    expect(fn () => $service->create($work, $actor, 'mixed', null))->toThrow(HttpException::class);
    Http::assertNothingSent();
});

test('simulation uses the actual selected roster and creates all seven cases without changing the work', function (): void {
    [$service, , $work, $actor] = simulationFixture();
    $original = $work->getAttributes();
    $run = $service->create($work, $actor, 'mixed', null);
    expect($run['simulation'])->toBeTrue()->and($run['rows'])->toHaveCount(7)
        ->and(array_column($run['rows'], 'scenario'))->toBe(['good', 'partial', 'weak', 'missing_attachment', 'no_submission', 'late', 'wrong_assignment'])
        ->and($run['rows'][3]['versions'][0]['attachments'])->toBeEmpty()
        ->and($run['rows'][3]['versions'][0]['artifacts'])->toHaveCount(1)
        ->and($run['rows'][4]['versions'][0]['artifacts'])->toBeEmpty()
        ->and($work->getAttributes())->toBe($original);
    TeachingSimulationAnswerAgent::assertPrompted(fn ($prompt) => ! str_contains($prompt->prompt, 'person1@example.test')
        && ! str_contains($prompt->prompt, 'Test1'));
    expect(fn () => $service->read(8, $run['id']))->toThrow(HttpException::class);
    expect(fn () => $service->artifact(7, $run['id'], '../.env'))->toThrow(HttpException::class);
    expect($service->collectionStatus($work, 7, range(1, 7)))->toMatchArray(['processed_count' => 0, 'complete' => false, 'completed_at' => null]);
    expect(fn () => $service->gradingSource($work, 7, range(1, 7)))->toThrow(HttpException::class);
    expect($service->collectionStatus($work, 8, range(1, 7)))->toBeNull()
        ->and($service->collectionStatus($work, 7, range(1, 6)))->toBeNull();
    $foreign = clone $work;
    $foreign->id = 87;
    expect($service->collectionStatus($foreign, 7, range(1, 7)))->toBeNull();
    $foreign->id = 86;
    $foreign->teaching_course_id = 17;
    expect($service->collectionStatus($foreign, 7, range(1, 7)))->toBeNull();
    $this->app['env'] = 'production';
    expect($service->collectionStatus($work, 7, range(1, 7)))->toBeNull();
});

test('Mailpit return sending is repeatable without duplicate messages and revisions preserve all originals', function (): void {
    [$service, $mailpit, $work, $actor] = simulationFixture();
    $run = $service->create($work, $actor, 'mixed', null);
    $mailpit->shouldReceive('find')->times(6)->andReturnNull();
    $mailpit->shouldReceive('send')->times(6)->andReturnUsing(function (array $payload): string {
        expect($payload['To'])->toBe([['Email' => 'teacher@example.test']])
            ->and($payload['Subject'])->toStartWith('[Simulation]')
            ->and($payload['Headers'])->toHaveKey('X-Schooltool-Simulation');

        return 'Mailpit'.bin2hex(random_bytes(8));
    });
    $first = $service->send(7, $run['id']);
    $second = $service->send(7, $run['id']);
    expect(array_column($first['rows'], 'versions'))->toBe(array_column($second['rows'], 'versions'));
    $revised = $service->revise(7, $run['id'], 'user:2');
    expect($revised['rows'][1]['versions'])->toHaveCount(2)
        ->and($revised['rows'][1]['versions'][0])->toBe($first['rows'][1]['versions'][0])
        ->and($revised['rows'][1]['versions'][1]['mailpit_id'])->toBeNull()
        ->and($revised['rows'][0]['versions'])->toBe($first['rows'][0]['versions']);
});

test('the local simulator is rejected outside the local environment before any source or mailbox access', function (): void {
    [$service, , $work, $actor] = simulationFixture();
    $this->app['env'] = 'production';
    expect(fn () => $service->create($work, $actor, 'good', null))->toThrow(HttpException::class);
    Http::assertNothingSent();
});

test('collection saves actual MIME and matching Mailpit bytes once and retains the first collection time', function (): void {
    [$service, $mailpit, $work, $actor] = simulationFixture();
    $run = $service->create($work, $actor, 'mixed', null);
    $mailpit->shouldReceive('find')->times(6)->andReturnNull();
    $payloads = [];
    $mailpit->shouldReceive('send')->times(6)->andReturnUsing(function (array $payload) use (&$payloads): string {
        $id = 'Mailpit'.count($payloads).'test';
        $payloads[$id] = $payload;

        return $id;
    });
    $service->send(7, $run['id']);
    $mailpit->shouldReceive('message')->times(6)->andReturnUsing(fn (string $id): array => [
        'From' => ['Address' => $payloads[$id]['From']['Email']],
        'Attachments' => array_map(fn (array $file): array => ['FileName' => $file['Filename'], 'PartID' => '1'], $payloads[$id]['Attachments']),
    ]);
    $raw = 'X-Schooltool-Simulation: '.$run['id']."\r\n\r\nTestnachricht";
    $mailpit->shouldReceive('raw')->times(6)->andReturn($raw);
    $mailpit->shouldReceive('part')->times(5)->andReturnUsing(fn (string $id): string => base64_decode($payloads[$id]['Attachments'][0]['Content'], true));
    $first = $service->collect(7, $run['id']);
    $status = $service->collectionStatus($work, 7, range(1, 7));
    expect($status)->toMatchArray(['origin' => 'mailpit_simulation', 'simulation' => true, 'work_id' => 86, 'course_id' => 16,
        'collected_count' => 6, 'non_submission_count' => 1, 'processed_count' => 7, 'complete' => true]);
    $this->travel(5)->minutes();
    $second = $service->collect(7, $run['id']);
    expect(array_column($first['rows'], 'versions'))->toBe(array_column($second['rows'], 'versions'));
    $rechecked = $service->collectionStatus($work, 7, range(1, 7));
    expect(array_diff_key($rechecked, ['last_check_at' => true]))->toBe(array_diff_key($status, ['last_check_at' => true]));
    expect(strtotime($rechecked['last_check_at']))->toBeGreaterThan(strtotime($status['last_check_at']));
    $gradingSource = $service->gradingSource($work, 7, range(1, 7));
    expect($gradingSource)->toMatchArray(['simulation' => true, 'run_id' => $run['id'], 'work_id' => 86, 'course_id' => 16])
        ->and($gradingSource['submissions'])->toHaveCount(7)
        ->and($gradingSource['submissions'][3]['files'])->toBeEmpty()
        ->and($gradingSource['submissions'][4]['message_file'])->toBeNull()
        ->and(json_encode($gradingSource))->not->toContain('quality', 'scenario', 'variants');
    $gradingDirectory = 'teaching-simulator/runs/7/'.$run['id'].'/Auswertungen';
    $disk = Storage::disk('local');
    $disk->makeDirectory($gradingDirectory);
    $gradeData = ['exercise_id' => 'simulation:'.$run['id'].':work:86', 'maximum' => '5', 'records' => [], 'source_hashes' => []];
    foreach ($first['rows'] as $index => $row) {
        $selected = $row['versions'][0];
        $selectedFiles = $selected['collected_at'] ? [$selected['saved_message'], ...array_column($selected['saved_attachments'], 'path')] : [];
        $absolute = array_map(fn (string $path): string => $disk->path($path), $selectedFiles);
        foreach ($selectedFiles as $path) {
            $gradeData['source_hashes'][$disk->path($path)] = hash('sha256', $disk->get($path));
        }
        $gradeData['records'][] = ['id' => $row['participant_id'], 'evaluated_submission_files' => $absolute,
            'evaluation_state' => $index === 0 ? 'complete' : ($index === 1 ? 'partial' : 'open'),
            'criteria' => [['criterion' => 'PDF-Inhalt', 'maximum' => '2', 'earned' => 'offen'],
                ['criterion' => 'E-Mail-Gestaltung', 'maximum' => '2,5', 'earned' => '2'],
                ['criterion' => 'Kopie', 'maximum' => '0,5', 'earned' => '0,5']],
            'total' => $index === 0 ? '4,5' : null, 'filename' => 'grade-'.$index.'.md', 'evaluation_completed_at' => now()->toISOString()];
    }
    $disk->put($gradingDirectory.'/grade-0.pdf', $disk->get($first['rows'][0]['versions'][0]['saved_attachments'][0]['path']));
    $releaseBytes = json_encode(['all_pages_rendered' => true, 'technical_flags' => [], 'pdfs' => [
        ['path' => $disk->path($gradingDirectory.'/grade-0.pdf'), 'sha256' => hash('sha256', $disk->get($gradingDirectory.'/grade-0.pdf'))],
    ]]);
    $gradeBytes = json_encode($gradeData);
    $disk->put($gradingDirectory.'/Bewertungsdaten_Simulation.json', $gradeBytes);
    $disk->put($gradingDirectory.'/PDF-Freigabe_Simulation.json', $releaseBytes);
    $disk->put($gradingDirectory.'/Laufabschluss_Simulation.json', json_encode(['kind' => 'schooltool.simulation-grading-completion',
        'simulation' => true, 'run_id' => $run['id'], 'work_id' => 86, 'course_id' => 16,
        'completed_at' => now()->toISOString(), 'data_sha256' => hash('sha256', $gradeBytes), 'pdf_release_sha256' => hash('sha256', $releaseBytes)]));
    expect($service->collectionStatus($work, 7, range(1, 7))['evaluation'])->toMatchArray([
        'completed_count' => 1, 'partial_count' => 1, 'open_count' => 5, 'run_complete' => true]);
    expect($service->collectionStatus($work, 7, range(1, 7))['evaluation']['rows'][1])->toMatchArray([
        'state' => 'partial', 'result' => '2,5 von 5 Punkten (vorläufig) · Offen: PDF-Inhalt']);
    $disk->put($gradingDirectory.'/Bewertungsdaten_Simulation.json', '{}');
    expect($service->collectionStatus($work, 7, range(1, 7))['evaluation'])->toBeNull();
    $disk->put($gradingDirectory.'/Bewertungsdaten_Simulation.json', $gradeBytes);
    $this->travel(1)->seconds();
    $service->collect(7, $run['id']);
    expect($service->collectionStatus($work, 7, range(1, 7))['evaluation'])->toMatchArray([
        'run_complete' => true, 'completed_count' => 1, 'partial_count' => 1, 'open_count' => 5]);
    $version = $first['rows'][0]['versions'][0];
    expect(Storage::disk('local')->get($version['saved_message']))->toBe($raw)
        ->and(hash('sha256', Storage::disk('local')->get($version['saved_attachments'][0]['path'])))
        ->toBe($version['attachments'][0]['sha256']);
    $archive = $service->archive(7, $run['id']);
    $zip = new ZipArchive;
    expect($zip->open($archive))->toBeTrue()->and($zip->locateName('run.json'))->not->toBeFalse();
    $zip->close();
    Storage::disk('local')->put($version['saved_attachments'][0]['path'], 'changed bytes');
    expect($service->collectionStatus($work, 7, range(1, 7)))->toMatchArray(['complete' => false, 'collected_count' => 5]);
    expect($service->collectionStatus($work, 7, range(1, 7))['evaluation']['completed_count'])->toBe(0);
    Storage::disk('local')->put($version['saved_attachments'][0]['path'], base64_decode(array_values($payloads)[0]['Attachments'][0]['Content'], true));
    $service->revise(7, $run['id'], 'user:1');
    expect($service->collectionStatus($work, 7, range(1, 7)))->toMatchArray(['complete' => false, 'processed_count' => 6, 'completed_at' => null]);
    expect($service->collectionStatus($work, 7, range(1, 7))['evaluation'])->toMatchArray(['completed_count' => 0, 'partial_count' => 1, 'open_count' => 6]);
    expect($service->collectionStatus($work, 7, range(1, 7))['evaluation']['run_complete'])->toBeFalse();
});

test('collection refuses a changed Mailpit attachment without recording a successful collection', function (): void {
    [$service, $mailpit, $work, $actor] = simulationFixture();
    $run = $service->create($work, $actor, 'good', null);
    $mailpit->shouldReceive('find')->times(7)->andReturnNull();
    $mailpit->shouldReceive('send')->times(7)->andReturn('Mailpittest');
    $sent = $service->send(7, $run['id']);
    $mailpit->shouldReceive('message')->once()->andReturn(['From' => ['Address' => 'person1@example.test'],
        'Attachments' => [['FileName' => 'Ausarbeitung_v1.pdf', 'PartID' => '1']]]);
    $mailpit->shouldReceive('raw')->once()->andReturn('X-Schooltool-Simulation: '.$run['id']);
    $mailpit->shouldReceive('part')->once()->andReturn('changed bytes');
    expect(fn () => $service->collect(7, $run['id']))->toThrow(HttpException::class);
    expect($service->read(7, $run['id'])['rows'])->toBe($sent['rows']);
});

test('unsafe generated file paths are rejected', function (string $path): void {
    expect(fn () => app(TeachingSimulationAnswers::class)->validateFile(['path' => $path, 'content' => 'test']))
        ->toThrow(HttpException::class);
})->with(['../secret.txt', '/absolute.txt', 'C:/secret.txt', 'program.php', 'run.exe', 'folder/../../secret.md']);

test('generated PDFs retain participant names with Unicode characters', function (): void {
    $files = app(TeachingSimulationAnswers::class)->files('unicode-test', ['first_name' => 'Andrija',
        'last_name' => 'Bošković Radulović', 'class_name' => '3B'], 'E-Mails',
        ['answer' => 'Zwei Warnzeichen für verdächtige E-Mails.', 'files' => []], 1);
    $text = (new Parser)->parseContent(Storage::disk('local')->get($files[0]['path']))->getText();
    expect($text)->toContain('Bošković Radulović')->toContain('verdächtige');
});
