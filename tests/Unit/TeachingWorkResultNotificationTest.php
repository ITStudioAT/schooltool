<?php

use App\Models\DropboxConnection;
use App\Models\TeachingCourseWork;
use App\Models\User;
use App\Services\DropboxWorkImport;
use App\Services\TeachingCourseWorkEntrySyncService;
use App\Services\TeachingSimulation;
use App\Services\TeachingSimulationMailpit;
use App\Services\TeachingWorkAiExport;
use App\Services\TeachingWorkResultNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->app['env'] = 'local';
    Storage::fake('local');
    Http::preventStrayRequests();
});

function resultNotificationFixture(?array $simulation = null, ?array $collection = null, ?array $teacher = null): array
{
    $actor = (new User)->forceFill(['id' => 7]);
    $pdf = '%PDF-personal-evaluation';
    $hash = hash('sha256', $pdf);
    Storage::disk('local')->put('evaluation/1.pdf', $pdf);
    $work = (new TeachingCourseWork)->forceFill(['id' => 86, 'teaching_course_id' => 16, 'title' => 'E-Mails',
        'groups' => [['student_ids' => [1, 2, 3]]], 'status' => [
            'assessment_json_imports' => array_map(fn (int $id): array => ['student_id' => $id, 'exercise_id' => 'exercise-86',
                'participant_id' => 'user:'.$id, 'record_checksum' => str_repeat((string) $id, 64),
                'record' => ['evaluation_state' => $id === 2 ? 'partial' : 'complete', 'evaluation_completed_at' => '2026-10-24T16:52:07.79244Z',
                    'pdf' => ['sha256' => $hash]]], [1, 2, 3]),
            'evaluation_pdfs' => [['student_id' => 1, 'origin' => 'evaluation_import', 'sha256' => $hash, 'file_path' => 'evaluation/1.pdf', 'storage_disk' => 'local']],
        ]]);
    $mailpit = Mockery::mock(TeachingSimulationMailpit::class);
    $service = Mockery::mock(TeachingWorkResultNotification::class, [Mockery::mock(TeachingWorkAiExport::class),
        Mockery::mock(TeachingCourseWorkEntrySyncService::class), Mockery::mock(TeachingSimulation::class), $mailpit])->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('currentWork')->with($work)->andReturn($work);
    $service->shouldReceive('snapshot')->andReturn(['people' => array_map(fn (int $id): array => ['schooltool_person_id' => (string) $id,
        'first_name' => ['Anna', 'Emil', 'Berta'][$id - 1], 'last_name' => ['Zeller', 'Alpha', 'Berger'][$id - 1], 'email' => 'student'.$id.'@example.test', 'sex' => ''], [1, 2, 3]),
        'entry_ids' => [1, 2, 3], 'simulation' => $simulation, 'collection' => $collection, 'teacher' => $teacher ?? ['first_name' => 'Günther', 'last_name' => 'Kron', 'email' => 'teacher@example.test']]);

    return [$service, $mailpit, $work, $actor];
}

function fakeResultNotificationMailpit(object $mailpit, array &$payloads, bool $failFirstRead = false, ?Closure $afterMessage = null, bool $tamperHtml = false): void
{
    $mailpit->shouldReceive('assertSafe')->andReturnNull();
    $mailpit->shouldReceive('findMessage')->andReturnUsing(function (string $messageId) use (&$payloads): ?string {
        foreach ($payloads as $id => $payload) {
            if ($payload['Headers']['Message-ID'] === '<'.$messageId.'>') {
                return $id;
            }
        }

        return null;
    });
    $mailpit->shouldReceive('send')->andReturnUsing(function (array $payload) use (&$payloads): string {
        $id = 'ResultMailpit'.count($payloads);
        $payloads[$id] = $payload;

        return $id;
    });
    $mailpit->shouldReceive('message')->andReturnUsing(function (string $id) use (&$payloads, &$failFirstRead, $tamperHtml): array {
        if ($failFirstRead) {
            $failFirstRead = false;
            throw new HttpException(502, 'Mailpit vorübergehend nicht erreichbar');
        }
        $payload = $payloads[$id];

        return ['From' => ['Address' => $payload['From']['Email']], 'To' => [['Address' => $payload['To'][0]['Email']]],
            'Subject' => $payload['Subject'], 'Text' => $payload['Text'], 'HTML' => str_replace("\n", "\r\n", $payload['HTML']).($tamperHtml ? '<p>Abweichender Inhalt</p>' : '')];
    });
    $mailpit->shouldReceive('raw')->andReturnUsing(function (string $id) use (&$payloads, &$afterMessage): string {
        if ($afterMessage) {
            $afterMessage();
            $afterMessage = null;
        }

        return 'Message-ID: '.$payloads[$id]['Headers']['Message-ID']."\r\n\r\n".$payloads[$id]['Text'];
    });
}

