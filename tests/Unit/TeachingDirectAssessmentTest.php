<?php

use App\Exceptions\DropboxExerciseRequestFailed;
use App\Exceptions\DropboxFolderCreationFailed;
use App\Models\DropboxConnection;
use App\Models\TeachingCourseWork;
use App\Models\TeachingWorkModuleLink;
use App\Models\User;
use App\Services\DropboxModuleLinks;
use App\Services\DropboxWorkImport;
use App\Services\TeachingAssessmentTargetGuard;
use App\Services\TeachingAssessmentTemplate;
use App\Services\TeachingDirectAssessment;
use App\Services\TeachingDirectAssessmentMc;
use App\Services\TeachingWorkAiExport;
use App\Support\TeachingAssessmentProgress;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class);

function directAssessmentFixture(): object
{
    config(['cache.default' => 'array']);
    Cache::flush();
    $state = (object) ['files' => [], 'writes' => [], 'reads' => [], 'fail' => null, 'onWrite' => null, 'temporary' => false, 'context' => [
        'exported_at' => 'now', 'work' => ['scheduled_date' => '2026-11-18', 'title' => 'Word',
            'main_deadline' => ['date' => '2026-11-19', 'time' => '20:00'], 'maximum_points' => 5],
        'course' => ['folder_group' => '3B_Gruppe-2'], 'course_roster' => ['complete' => true, 'participants' => [
            ['first_name' => 'Anna', 'last_name' => 'Beispiel'], ['first_name' => 'Ben', 'last_name' => 'Test'],
        ]],
    ]];
    $state->folders = ['id:module' => ['.tag' => 'folder', 'id' => 'id:module', 'name' => 'Modul',
        'path_lower' => '/modul', 'path_display' => '/Modul']];
    $connection = new DropboxConnection(['account_id' => 'test']);
    $connection->id = 7;
    $state->work = Mockery::mock(TeachingCourseWork::class)->makePartial();
    $state->work->id = 10;
    $state->work->teaching_course_id = 20;
    $state->work->shouldReceive('fresh')->andReturn($state->work);
    $state->user = new User;
    $state->user->id = 30;
    $link = new TeachingWorkModuleLink(['dropbox_connection_id' => 7, 'account_id' => 'test', 'folder_id' => 'id:module', 'folder_path' => '/modul']);
    $state->link = $link;
    $dropbox = Mockery::mock(DropboxWorkImport::class);
    $dropbox->shouldReceive('connection')->andReturn($connection);
    $dropbox->shouldReceive('canWrite')->andReturn(true);
    $dropbox->shouldReceive('isTemporaryFolderPath')->andReturnUsing(fn () => $state->temporary);
    $dropbox->shouldReceive('metadata')->andReturnUsing(fn ($connection, string $id) => $state->folders[$id]);
    $dropbox->shouldReceive('readExerciseInventory')->andReturnUsing(function ($connection, string $folder, bool $includeSharing = false) use ($state) {
        $state->listRequests[] = $folder;
        $parent = $state->folders[$folder]['path_lower'];
        $entries = array_values(array_filter($state->folders, fn (array $folder): bool => dirname($folder['path_lower']) === $parent));
        foreach ($state->files as $key => $file) {
            $prefix = $folder.'/';
            if (str_starts_with($key, $prefix) && ! str_contains(substr($key, strlen($prefix)), '/')) {
                $entries[] = ['.tag' => 'file', 'id' => 'id:file-'.hash('sha256', $key), 'name' => substr($key, strlen($prefix))];
            }
        }

        return new Illuminate\Http\Client\Response(new Response(200, [], json_encode(['entries' => $entries, 'has_more' => false])));
    });
    $dropbox->shouldReceive('createExerciseChildFolder')->andReturnUsing(function ($connection, string $parent, string $name, string $expected) use ($state) {
        expect($state->folders[$parent]['path_lower'])->toBe($expected);
        $path = $expected.'/'.mb_strtolower($name);
        expect(collect($state->folders)->contains('path_lower', $path))->toBeFalse();
        $id = 'id:'.count($state->folders);
        $state->folders[$id] = ['.tag' => 'folder', 'id' => $id, 'name' => $name, 'path_lower' => $path,
            'path_display' => $state->folders[$parent]['path_display'].'/'.$name];
        if (isset($state->onCreate)) {
            ($state->onCreate)($state, $name);
        }

        return $state->folders[$id];
    });
    $dropbox->shouldReceive('writeExerciseDocument')->andReturnUsing(function ($connection, string $folder, string $name, string $bytes, ?string $revision) use ($state) {
        if ($state->fail === $name && (! isset($state->failFolder) || $state->folders[$folder]['name'] === $state->failFolder)) {
            throw new RuntimeException('Simulated failure');
        }
        $key = $folder.'/'.$name;
        expect($state->files[$key]['rev'] ?? null)->toBe($revision);
        $result = 'rev'.count($state->writes);
        $state->files[$key] = ['contents' => $bytes, 'rev' => $result];
        $state->writes[] = ['folder' => $folder, 'name' => $name, 'bytes' => $bytes];
        if ($state->onWrite) {
            ($state->onWrite)($state, $name);
        }

        return $result;
    });
    $dropbox->shouldReceive('readExerciseDocument')->andReturnUsing(function ($connection, string $folder, string $name) use ($state) {
        $state->reads[] = $folder.'/'.$name;

        return $state->files[$folder.'/'.$name] ?? null;
    });
    $modules = Mockery::mock(DropboxModuleLinks::class);
    $modules->shouldReceive('current')->andReturn($link);
    $modules->shouldReceive('exercise')->andReturnUsing(function () use ($state): array {
        foreach ($state->files as $key => $file) {
            if (str_ends_with($key, '/status.json') && (json_decode($file['contents'], true)['prepared'] ?? false)) {
                $folderId = substr($key, 0, -strlen('/status.json'));

                return ['status' => 'found', 'folder' => ['id' => $folderId], 'preparation' => ['status' => 'ready']];
            }
        }

        return ['status' => 'missing'];
    });
    $modules->shouldReceive('forgetExerciseCache');
    $export = Mockery::mock(TeachingWorkAiExport::class);
    $export->shouldReceive('buildExercise')->andReturnUsing(fn () => ['context' => $state->context]);
    $state->template = ['folder' => ['id' => 'id:template'], 'points' => 35.0, 'documents' => [
        'assignment' => ['name' => 'LF_Word.pdf', 'id' => 'a', 'rev' => '1', 'sha256' => hash('sha256', 'pdf'), 'bytes' => 'pdf'],
        'criteria' => ['name' => 'Beurteilungskriterien_Word.pdf', 'id' => 'b', 'rev' => '1', 'sha256' => hash('sha256', 'criteria pdf'), 'bytes' => 'criteria pdf'],
        'criteria_markdown' => ['name' => 'Beurteilungskriterien_Word.md', 'id' => 'c', 'rev' => '1', 'sha256' => hash('sha256', 'criteria'), 'bytes' => 'criteria'],
    ]];
    $templates = Mockery::mock(TeachingAssessmentTemplate::class);
    $templates->shouldReceive('inspect')->andReturnUsing(fn () => $state->template);
    $state->mc = null;
    $mc = Mockery::mock(TeachingDirectAssessmentMc::class);
    $mc->shouldReceive('snapshot')->andReturnUsing(function ($work, $date, $expected) use ($state) {
        abort_unless($expected === ($state->mc['id'] ?? null), 409, 'MC-Zuordnung geändert.');

        return $state->mc;
    });
    $targets = Mockery::mock(TeachingAssessmentTargetGuard::class);
    $targets->shouldReceive('assertUnique');
    $targets->shouldReceive('dates')->andReturnUsing(fn ($work) => array_values(array_unique(array_filter([
        $work->date_for_all_groups?->format('Y-m-d'), ...array_column($work->groups ?? [], 'date')]))));
    $state->service = new TeachingDirectAssessment($dropbox, $modules, $export, $templates, $mc, $targets);

    return $state;
}