test('result preview excludes unfinished and unavailable results and shows complete personal messages without sending', function (): void {
    [$service, $mailpit, $work, $actor] = resultNotificationFixture();
    $before = $work->getAttributes();
    $mailpit->shouldNotReceive('send');
    $preview = $service->preview($work, $actor);
    expect($preview['student_ids'])->toBe([1])->and($preview['messages'])->toHaveCount(2)
        ->and(array_column($preview['rows'], 'last_name'))->toBe(['Alpha', 'Berger', 'Zeller'])
        ->and($preview['messages'][0]['text'])->toContain('Guten Tag Anna Zeller,')->toContain(TeachingWorkResultNotification::AI_NOTE)
        ->and($preview['messages'][0]['html'])->toContain('align="center"')->toContain('font-style:italic')
        ->toContain('data-school-email-layout="v1"')->toContain('#eef4f1')->toContain('<strong>Guten Tag Anna Zeller,</strong>')
        ->and($preview['messages'][0]['subject'])->toStartWith('[Simulation] ')
        ->and($preview['messages'][1]['student_id'])->toBeNull()->and($work->getAttributes())->toBe($before);
    Http::assertNothingSent();
});

test('teacher results respect the chosen address form and include the complete safe formatted signature', function (string $form, string $greeting, string $possessive): void {
    [$service, $mailpit, $work, $actor] = resultNotificationFixture(null, null,
        ['first_name' => 'Günther', 'last_name' => 'Kron', 'email' => 'guenther.kron@cdgym.at']);
    $mailpit->shouldNotReceive('send');

    $preview = $service->preview($work, $actor, ['teacher_preview' => true, 'address_form' => $form]);

    expect($preview['messages'])->toHaveCount(1);
    expect($preview['messages'][0]['text'])->toStartWith($greeting)->toContain($possessive.' Testauswertung')
        ->toContain('Prof. Dipl.-Ing. Günther Kron')->toContain('T: 0662 431208 | W: cdgym.at')
        ->toContain('Abendgymnasium Salzburg')->toContain('T: 0662 434575 | W: abendgymnasium.salzburg.at')
        ->not->toContain('**');
    expect($preview['messages'][0]['html'])->toContain('<strong>Prof. Dipl.-Ing. Günther Kron</strong>')
        ->toContain('<em>Informatik, Wirtschaftsinformatik, Medien, Digitale Grundbildung</em>')
        ->toContain('height="28"');
})->with([['sie', 'Sehr geehrter Herr Kron,', 'Ihre'], ['du', 'Lieber Günther,', 'Deine']]);

test('result signatures escape user text instead of accepting executable markup', function (): void {
    [$service, $mailpit, $work, $actor] = resultNotificationFixture();
    $preview = $service->preview($work, $actor);

    $message = $service->preview($work, $actor, ['body' => $preview['body']."\n\n**<script>alert(1)</script>**"]);

    expect($message['messages'][0]['html'])->not->toContain('<script>')->toContain('&lt;script&gt;');
});