function addEmptyDirectFolder(object $state): void
{
    $state->folders['id:root'] = ['.tag' => 'folder', 'id' => 'id:root', 'name' => 'Leistungsfeststellungen',
        'path_lower' => '/modul/leistungsfeststellungen', 'path_display' => '/Modul/Leistungsfeststellungen'];
    $name = '2026-11-18 – 3B_Gruppe-2 – Word';
    $state->folders['id:existing'] = ['.tag' => 'folder', 'id' => 'id:existing', 'name' => $name,
        'path_lower' => '/modul/leistungsfeststellungen/'.mb_strtolower($name), 'path_display' => '/Modul/Leistungsfeststellungen/'.$name];
}

test('previews and confirms reuse of the same empty canonical folder without overwriting', function (): void {
    $state = directAssessmentFixture();
    addEmptyDirectFolder($state);
    $preview = $state->service->preview($state->work, $state->user, '2026-11-18', 35, null, true);
    expect($preview['reusable_folder'])->toBe('empty');
    expect($state->writes)->toBeEmpty();
    $result = $state->service->store($state->work, $state->user, $preview['id']);
    expect($result['folder']['id'])->toBe('id:existing');
    $statuses = collect($state->writes)->where('name', 'status.json')->values();
    expect(json_decode($statuses[0]['bytes'], true)['prepared'])->toBeFalse();
    expect(json_decode($statuses->last()['bytes'], true)['prepared'])->toBeTrue();
    $count = count($state->writes);
    expect($state->service->store($state->work, $state->user, $preview['id']))->toBe($result);
    expect($state->writes)->toHaveCount($count);
});