test('Mailpit result notifications are confirmed individually and archived separately from live evidence', function (): void {
    [$service, $mailpit, $work, $actor] = resultNotificationFixture();
    $payloads = [];
    fakeResultNotificationMailpit($mailpit, $payloads);
    $preview = $service->preview($work, $actor);
    $input = array_intersect_key($preview, array_flip(['subject', 'body', 'student_ids', 'hash']));
    $status = $service->sendSimulation($work, $actor, $input);
    expect($payloads)->toHaveCount(2)->and($status['rows'])->toHaveCount(1)->and($status['teacher_count'])->toBe(1)
        ->and($status['origin'])->toBe('mailpit_result_notification')->and($work->status['dispatch_notifications'] ?? [])->toBe([]);
    foreach ($payloads as $payload) {
        expect($payload['To'])->toHaveCount(1)->and($payload['Cc'])->toBe([])->and($payload['Bcc'])->toBe([])->and($payload['Attachments'])->toBe([]);
    }
    expect(fn () => $service->sendSimulation($work, $actor, $input))->toThrow(HttpException::class);
    expect($payloads)->toHaveCount(2)->and($service->preview($work, $actor)['student_ids'])->toBe([]);
    $attributes = $work->status;
    $attributes['assessment_json_imports'][0]['record_checksum'] = str_repeat('f', 64);
    $work->status = $attributes;
    expect($service->status($work, 7)['rows'])->toBe([]);
});

test('an uncertain Mailpit response resumes by message ID without sending a second copy', function (): void {
    [$service, $mailpit, $work, $actor] = resultNotificationFixture();
    $payloads = [];
    fakeResultNotificationMailpit($mailpit, $payloads, true);
    $preview = $service->preview($work, $actor);
    $input = array_intersect_key($preview, array_flip(['subject', 'body', 'student_ids', 'hash']));
    expect(fn () => $service->sendSimulation($work, $actor, $input))->toThrow(HttpException::class);
    expect($payloads)->toHaveCount(1);
    $status = $service->sendSimulation($work, $actor, $input);
    expect($status['rows'])->toHaveCount(1)->and($status['teacher_count'])->toBe(1)->and($payloads)->toHaveCount(2);
});

test('changed results and unselected unfinished students block sending before any transport call', function (): void {
    [$service, $mailpit, $work, $actor] = resultNotificationFixture();
    $mailpit->shouldNotReceive('send');
    $preview = $service->preview($work, $actor);
    expect(fn () => $service->preview($work, $actor, ['student_ids' => [2]]))->toThrow(HttpException::class);
    $input = array_intersect_key($preview, array_flip(['subject', 'body', 'student_ids', 'hash']));
    $status = $work->status;
    $status['assessment_json_imports'][0]['record_checksum'] = str_repeat('f', 64);
    $work->status = $status;
    expect(fn () => $service->sendSimulation($work, $actor, $input))->toThrow(HttpException::class);
    Http::assertNothingSent();
});

test('HTML content differences still block confirmation while transport line endings are tolerated', function (): void {
    [$service, $mailpit, $work, $actor] = resultNotificationFixture();
    $payloads = [];
    fakeResultNotificationMailpit($mailpit, $payloads, false, null, true);
    $preview = $service->preview($work, $actor);
    $input = array_intersect_key($preview, array_flip(['subject', 'body', 'student_ids', 'hash']));
    expect(fn () => $service->sendSimulation($work, $actor, $input))->toThrow(HttpException::class);
    expect($service->status($work, 7)['rows'])->toBe([])->and($payloads)->toHaveCount(1);
    expect(fn () => $service->sendSimulation($work, $actor, $input))->toThrow(HttpException::class);
    expect($payloads)->toHaveCount(1);
});

test('a changed assessment during a batch stops subsequent messages while retaining prior confirmations', function (): void {
    [$service, $mailpit, $work, $actor] = resultNotificationFixture();
    $payloads = [];
    fakeResultNotificationMailpit($mailpit, $payloads, false, function () use ($work): void {
        $status = $work->status;
        $status['assessment_json_imports'][0]['record_checksum'] = str_repeat('f', 64);
        $work->status = $status;
    });
    $preview = $service->preview($work, $actor);
    $input = array_intersect_key($preview, array_flip(['subject', 'body', 'student_ids', 'hash']));
    expect(fn () => $service->sendSimulation($work, $actor, $input))->toThrow(HttpException::class);
    expect($payloads)->toHaveCount(1);
});

test('live result preparation uses the existing Office workflow and never sends through a fallback', function (): void {
    [$service, $mailpit, $work, $actor] = resultNotificationFixture();
    $this->app['env'] = 'production';
    $mailpit->shouldNotReceive('send');
    $dropbox = Mockery::mock(DropboxWorkImport::class);
    $connection = new DropboxConnection;
    $dropbox->shouldReceive('connection')->with($actor)->andReturn($connection);
    $dropbox->shouldReceive('resultsFolder')->with($connection, $work, null)->andReturn(['id' => 'id:existing-work']);
    $this->app->instance(DropboxWorkImport::class, $dropbox);
    $preview = $service->preview($work, $actor);
    $input = array_intersect_key($preview, array_flip(['subject', 'body', 'student_ids', 'hash']));
    $order = $service->officeOrder($work, $actor, $input);
    expect($preview['transport'])->toBe('office_outlook')->and($order)->toContain('announce-exercise-results')
        ->toContain('id:existing-work')->toContain('"send": false')->toContain('"preserve_prepared_html": true')
        ->toContain('school-standard-v1')->toContain('data-school-email-layout');
    expect(fn () => $service->sendSimulation($work, $actor, $input))->toThrow(HttpException::class);
    Http::assertNothingSent();
});

test('teacher-only styling previews preserve the original completion and never repeat student notifications', function (): void {
    [$service, $mailpit, $work, $actor] = resultNotificationFixture();
    $payloads = [];
    fakeResultNotificationMailpit($mailpit, $payloads);
    $preview = $service->preview($work, $actor);
    $status = $service->sendSimulation($work, $actor, array_intersect_key($preview, array_flip(['subject', 'body', 'student_ids', 'hash'])));
    $completedAt = $status['completed_at'];
    expect($completedAt)->not->toBeNull();
    $this->travel(10)->minutes();
    expect($service->status($work, 7))->toBe($status);
    $test = $service->preview($work, $actor, ['teacher_preview' => true]);
    expect($test['student_ids'])->toBe([])->and($test['messages'])->toHaveCount(1)
        ->and($test['messages'][0]['email'])->toBe('teacher@example.test')->and($test['last_completed_at'])->toBe($completedAt);
    $input = array_intersect_key($test, array_flip(['subject', 'body', 'student_ids', 'hash', 'teacher_preview', 'preview_id']));
    expect($service->sendSimulation($work, $actor, $input))->toBe($status)->and($payloads)->toHaveCount(3);
    expect($service->sendSimulation($work, $actor, $input))->toBe($status)->and($payloads)->toHaveCount(3);
    expect($service->preview($work, $actor, ['teacher_preview' => true])['preview_id'])->not->toBe($test['preview_id']);
});

test('new live submission evidence blocks later notification while an unchanged check preserves availability', function (bool $changed): void {
    [$service, $mailpit, $work, $actor] = resultNotificationFixture(collection: [
        'completed_at' => '2026-10-24T20:00:00Z', 'changes_detected' => $changed,
    ]);
    $this->app['env'] = 'production';
    $mailpit->shouldNotReceive('send');
    $preview = $service->preview($work, $actor);
    expect($preview['student_ids'])->toBe($changed ? [] : [1]);
    if ($changed) {
        expect($preview['workflow_blocked'])->toContain('neuen oder geänderten');
        expect(fn () => $service->preview($work, $actor, ['student_ids' => [1]]))->toThrow(HttpException::class);
    }
    Http::assertNothingSent();
})->with([false, true]);

test('notification cannot bypass an unfinished simulator grading pass', function (): void {
    [$service, $mailpit, $work, $actor] = resultNotificationFixture(['complete' => true, 'run_id' => 'run',
        'evaluation' => ['run_complete' => false, 'rows' => []]]);
    $mailpit->shouldNotReceive('send');
    expect($service->preview($work, $actor)['student_ids'])->toBe([]);
    expect(fn () => $service->preview($work, $actor, ['student_ids' => [1]]))->toThrow(HttpException::class);
});