test('rejects a changed or unsafe existing folder before any write', function (string $case): void {
    $state = directAssessmentFixture();
    addEmptyDirectFolder($state);
    if ($case === 'shared') {
        $state->folders['id:existing']['sharing_info'] = ['shared_folder_id' => 'other'];
    }
    if ($case === 'occupied') {
        $state->files['id:existing/other.txt'] = ['contents' => 'other', 'rev' => 'other'];
    }
    if ($case === 'renamed') {
        $state->folders['id:existing']['name'] = 'Anderer Ordner';
    }
    try {
        $state->service->preview($state->work, $state->user, '2026-11-18', 35, null, true);
        test()->fail('An unclear folder must not be reused.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(409);
    }
    expect($state->writes)->toBeEmpty();
})->with(['shared', 'occupied', 'renamed']);

test('rejects an empty folder changed between preview and confirmation', function (): void {
    $state = directAssessmentFixture();
    addEmptyDirectFolder($state);
    $preview = $state->service->preview($state->work, $state->user, '2026-11-18', 35, null, true);
    $state->files['id:existing/other.txt'] = ['contents' => 'external', 'rev' => 'external'];
    try {
        $state->service->store($state->work, $state->user, $preview['id']);
        test()->fail('The changed folder must not be reused.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(409);
    }
    expect($state->writes)->toBeEmpty();
});

test('rejects replacement of the reviewed folder by another Dropbox identity', function (): void {
    $state = directAssessmentFixture();
    addEmptyDirectFolder($state);
    $preview = $state->service->preview($state->work, $state->user, '2026-11-18', 35, null, true);
    $replacement = $state->folders['id:existing'];
    unset($state->folders['id:existing']);
    $replacement['id'] = 'id:replacement';
    $state->folders['id:replacement'] = $replacement;
    try {
        $state->service->store($state->work, $state->user, $preview['id']);
        test()->fail('A replacement folder must not be reused.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(409);
    }
    expect($state->writes)->toBeEmpty();
});

test('does not initialize a linked folder when the work has ambiguous dates or the module is shared', function (string $case): void {
    $state = directAssessmentFixture();
    $state->work->date_for_all_groups = Carbon::parse('2026-11-18');
    $state->work->groups = $case === 'dates' ? [['date' => '2026-11-20']] : [];
    if ($case === 'shared') {
        $state->folders['id:module']['sharing_info'] = ['shared_folder_id' => 'foreign'];
    }
    try {
        $state->service->initializeLinkedFolder($state->work, $state->user);
        test()->fail('The linked folder must not be initialized.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBeIn([409, 422]);
    }
    expect($state->writes)->toBeEmpty();
    expect($state->folders)->toHaveCount(1);
})->with(['dates', 'shared']);

test('retries a failed initial status write only while the folder is still empty', function (): void {
    $state = directAssessmentFixture();
    addEmptyDirectFolder($state);
    $preview = $state->service->preview($state->work, $state->user, '2026-11-18', 35, null, true);
    $state->fail = 'status.json';
    try {
        $state->service->store($state->work, $state->user, $preview['id']);
        test()->fail('The simulated write must fail.');
    } catch (ValidationException) {
        expect($state->writes)->toBeEmpty();
    }
    $state->fail = null;
    expect($state->service->store($state->work, $state->user, $preview['id'])['folder']['id'])->toBe('id:existing');
});

test('initializes a linked folder once and completes it only after a later preview', function (): void {
    $state = directAssessmentFixture();
    $state->work->date_for_all_groups = Carbon::parse('2026-11-18');
    $state->work->groups = [];
    $first = $state->service->initializeLinkedFolder($state->work, $state->user);
    $id = $first['folder']['id'];
    expect($first['status'])->toBe('initialized');
    expect(json_decode($state->files[$id.'/status.json']['contents'], true))->toMatchArray(['prepared' => false,
        'components' => ['aufgabenblatt' => ['included' => false, 'points' => null], 'multiple_choice' => ['included' => false, 'points' => null]], 'total_points' => null]);
    $writes = count($state->writes);
    expect($state->service->initializeLinkedFolder($state->work, $state->user)['folder']['id'])->toBe($id);
    expect($state->writes)->toHaveCount($writes);
    $preview = $state->service->preview($state->work, $state->user, '2026-11-18', 35, null, true);
    expect($preview['reusable_folder'])->toBe('linked');
    expect($state->writes)->toHaveCount($writes);
    expect($state->service->store($state->work, $state->user, $preview['id'])['folder']['id'])->toBe($id);
    expect(json_decode($state->files[$id.'/status.json']['contents'], true)['prepared'])->toBeTrue();
});

test('a failed linked-folder completion leaves the status incomplete and can be retried', function (): void {
    $state = directAssessmentFixture();
    $state->work->date_for_all_groups = Carbon::parse('2026-11-18');
    $state->work->groups = [];
    $folder = $state->service->initializeLinkedFolder($state->work, $state->user)['folder'];
    $preview = $state->service->preview($state->work, $state->user, '2026-11-18', 35, null, true);
    $state->fail = 'Beurteilung.pdf';
    try {
        $state->service->store($state->work, $state->user, $preview['id']);
        test()->fail('The simulated write must fail.');
    } catch (ValidationException) {
        expect(json_decode($state->files[$folder['id'].'/status.json']['contents'], true)['prepared'])->toBeFalse();
    }
    $state->fail = null;
    expect($state->service->store($state->work, $state->user, $preview['id'])['folder']['id'])->toBe($folder['id']);
    expect(json_decode($state->files[$folder['id'].'/status.json']['contents'], true)['prepared'])->toBeTrue();
});

test('previews and creates the complete confirmed package without MC independently of the work scale', function (): void {
    $state = directAssessmentFixture();
    $preview = $state->service->preview($state->work, $state->user, '2026-11-18', 35);
    expect($preview['points'])->toBe(35.0);
    expect($preview['target'])->toContain('2026-11-18 – 3B_Gruppe-2 – Word');
    expect($state->writes)->toBeEmpty();
    $result = $state->service->store($state->work, $state->user, $preview['id']);
    expect($result['participants_count'])->toBe(2);
    expect($state->files[$result['folder']['id'].'/LF_Word.pdf']['contents'])->toBe('pdf');
    expect(collect($state->writes)->where('name', 'LF_Word.pdf'))->toHaveCount(3);
    $statuses = array_values(array_filter($state->writes, fn (array $write): bool => $write['name'] === 'status.json'));
    expect(json_decode($statuses[0]['bytes'], true)['prepared'])->toBeFalse();
    $completed = json_decode(end($statuses)['bytes'], true);
    expect($completed['prepared'])->toBeTrue();
    expect($completed['total_points'])->toBe(35);
    expect($completed['components']['multiple_choice']['included'])->toBeFalse();
    $onlineStatus = $state->files[$result['folder']['id'].'/status.json'];
    expect(json_decode($onlineStatus['contents'], true))->toBe($completed);
    expect($completed['schooltool'])->toBe(['work_id' => 10, 'course_id' => 20]);
    expect($completed['scheduled_date'])->toBe('2026-11-18');
    expect($state->reads)->toContain($result['folder']['id'].'/status.json');
    expect(array_column($state->writes, 'bytes'))->toContain('pdf', 'criteria', 'criteria pdf');
    $count = count($state->writes);
    expect($state->service->store($state->work, $state->user, $preview['id']))->toBe($result);
    expect($state->writes)->toHaveCount($count);
});

test('summary includes only recorded names and sex without document content', function (): void {
    $state = directAssessmentFixture();
    $state->context['course_roster']['participants'][0] += ['sex' => 'w', 'email' => 'private@example.test', 'participant_id' => 123];
    $state->context['course_roster']['participants'][1]['sex'] = null;
    $preview = $state->service->preview($state->work, $state->user, '2026-11-18', 35, null, true);
    expect($preview['participants'])->toBe([
        ['name' => 'Beispiel Anna', 'sex' => 'w'], ['name' => 'Test Ben', 'sex' => null],
    ]);
    expect($preview['checks'])->toBe(['assignment' => 'ok', 'criteria' => 'ok']);
    expect($preview)->not->toHaveKeys(['documents', 'criteria_markdown']);
    expect($state->writes)->toBeEmpty();
    $legacy = $state->service->preview($state->work, $state->user, '2026-11-18', 35);
    expect($legacy)->toHaveKeys(['documents', 'criteria_markdown']);
});

test('keeps the separately linked MC test outside the PDF package and exposes its independent points', function (): void {
    $state = directAssessmentFixture();
    $state->mc = ['id' => 99, 'title' => 'MC', 'points' => 10.0, 'status' => 'draft', 'binding' => [], 'attributes' => []];
    $preview = $state->service->preview($state->work, $state->user, '2026-11-18', 35, 99);
    expect($preview['combined_points'])->toBe(45.0);
    $result = $state->service->store($state->work, $state->user, $preview['id']);
    expect($result['multiple_choice']['id'])->toBe(99);
    $status = collect($state->writes)->where('name', 'status.json')->last();
    expect(json_decode($status['bytes'], true)['components']['multiple_choice'])->toBe(['included' => false, 'points' => null]);
});

test('stops a changed preview before writing anything', function (): void {
    $state = directAssessmentFixture();
    $preview = $state->service->preview($state->work, $state->user, '2026-11-18', 35);
    $state->context['work']['title'] = 'Changed';
    try {
        $state->service->store($state->work, $state->user, $preview['id']);
    } finally {
        expect($state->writes)->toBeEmpty();
    }
})->throws(HttpException::class);

test('rejects a temporary module before creating anything', function (): void {
    $state = directAssessmentFixture();
    $state->temporary = true;
    try {
        $state->service->preview($state->work, $state->user, '2026-11-18', 35);
    } finally {
        expect($state->writes)->toBeEmpty();
    }
})->throws(HttpException::class);

test('resumes an incomplete package without overwriting the confirmed files or creating a duplicate', function (): void {
    $state = directAssessmentFixture();
    $preview = $state->service->preview($state->work, $state->user, '2026-11-18', 35);
    $state->fail = 'Beurteilung.pdf';
    $events = [];
    $report = function (string $event, ?string $step = null) use (&$events): void {
        $events[] = [$event, $step];
    };
    try {
        $state->service->store($state->work, $state->user, $preview['id'], $report);
        test()->fail('The simulated write must fail.');
    } catch (ValidationException) {
        expect(end($events))->toBe(['start', 'assessment_documents']);
        $status = collect($state->writes)->firstWhere('name', 'status.json');
        expect(json_decode($status['bytes'], true)['prepared'])->toBeFalse();
    }
    $state->fail = null;
    $events = [];
    $result = $state->service->store($state->work, $state->user, $preview['id'], $report);
    expect(end($events))->toBe(['completed', 'assessment_verify']);
    expect($result['participants_count'])->toBe(2);
    expect(collect($state->folders)->where('name', '2026-11-18 – 3B_Gruppe-2 – Word'))->toHaveCount(1);
    expect(collect($state->writes)->where('name', 'Beurteilung.md'))->toHaveCount(1);
    $final = $state->files[$result['folder']['id'].'/status.json'];
    expect(json_decode($final['contents'], true)['prepared'])->toBeTrue();
});

test('does not replace an externally advanced status during a retry', function (): void {
    $state = directAssessmentFixture();
    $preview = $state->service->preview($state->work, $state->user, '2026-11-18', 35);
    $state->fail = 'Beurteilung.pdf';
    try {
        $state->service->store($state->work, $state->user, $preview['id']);
        test()->fail('The simulated write must fail.');
    } catch (ValidationException) {
        $folder = collect($state->folders)->firstWhere('name', '2026-11-18 – 3B_Gruppe-2 – Word');
        $key = $folder['id'].'/status.json';
        $state->files[$key]['contents'] = '{"schema_version":1,"kind":"leistungsfeststellung.status","schooltool":{"work_id":10,"course_id":20},"prepared":true,"further_progress":true}';
        $state->files[$key]['rev'] = 'advanced';
    }
    $before = count($state->writes);
    try {
        $state->service->store($state->work, $state->user, $preview['id']);
    } finally {
        expect($state->files[$key]['rev'])->toBe('advanced');
        expect($state->writes)->toHaveCount($before);
    }
})->throws(ValidationException::class);

test('a concurrently discovered folder for the same work prevents a completed status', function (): void {
    $state = directAssessmentFixture();
    $preview = $state->service->preview($state->work, $state->user, '2026-11-18', 35);
    $state->onWrite = function (object $state, string $name): void {
        if ($name !== 'LF_Word.pdf' || isset($state->folders['id:external'])) {
            return;
        }
        $root = collect($state->folders)->firstWhere('name', 'Leistungsfeststellungen');
        $state->folders['id:external'] = ['.tag' => 'folder', 'id' => 'id:external', 'name' => 'Andere Durchführung',
            'path_lower' => $root['path_lower'].'/andere durchführung'];
        $state->files['id:external/status.json'] = ['contents' => json_encode(['schema_version' => 1, 'kind' => 'leistungsfeststellung.status',
            'schooltool' => ['work_id' => 10, 'course_id' => 20], 'prepared' => true]), 'rev' => 'external'];
    };
    try {
        $state->service->store($state->work, $state->user, $preview['id']);
    } finally {
        $status = collect($state->writes)->where('name', 'status.json');
        expect($status->every(fn (array $write): bool => json_decode($write['bytes'], true)['prepared'] === false))->toBeTrue();
        expect(json_decode($status->last()['bytes'], true)['progress']['attempts'][0]['state'])->toBe('failed');
    }
})->throws(ValidationException::class);

test('resumes a matching partial package after its local preview cache is lost without overwriting files', function (): void {
    $state = directAssessmentFixture();
    $preview = $state->service->preview($state->work, $state->user, '2026-11-18', 35);
    $state->fail = 'LF_Word.pdf';
    try {
        $state->service->store($state->work, $state->user, $preview['id']);
        test()->fail('The simulated first attempt must fail.');
    } catch (ValidationException) {
    }
    $priorWrites = $state->writes;
    Cache::flush();
    $state->fail = null;
    $next = $state->service->preview($state->work, $state->user, '2026-11-18', 35);
    expect($next['reusable_folder'])->toBe('partial');
    $events = [];
    $result = $state->service->store($state->work, $state->user, $next['id'], function (string $event, ?string $step = null, mixed $details = null) use (&$events): void {
        $events[] = [$event, $step, $details];
    });
    expect($result['participants_count'])->toBe(2)
        ->and(collect($state->folders)->where('name', '2026-11-18 – 3B_Gruppe-2 – Word'))->toHaveCount(1)
        ->and(collect($state->writes)->where('name', 'Beurteilung.md'))->toHaveCount(1)
        ->and(count($state->writes))->toBeGreaterThan(count($priorWrites))
        ->and(collect($events)->contains(fn ($event) => $event[0] === 'completed' && $event[1] === 'assessment_candidate_0'))->toBeTrue();
});

test('rejects foreign or changed partial files before any further writes after cache loss', function (string $case): void {
    $state = directAssessmentFixture();
    $preview = $state->service->preview($state->work, $state->user, '2026-11-18', 35);
    $state->fail = 'Beurteilung.pdf';
    try {
        $state->service->store($state->work, $state->user, $preview['id']);
    } catch (ValidationException) {
    }
    $folder = collect($state->folders)->firstWhere('name', '2026-11-18 – 3B_Gruppe-2 – Word');
    if ($case === 'changed') {
        $state->files[$folder['id'].'/Beurteilung.md']['contents'] = 'foreign';
    } else {
        $state->files[$folder['id'].'/Fremd.txt'] = ['contents' => 'foreign', 'rev' => 'foreign'];
    }
    $before = count($state->writes);
    Cache::flush();
    try {
        $state->service->preview($state->work, $state->user, '2026-11-18', 35);
        test()->fail('Foreign or changed content must be rejected.');
    } catch (HttpException) {
        expect(count($state->writes))->toBe($before);
    }
})->with(['changed', 'foreign']);

test('reads the candidate parent a bounded number of times for several candidates', function (int $count): void {
    $state = directAssessmentFixture();
    $state->context['course_roster']['participants'] = array_map(fn ($n) => ['first_name' => 'Vorname'.$n, 'last_name' => 'Name'.$n], range(1, $count));
    $state->listRequests = [];
    $preview = $state->service->preview($state->work, $state->user, '2026-11-18', 35);
    $result = $state->service->store($state->work, $state->user, $preview['id']);
    $parent = collect($state->folders)->firstWhere('name', 'Kandidaten');
    expect($result['participants_count'])->toBe($count)
        ->and(count(array_filter($state->listRequests, fn ($id) => $id === $parent['id'])))->toBe(2);
    foreach (range(1, $count) as $number) {
        $person = collect($state->folders)->firstWhere('name', 'Name'.$number.' Vorname'.$number);
        expect(count(array_filter($state->listRequests, fn ($id) => $id === $person['id'])))->toBe(1);
    }
})->with([1, 6]);

test('rejects changed partial status formatting or revision without overwriting', function (string $case): void {
    $state = directAssessmentFixture();
    $preview = $state->service->preview($state->work, $state->user, '2026-11-18', 35);
    $state->fail = 'Beurteilung.pdf';
    try {
        $state->service->store($state->work, $state->user, $preview['id']);
    } catch (ValidationException) {
    }
    $folder = collect($state->folders)->firstWhere('name', '2026-11-18 – 3B_Gruppe-2 – Word');
    $key = $folder['id'].'/status.json';
    Cache::flush();
    $before = count($state->writes);
    if ($case === 'format') {
        $state->files[$key]['contents'] = json_encode(json_decode($state->files[$key]['contents'], true), JSON_THROW_ON_ERROR);
        try {
            $state->service->preview($state->work, $state->user, '2026-11-18', 35);
            test()->fail('Reformatted status must not be reused.');
        } catch (HttpException) {
            expect(count($state->writes))->toBe($before);
        }

        return;
    }
    $next = $state->service->preview($state->work, $state->user, '2026-11-18', 35);
    if ($case === 'file-revision') {
        $state->files[$folder['id'].'/Beurteilung.md']['rev'] = 'changed-revision';
    } else {
        $state->files[$key]['rev'] = 'changed-revision';
    }
    try {
        $state->service->store($state->work, $state->user, $next['id']);
        test()->fail('Changed status revision must not be reused.');
    } catch (HttpException) {
        expect(count($state->writes))->toBe($before);
    }
})->with(['format', 'revision', 'file-revision']);

test('does not mark a recovered package prepared if a foreign file appears during completion', function (): void {
    $state = directAssessmentFixture();
    $first = $state->service->preview($state->work, $state->user, '2026-11-18', 35);
    $state->fail = 'Beurteilung.pdf';
    try {
        $state->service->store($state->work, $state->user, $first['id']);
    } catch (ValidationException) {
    }
    $folder = collect($state->folders)->firstWhere('name', '2026-11-18 – 3B_Gruppe-2 – Word');
    Cache::flush();
    $state->fail = null;
    $next = $state->service->preview($state->work, $state->user, '2026-11-18', 35);
    $state->onWrite = function ($state, string $name) use ($folder): void {
        if ($name === 'LF_Word.pdf') {
            $state->files[$folder['id'].'/Fremd.txt'] = ['contents' => 'foreign', 'rev' => 'foreign'];
        }
    };
    try {
        $state->service->store($state->work, $state->user, $next['id']);
        test()->fail('Foreign content must stop final confirmation.');
    } catch (ValidationException) {
        expect(json_decode($state->files[$folder['id'].'/status.json']['contents'], true)['prepared'])->toBeFalse();
    }
});

test('reports only the allowlisted Dropbox folder failure and the verified partial state', function (): void {
    Log::spy();
    $state = directAssessmentFixture();
    $first = $state->service->preview($state->work, $state->user, '2026-11-18', 35);
    $state->onWrite = function ($state, string $name): void {
        if ($name === 'status.json') {
            throw new DropboxFolderCreationFailed('no_write_permission');
        }
    };
    try {
        $state->service->store($state->work, $state->user, $first['id']);
        test()->fail('Simulated Dropbox failure must not succeed.');
    } catch (ValidationException $exception) {
        $message = $exception->errors()['generation'][0];
        expect($message)->toContain('Schreibberechtigung')->toContain('Statusdatei ist noch nicht bestätigt')
            ->not->toContain('no_write_permission');
        Log::shouldHaveReceived('warning')->once()->with('Direct assessment creation incomplete', Mockery::on(
            fn ($context) => $context === ['phase' => 'status', 'candidate_index' => null, 'exception_class' => DropboxFolderCreationFailed::class,
                'endpoint' => 'files/create_folder_v2', 'operation' => 'folder_create', 'category' => 'no_write_permission', 'http_status' => null]));
    }
});

test('continues a status-only unfinished folder after the preview cache is lost', function (): void {
    $state = directAssessmentFixture();
    $first = $state->service->preview($state->work, $state->user, '2026-11-18', 35);
    $state->onWrite = function ($state, string $name): void {
        if ($name === 'status.json') {
            throw new RuntimeException('Lost response');
        }
    };
    try {
        $state->service->store($state->work, $state->user, $first['id']);
    } catch (ValidationException) {
    }
    $folder = collect($state->folders)->firstWhere('name', '2026-11-18 – 3B_Gruppe-2 – Word');
    expect(count($state->files))->toBe(1)->and(count($state->folders))->toBe(3);
    Cache::flush();
    $state->onWrite = null;
    $retry = $state->service->preview($state->work, $state->user, '2026-11-18', 35);
    expect($retry['reusable_folder'])->toBe('partial');
    $result = $state->service->store($state->work, $state->user, $retry['id']);
    $status = json_decode($state->files[$folder['id'].'/status.json']['contents'], true);
    expect($result['folder']['id'])->toBe($folder['id'])
        ->and($result['participants_count'])->toBe(2)
        ->and($status['prepared'])->toBeTrue()
        ->and(array_column($status['progress']['attempts'], 'state'))->toBe(['interrupted', 'completed']);
});

test('rejects a recovered candidate entry with a missing Dropbox type before any write', function (): void {
    $state = directAssessmentFixture();
    $first = $state->service->preview($state->work, $state->user, '2026-11-18', 35);
    $state->fail = 'LF_Word.pdf';
    $state->failFolder = 'Angaben';
    try {
        $state->service->store($state->work, $state->user, $first['id']);
    } catch (ValidationException) {
    }
    $person = collect($state->folders)->firstWhere('name', 'Beispiel Anna');
    unset($state->folders[$person['id']]['.tag']);
    $before = count($state->writes);
    Cache::flush();
    try {
        $state->service->preview($state->work, $state->user, '2026-11-18', 35);
        test()->fail('Missing Dropbox type must prevent recovery.');
    } catch (HttpException) {
        expect(count($state->writes))->toBe($before);
    }
});

test('keeps a single module owner through a creation lasting longer than the lease', function (): void {
    $start = Carbon::parse('2026-11-18 12:00:00', 'UTC');
    Carbon::setTestNow($start);
    try {
        $state = directAssessmentFixture();
        $preview = $state->service->preview($state->work, $state->user, '2026-11-18', 35);
        $elapsed = 0;
        $blocked = false;
        $state->onCreate = function ($state, string $name) use ($start, $preview, &$elapsed, &$blocked): void {
            $elapsed += 45;
            Carbon::setTestNow($start->copy()->addSeconds($elapsed));
            if ($elapsed > 300 && ! $blocked) {
                try {
                    $state->service->store($state->work, $state->user, $preview['id']);
                    test()->fail('A competing attempt must not enter the module.');
                } catch (HttpException $exception) {
                    expect($exception->getStatusCode())->toBe(409);
                    $blocked = true;
                }
            }
        };
        $result = $state->service->store($state->work, $state->user, $preview['id']);
        expect($elapsed)->toBeGreaterThan(300)->and($blocked)->toBeTrue()->and($result['participants_count'])->toBe(2);
    } finally {
        Carbon::setTestNow();
    }
});

test('stops before the next external write when the cache lease belongs to another owner', function (): void {
    $state = directAssessmentFixture();
    $preview = $state->service->preview($state->work, $state->user, '2026-11-18', 35);
    $name = 'teaching:direct-assessment:'.hash('sha256', 'id:module');
    $foreign = null;
    $state->onWrite = function ($state, string $nameWritten) use ($name, &$foreign): void {
        if ($nameWritten !== 'status.json') {
            return;
        }
        Cache::lock($name, 300)->forceRelease();
        $foreign = Cache::lock($name, 300);
        expect($foreign->get())->toBeTrue();
    };
    try {
        $state->service->store($state->work, $state->user, $preview['id']);
        test()->fail('A lost lease must stop creation.');
    } catch (ValidationException) {
        expect(collect($state->writes)->pluck('name')->all())->toBe(['status.json'])
            ->and(collect($state->folders)->where('name', 'Kandidaten'))->toBeEmpty()
            ->and(Cache::lock($name, 300)->get())->toBeFalse();
    } finally {
        $foreign?->release();
    }
});

test('releases its process lock after a failed creation so the same attempt can resume', function (): void {
    $state = directAssessmentFixture();
    $preview = $state->service->preview($state->work, $state->user, '2026-11-18', 35);
    $state->fail = 'Beurteilung.pdf';
    try {
        $state->service->store($state->work, $state->user, $preview['id']);
    } catch (ValidationException) {
    }
    $state->fail = null;
    $result = $state->service->store($state->work, $state->user, $preview['id']);
    expect($result['participants_count'])->toBe(2);
});

test('refuses a cache backend without an ownership-protected lock', function (): void {
    $state = directAssessmentFixture();
    config(['cache.stores.no_assessment_lock' => ['driver' => 'null']]);
    Cache::setDefaultDriver('no_assessment_lock');
    try {
        (new ReflectionMethod($state->service, 'acquireModuleLock'))->invoke($state->service, 'id:module');
        test()->fail('A non-locking cache must not perform writes.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(503)->and($state->writes)->toBeEmpty();
    } finally {
        Cache::setDefaultDriver('array');
    }
});

test('the local file cache and process guard prevent a second owner after lease expiry', function (): void {
    $state = directAssessmentFixture();
    Cache::setDefaultDriver('file');
    $start = Carbon::parse('2026-11-18 12:00:00', 'UTC');
    Carbon::setTestNow($start);
    $acquire = new ReflectionMethod($state->service, 'acquireModuleLock');
    $release = new ReflectionMethod($state->service, 'releaseModuleLock');
    $guard = null;
    $lock = null;
    try {
        [$guard, $lock] = $acquire->invoke($state->service, 'id:module-file-test');
        Carbon::setTestNow($start->copy()->addSeconds(301));
        try {
            $acquire->invoke($state->service, 'id:module-file-test');
            test()->fail('An active process must keep its module guard after lease expiry.');
        } catch (HttpException $exception) {
            expect($exception->getStatusCode())->toBe(409);
        }
        $release->invoke($state->service, $guard, $lock);
        $guard = null;
        [$guard, $lock] = $acquire->invoke($state->service, 'id:module-file-test');
        expect($lock->refresh(300))->toBeTrue();
    } finally {
        if ($guard !== null) {
            $release->invoke($state->service, $guard, $lock);
        }
        Carbon::setTestNow();
        Cache::setDefaultDriver('array');
    }
});

test('a crash-released process guard waits for a stale file lease and recovers after expiry', function (): void {
    $state = directAssessmentFixture();
    Cache::setDefaultDriver('file');
    $start = Carbon::parse('2026-11-18 12:00:00', 'UTC');
    Carbon::setTestNow($start);
    $acquire = new ReflectionMethod($state->service, 'acquireModuleLock');
    $release = new ReflectionMethod($state->service, 'releaseModuleLock');
    $guard = null;
    $lock = null;
    try {
        [$guard, $lock] = $acquire->invoke($state->service, 'id:crashed-module');
        flock($guard, LOCK_UN);
        fclose($guard);
        $guard = null;
        try {
            $acquire->invoke($state->service, 'id:crashed-module');
            test()->fail('The stale cache lease must prevent immediate re-entry.');
        } catch (HttpException $exception) {
            expect($exception->getStatusCode())->toBe(409);
        }
        Carbon::setTestNow($start->copy()->addSeconds(301));
        [$guard, $lock] = $acquire->invoke($state->service, 'id:crashed-module');
        expect($lock->refresh(300))->toBeTrue();
    } finally {
        if ($guard !== null) {
            $release->invoke($state->service, $guard, $lock);
        }
        Carbon::setTestNow();
        Cache::setDefaultDriver('array');
    }
});

test('early initialization and full creation exclude each other for the same module', function (): void {
    $state = directAssessmentFixture();
    $state->work->date_for_all_groups = Carbon::parse('2026-11-18');
    $preview = $state->service->preview($state->work, $state->user, '2026-11-18', 35);
    $blocked = false;
    $state->onCreate = function ($state, string $name) use ($preview, &$blocked): void {
        if ($blocked) {
            return;
        }
        try {
            $state->service->store($state->work, $state->user, $preview['id']);
            test()->fail('Creation must not enter an initializing module.');
        } catch (HttpException $exception) {
            expect($exception->getStatusCode())->toBe(409);
            $blocked = true;
        }
    };
    $result = $state->service->initializeLinkedFolder($state->work, $state->user);
    expect($blocked)->toBeTrue()->and($result['status'])->toBe('initialized');
});

test('preserves a failed second candidate checkpoint and confirms each candidate again after cache loss', function (): void {
    $state = directAssessmentFixture();
    $preview = $state->service->preview($state->work, $state->user, '2026-11-18', 35);
    $state->onCreate = function (object $state, string $name): void {
        $created = end($state->folders);
        if ($name === 'Angaben' && str_contains($created['path_lower'], '/test ben/')) {
            throw new DropboxExerciseRequestFailed('folder_child_metadata', 'provider_unavailable', 503);
        }
    };
    try {
        $state->service->store($state->work, $state->user, $preview['id']);
        test()->fail('The second candidate must remain unconfirmed.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['generation'][0])->toContain('Neu angelegten Ordner rückprüfen');
    }
    $folder = collect($state->folders)->firstWhere('name', '2026-11-18 – 3B_Gruppe-2 – Word');
    $statusPath = $folder['id'].'/status.json';
    $checkpoint = json_decode($state->files[$statusPath]['contents'], true);
    expect($checkpoint['prepared'])->toBeFalse()
        ->and(TeachingAssessmentProgress::valid($checkpoint['progress'], false))->toBeTrue()
        ->and($checkpoint['progress']['attempts'][0])->toMatchArray(['state' => 'failed', 'phase' => 'candidate_subfolder',
            'confirmed_candidates' => [0], 'active_candidate' => 1,
            'error' => ['operation' => 'folder_child_metadata', 'category' => 'provider_unavailable', 'http_status' => 503]])
        ->and($state->files[$statusPath]['contents'])->not->toContain('Anna', 'Ben');
    $original = array_diff_key($state->files, [$statusPath => true]);
    Cache::flush();
    $state->onCreate = null;
    $retry = $state->service->preview($state->work, $state->user, '2026-11-18', 35);
    expect($retry['reusable_folder'])->toBe('partial');
    $result = $state->service->store($state->work, $state->user, $retry['id']);
    $complete = json_decode($state->files[$statusPath]['contents'], true);
    expect($result['folder']['id'])->toBe($folder['id'])
        ->and(array_intersect_key($state->files, $original))->toBe($original)
        ->and(TeachingAssessmentProgress::valid($complete['progress'], true))->toBeTrue()
        ->and($complete['progress']['attempts'][0])->toBe($checkpoint['progress']['attempts'][0])
        ->and($complete['progress']['attempts'][1])->toMatchArray(['state' => 'completed', 'phase' => 'complete',
            'confirmed_candidates' => [0, 1], 'active_candidate' => null, 'error' => null]);
    expect($complete['progress']['attempts'][1]['completed_steps'])->toContain('assessment_documents', 'assessment_verify')
        ->not->toContain('assessment_mc');
});

test('a concurrent status revision prevents a candidate checkpoint from replacing it or announcing completion', function (): void {
    $state = directAssessmentFixture();
    $preview = $state->service->preview($state->work, $state->user, '2026-11-18', 35);
    $statusKey = null;
    $foreignStatus = null;
    $state->onWrite = function (object $state, string $name) use (&$statusKey, &$foreignStatus): void {
        if ($name === 'LF_Word.pdf') {
            $folder = collect($state->folders)->firstWhere('name', '2026-11-18 – 3B_Gruppe-2 – Word');
            $statusKey = $folder['id'].'/status.json';
            $state->files[$statusKey]['rev'] = 'foreign-revision';
            $foreignStatus = $state->files[$statusKey];
        }
    };
    $events = [];
    try {
        $state->service->store($state->work, $state->user, $preview['id'], function ($event, $step = null) use (&$events): void {
            $events[] = [$event, $step];
        });
        test()->fail('The changed status revision must prevent completion.');
    } catch (ValidationException) {
        expect($statusKey)->not->toBeNull()
            ->and($state->files[$statusKey])->toBe($foreignStatus)
            ->and($events)->not->toContain(['completed', 'assessment_candidate_0'])
            ->and(collect($state->folders)->where('name', 'Test Ben'))->toBeEmpty();
    }
});
