<?php

use App\Models\DropboxConnection;
use App\Models\Import116;
use App\Models\McAttempt;
use App\Models\McCatalog;
use App\Models\McTest;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseWork;
use App\Models\TeachingEntryArea;
use App\Models\TeachingEntryDefinition;
use App\Models\TeachingWorkModuleLink;
use App\Models\User;
use App\Services\DropboxModuleLinks;
use App\Services\MultipleChoice\McModuleTemplateSource;
use App\Services\TeachingWorkMultipleChoice;
use App\Services\TeachingWorkStudentDistribution;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

test('simultaneous catalog confirmations create only one test and one attempt', function (): void {
    require_once dirname(__DIR__).'/Support/MultipleChoiceTestDatabase.php';
    requireMultipleChoiceTestDatabase();
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('This row-lock concurrency regression requires the isolated MySQL test workflow.');
    }
    $existingTeacherRole = Role::query()->where('name', 'teacher')->where('guard_name', 'web')->value('id');
    $existingStudentRole = Role::query()->where('name', 'student')->where('guard_name', 'web')->value('id');
    Role::findOrCreate('teacher', 'web');
    Role::findOrCreate('student', 'web');
    $school = School::factory()->create();
    $year = Schoolyear::factory()->create(['school_id' => $school->id]);
    $teacher = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id])->assignRole('teacher');
    $student = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id])->assignRole('student');
    $course = TeachingCourse::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id, 'user_id' => $teacher->id]);
    $course->teachingCourseStudents()->create(['user_id' => $student->id]);
    $date = now()->addDays(2)->format('Y-m-d');
    $work = TeachingCourseWork::factory()->create(['teaching_course_id' => $course->id, 'date_for_all_groups' => $date,
        'finish_until_date' => now()->addDays(3)->format('Y-m-d'),
        'groups' => [['date' => $date, 'student_ids' => [$student->id]]], 'status' => []]);
    $questions = [];
    for ($index = 1; $index <= 10; $index++) {
        $questions[] = ['id' => 'q'.$index, 'prompt' => 'Frage '.$index, 'type' => 'single', 'active' => true, 'reviewed' => true,
            'options' => [['id' => 'a', 'text' => 'A'], ['id' => 'b', 'text' => 'B']], 'correct_option_ids' => ['a']];
    }
    $catalog = McCatalog::query()->create(['school_id' => $school->id, 'owner_id' => $teacher->id, 'title' => 'Paralleltest', 'questions' => $questions]);
    $connection = DB::connection();
    $connection->commit();
    $workerCode = <<<'PHP'
require getcwd().'/vendor/autoload.php';
require getcwd().'/tests/Support/MultipleChoiceTestDatabase.php';
requireMultipleChoiceTestDatabase();
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$work = App\Models\TeachingCourseWork::query()->findOrFail((int) $argv[1]);
$actor = App\Models\User::query()->findOrFail((int) $argv[2]);
$controller = $app->make(App\Http\Controllers\Admin\Teaching\CourseWorkController::class);
$method = new ReflectionMethod($controller, 'updateMcCatalogSelection');
fwrite(STDOUT, "READY\n");
$result = $method->invoke($controller, $work, $actor, ['mc_selection' => 'yes', 'catalog_id' => (int) $argv[3], 'test_id' => null, 'expected_test_id' => null, 'date' => $argv[4]]);
fwrite(STDOUT, json_encode(['id' => $result['linked_test_id'], 'action' => $result['action']], JSON_THROW_ON_ERROR)."\n");
PHP;
    $environment = ['APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'mysql',
        'DB_DATABASE' => $connection->getDatabaseName(), 'DB_DATABASE_TEST' => $connection->getDatabaseName(),
        'DB_HOST' => config('database.connections.mysql.host'), 'DB_PORT' => (string) config('database.connections.mysql.port'),
        'DB_USERNAME' => config('database.connections.mysql.username'), 'DB_PASSWORD' => config('database.connections.mysql.password'),
        'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
        'SCHOOLTOOL_MC_TEST_RECEIPT' => (string) ($_SERVER['SCHOOLTOOL_MC_TEST_RECEIPT'] ?? $_ENV['SCHOOLTOOL_MC_TEST_RECEIPT'] ?? getenv('SCHOOLTOOL_MC_TEST_RECEIPT')),
        'APP_CONFIG_CACHE' => 'bootstrap/cache/mc-catalog-concurrency-'.bin2hex(random_bytes(6)).'.config.php', 'TEST_TOKEN' => 'mc-catalog-concurrency'];
    $workers = [];
    $locked = false;
    try {
        $connection->beginTransaction();
        $locked = true;
        TeachingCourseWork::query()->whereKey($work->id)->lockForUpdate()->firstOrFail();
        foreach ([1, 2] as $index) {
            $worker = new Process([PHP_BINARY, '-r', $workerCode, (string) $work->id, (string) $teacher->id, (string) $catalog->id, $date], base_path(), $environment);
            $worker->setTimeout(25)->start();
            $workers[] = $worker;
        }
        $until = microtime(true) + 15;
        do {
            $ready = count(array_filter($workers, fn (Process $worker): bool => str_contains($worker->getOutput(), "READY\n")));
            if ($ready === 2) {
                break;
            }
            usleep(50000);
        } while (microtime(true) < $until);
        expect($ready)->toBe(2);
        $connection->commit();
        $locked = false;
        foreach ($workers as $worker) {
            $this->assertSame(0, $worker->wait(), $worker->getErrorOutput().$worker->getOutput());
        }
        $results = array_map(fn (Process $worker): array => json_decode(trim(str_replace('READY', '', $worker->getOutput())), true, flags: JSON_THROW_ON_ERROR), $workers);
        expect($results[0]['id'])->toBe($results[1]['id'])
            ->and(collect($results)->pluck('action')->sort()->values()->all())->toBe(['created', 'unchanged'])
            ->and(McTest::query()->where('catalog_id', $catalog->id)->count())->toBe(1)
            ->and(McAttempt::query()->where('test_id', $results[0]['id'])->count())->toBe(1);
    } finally {
        if ($locked) {
            $connection->rollBack();
        }
        foreach ($workers as $worker) {
            if ($worker->isRunning()) {
                $worker->stop();
            }
        }
        try {
            DB::transaction(function () use ($work, $course, $catalog, $teacher, $student, $year, $school, $existingTeacherRole, $existingStudentRole): void {
                $testIds = McTest::query()->where('catalog_id', $catalog->id)->pluck('id');
                McAttempt::query()->whereIn('test_id', $testIds)->delete();
                McTest::query()->whereIn('id', $testIds)->delete();
                McCatalog::query()->whereKey($catalog->id)->delete();
                DB::table('teaching_course_work_group_students')->where('teaching_course_work_id', $work->id)->delete();
                DB::table('teaching_course_works')->where('id', $work->id)->delete();
                DB::table('teaching_course_students')->where('teaching_course_id', $course->id)->delete();
                DB::table('teaching_courses')->where('id', $course->id)->delete();
                DB::table('model_has_roles')->where('model_type', User::class)->whereIn('model_id', [$teacher->id, $student->id])->delete();
                DB::table('users')->whereIn('id', [$teacher->id, $student->id])->delete();
                DB::table('schoolyears')->where('id', $year->id)->delete();
                DB::table('schools')->where('id', $school->id)->delete();
                if ($existingTeacherRole === null) {
                    Role::query()->where('name', 'teacher')->where('guard_name', 'web')->delete();
                }
                if ($existingStudentRole === null) {
                    Role::query()->where('name', 'student')->where('guard_name', 'web')->delete();
                }
            });
        } finally {
            $connection->beginTransaction();
        }
    }
});

test('catalog preparation resolves a previous-year student account through the current course import', function (bool $importOnly): void {
    workMcFixture($this);
    $this->partialMock(TeachingWorkMultipleChoice::class)->shouldReceive('configuration')->andReturn(['locked' => false]);
    $import = Import116::factory()->create(['school_id' => $this->course->school_id,
        'schoolyear_id' => $this->course->schoolyear_id, 'user_id' => $this->student->id]);
    $this->student->forceFill(['schoolyear_id' => null, 'import116_id' => $import->id])->save();
    $membership = $this->course->teachingCourseStudents()->firstOrFail();
    $membership->update(['import116_id' => $import->id, ...($importOnly ? ['user_id' => null] : [])]);
    $this->work->update(['groups' => [['date' => '2026-10-20', 'student_ids' => [$importOnly ? $import->id : $this->student->id]]]]);
    $payload = ['mc_selection' => 'yes', 'catalog_id' => $this->catalog->id, 'test_id' => null,
        'expected_test_id' => null, 'date' => '2026-10-20'];
    $id = $this->postJson($this->base.'/prepare-exercise', $payload)->assertOk()->json('linked_test_id');
    expect(McAttempt::query()->where('test_id', $id)->pluck('student_id')->all())->toBe([$this->student->id]);
    $this->getJson($this->base.'/student-distribution-preview')->assertOk()->assertJsonPath('data.tests.0.id', $id);
    $this->work->refresh();
    $status = $this->work->status;
    $status['student_distribution'] = ['published_at' => now()->toIso8601String(), 'student_ids' => [$this->student->id]];
    $this->work->update(['status' => $status]);
    expect(app(TeachingWorkStudentDistribution::class)->visibleTo($this->work->fresh('teachingCourse'), $this->student))->toBeTrue();
    Http::assertNothingSent();
})->with([false, true]);

test('catalog preparation keeps the student role and current course identity requirements', function (string $problem): void {
    workMcFixture($this);
    $this->partialMock(TeachingWorkMultipleChoice::class)->shouldReceive('configuration')->andReturn(['locked' => false]);
    $import = Import116::factory()->create(['school_id' => $this->course->school_id,
        'schoolyear_id' => $this->course->schoolyear_id, 'user_id' => $this->student->id]);
    $this->course->teachingCourseStudents()->firstOrFail()->update(['import116_id' => $import->id]);
    $this->work->update(['groups' => [['date' => '2026-10-20', 'student_ids' => [$this->student->id]]]]);
    if ($problem === 'role') {
        $this->student->removeRole('student');
    } elseif ($problem === 'school') {
        $this->student->forceFill(['school_id' => School::factory()->create()->id])->save();
    } elseif ($problem === 'import') {
        $import->update(['user_id' => User::factory()->create(['school_id' => $this->course->school_id])->id]);
    } else {
        $this->student->forceFill(['schoolyear_id' => null])->save();
        $this->course->teachingCourseStudents()->firstOrFail()->update(['import116_id' => null]);
    }
    $response = $this->postJson($this->base.'/prepare-exercise', ['mc_selection' => 'yes', 'catalog_id' => $this->catalog->id,
        'test_id' => null, 'expected_test_id' => null, 'date' => '2026-10-20'])->assertUnprocessable();
    expect(json_encode($response->json(), JSON_UNESCAPED_UNICODE))->toContain(match ($problem) {
        'role' => 'Schülerrolle', 'school' => 'zur Schule gehörendes Schülerkonto',
        'import' => 'widerspricht', 'year' => 'nicht eindeutig belegt',
    });
    expect(McTest::query()->count())->toBe(0);
    Http::assertNothingSent();
})->with(['role', 'school', 'import', 'year']);

test('catalog selection prepares one individual test and repeats without duplicates', function (): void {
    workMcFixture($this);
    $this->partialMock(TeachingWorkMultipleChoice::class)->shouldReceive('configuration')->andReturn(['locked' => false]);
    $this->work->update(['groups' => [['date' => '2026-10-20', 'student_ids' => [$this->student->id]]]]);
    $this->getJson($this->base.'/exercise-options?mc_selection=catalogs')->assertOk()
        ->assertJsonPath('catalogs.0.id', $this->catalog->id)->assertJsonPath('defaults.duration_minutes', 20);
    $this->getJson($this->base.'/exercise-options?mc_selection=candidates&catalog_id='.$this->catalog->id.'&date=2026-10-20')
        ->assertOk()->assertJsonCount(0, 'candidates')->assertJsonPath('available_question_count', 10);
    $payload = ['mc_selection' => 'yes', 'catalog_id' => $this->catalog->id, 'test_id' => null,
        'expected_test_id' => null, 'date' => '2026-10-20'];
    $id = $this->postJson($this->base.'/prepare-exercise', $payload)->assertOk()->assertJsonPath('action', 'created')->json('linked_test_id');
    $test = McTest::query()->findOrFail($id);
    expect($test->status)->toBe('draft')->and($test->start_mode)->toBe('individual')
        ->and($test->question_count)->toBe(10)->and((float) $test->total_points)->toBe(10.0)
        ->and($test->duration_seconds)->toBe(1200)->and($test->lead_seconds)->toBe(30)
        ->and($test->closes_at->setTimezone('Europe/Vienna')->format('Y-m-d H:i:s'))->toBe('2026-10-20 23:59:00')
        ->and($test->attempts()->pluck('student_id')->all())->toBe([$this->student->id])
        ->and($this->work->fresh()->status['multiple_choice_tests']['2026-10-20']['test_id'])->toBe($id);
    $this->postJson($this->base.'/prepare-exercise', [...$payload, 'question_count' => 6, 'total_points' => 13.75, 'duration_minutes' => 12])
        ->assertOk()->assertJsonPath('action', 'unchanged')->assertJsonPath('linked_test_id', $id)
        ->assertJsonPath('test.question_count', 10)->assertJsonPath('test.total_points', 10)->assertJsonPath('test.duration_minutes', 20);
    expect(McTest::query()->count())->toBe(1)->and(McAttempt::query()->count())->toBe(1);
    Mail::assertNothingSent();
    Http::assertNothingSent();
});

test('adding a test to a prepared worksheet keeps the student release and requires that it has never gone live', function (string $state): void {
    workMcFixture($this);
    $release = ['prepared_at' => now()->toIso8601String(), 'instructions' => 'Aufgabenblatt ist vorbereitet'];
    if ($state === 'paused') {
        $release += ['published_at' => now()->toIso8601String(), 'withdrawn_at' => now()->toIso8601String()];
    }
    $status = ['automation_enabled' => true, 'student_distribution' => $release];
    if ($state === 'mail') {
        $status['assignment_mail'] = ['batches' => ['2026-10-20' => [
            'transport' => 'postmark', 'recipients' => [['state' => 'queued']],
        ]]];
    }
    $this->work->update(['groups' => [['date' => '2026-10-20', 'student_ids' => [$this->student->id]]], 'status' => $status]);
    $payload = ['mc_selection' => 'yes', 'mc_add_only' => true, 'catalog_id' => $this->catalog->id,
        'test_id' => null, 'expected_test_id' => null, 'date' => '2026-10-20'];
    if ($state === 'prepared') {
        $response = $this->postJson($this->base.'/prepare-exercise', $payload + ['stream_progress' => true])
            ->assertOk()->assertHeader('Content-Type', 'application/x-ndjson');
        $events = collect(explode("\n", trim($response->streamedContent())))->map(fn (string $event): array => json_decode($event, true, flags: JSON_THROW_ON_ERROR));
        expect($events->where('type', 'progress')->pluck('data.active')->all())->toBe(['mc_check', 'mc_prepare', 'mc_assign', null]);
        expect($events->where('type', 'progress')->last()['data']['completed'])->toBe(['mc_check', 'mc_prepare', 'mc_assign']);
        $result = $events->last();
        expect($result['type'])->toBe('result')->and($result['data']['action'])->toBe('created');
        expect(McTest::query()->sole()->status)->toBe('draft');
        $this->postJson($this->base.'/prepare-exercise', $payload)
            ->assertOk()->assertJsonPath('linked_test_id', $result['data']['linked_test_id']);
    } else {
        $this->postJson($this->base.'/prepare-exercise', $payload)->assertConflict();
        expect(McTest::query()->count())->toBe(0);
    }
    expect($this->work->fresh()->status['student_distribution'])->toBe($release);
    Mail::assertNothingSent();
    Http::assertNothingSent();
})->with(['prepared', 'paused', 'mail']);

test('catalog selection creates a new individual test using the requested settings', function (): void {
    workMcFixture($this);
    $this->partialMock(TeachingWorkMultipleChoice::class)->shouldReceive('configuration')->andReturn(['locked' => false]);
    $this->work->update(['groups' => [['date' => '2026-10-20', 'student_ids' => [$this->student->id]]]]);

    $response = $this->postJson($this->base.'/prepare-exercise', ['mc_selection' => 'yes', 'catalog_id' => $this->catalog->id,
        'test_id' => null, 'expected_test_id' => null, 'date' => '2026-10-20', 'question_count' => 7, 'total_points' => 23.75, 'duration_minutes' => 35]);
    $response->assertOk()->assertJsonPath('action', 'created')->assertJsonPath('test.question_count', 7)
        ->assertJsonPath('test.total_points', 23.75)->assertJsonPath('test.duration_minutes', 35);
    $test = McTest::query()->findOrFail($response->json('linked_test_id'));
    expect($test->question_count)->toBe(7)->and($test->total_points)->toBe('23.75')->and($test->duration_seconds)->toBe(2100);
    expect($test->attempts()->sole()->questions)->toHaveCount(7);
    Http::assertNothingSent();
});

test('catalog selection rejects invalid new test settings without saving a test or assignment', function (string $field, mixed $value): void {
    workMcFixture($this);
    $this->partialMock(TeachingWorkMultipleChoice::class)->shouldReceive('configuration')->andReturn(['locked' => false]);
    $this->work->update(['groups' => [['date' => '2026-10-20', 'student_ids' => [$this->student->id]]]]);
    $this->postJson($this->base.'/prepare-exercise', ['mc_selection' => 'yes', 'catalog_id' => $this->catalog->id,
        'test_id' => null, 'expected_test_id' => null, 'date' => '2026-10-20', $field => $value])
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    expect(McTest::query()->count())->toBe(0)->and(McAttempt::query()->count())->toBe(0)
        ->and($this->work->fresh()->status['multiple_choice_tests'] ?? [])->toBe([]);
    Http::assertNothingSent();
})->with([
    'fractional question count' => ['question_count', 1.5],
    'questions over upper limit' => ['question_count', 2001],
    'zero points' => ['total_points', 0],
    'points over precision' => ['total_points', 7.123],
    'points over upper limit' => ['total_points', 100001],
    'blank minutes' => ['duration_minutes', null],
    'minutes over upper limit' => ['duration_minutes', 1441],
]);

test('catalog preparation creates attempts only for active group accounts', function (): void {
    workMcFixture($this);
    $this->partialMock(TeachingWorkMultipleChoice::class)->shouldReceive('configuration')->andReturn(['locked' => false]);
    $inactive = User::factory()->create(['school_id' => $this->course->school_id, 'schoolyear_id' => $this->course->schoolyear_id])->assignRole('student');
    $this->course->teachingCourseStudents()->create(['user_id' => $inactive->id]);
    $this->work->update(['groups' => [['date' => '2026-10-20', 'student_ids' => [$this->student->id, $inactive->id]]]]);
    $inactive->forceFill(['is_active' => false])->save();
    $groups = $this->work->fresh()->groups;

    $id = $this->postJson($this->base.'/prepare-exercise', ['mc_selection' => 'yes', 'catalog_id' => $this->catalog->id,
        'test_id' => null, 'expected_test_id' => null, 'date' => '2026-10-20'])->assertOk()->json('linked_test_id');

    expect(McAttempt::query()->where('test_id', $id)->pluck('student_id')->all())->toBe([$this->student->id])
        ->and($this->work->fresh()->groups)->toEqual($groups)
        ->and($inactive->fresh()->is_active)->toBeFalsy();
    Http::assertNothingSent();
});

test('catalog selection reuses the proven test while preserving a now inactive group member attempt', function (): void {
    workMcFixture($this);
    $this->partialMock(TeachingWorkMultipleChoice::class)->shouldReceive('configuration')->andReturn(['locked' => false]);
    $inactive = User::factory()->create(['school_id' => $this->course->school_id, 'schoolyear_id' => $this->course->schoolyear_id])->assignRole('student');
    $this->course->teachingCourseStudents()->create(['user_id' => $inactive->id]);
    $this->work->update(['groups' => [['date' => '2026-10-20', 'student_ids' => [$this->student->id, $inactive->id]]]]);
    $payload = ['mc_selection' => 'yes', 'catalog_id' => $this->catalog->id, 'test_id' => null, 'expected_test_id' => null, 'date' => '2026-10-20'];
    $id = $this->postJson($this->base.'/prepare-exercise', $payload)->assertOk()->json('linked_test_id');
    $attempt = McAttempt::query()->where('test_id', $id)->where('student_id', $inactive->id)->firstOrFail();
    $before = $attempt->getRawOriginal();
    $inactive->forceFill(['is_active' => false])->save();
    $status = $this->work->fresh()->status;
    unset($status['multiple_choice_tests']['2026-10-20']);
    $this->work->update(['status' => $status]);

    $this->getJson($this->base.'/exercise-options?mc_selection=candidates&catalog_id='.$this->catalog->id.'&date=2026-10-20')
        ->assertOk()->assertJsonPath('candidates.0.id', $id);
    $this->postJson($this->base.'/prepare-exercise', $payload)->assertOk()->assertJsonPath('action', 'reused')->assertJsonPath('linked_test_id', $id);
    expect(McTest::query()->count())->toBe(1)->and(McAttempt::query()->count())->toBe(2)
        ->and($attempt->fresh()->getRawOriginal())->toBe($before);
    Http::assertNothingSent();
});

test('an unproven matching test with a now inactive group member still prevents a duplicate', function (): void {
    workMcFixture($this);
    $this->partialMock(TeachingWorkMultipleChoice::class)->shouldReceive('configuration')->andReturn(['locked' => false]);
    $inactive = User::factory()->create(['school_id' => $this->course->school_id, 'schoolyear_id' => $this->course->schoolyear_id])->assignRole('student');
    $this->course->teachingCourseStudents()->create(['user_id' => $inactive->id]);
    $this->work->update(['groups' => [['date' => '2026-10-20', 'student_ids' => [$this->student->id, $inactive->id]]]]);
    $payload = ['mc_selection' => 'yes', 'catalog_id' => $this->catalog->id, 'test_id' => null, 'expected_test_id' => null, 'date' => '2026-10-20'];
    $id = $this->postJson($this->base.'/prepare-exercise', $payload)->assertOk()->json('linked_test_id');
    $test = McTest::query()->findOrFail($id);
    $snapshot = $test->snapshot;
    unset($snapshot['schooltool_work']);
    $test->update(['snapshot' => $snapshot]);
    $inactive->forceFill(['is_active' => false])->save();
    $status = $this->work->fresh()->status;
    unset($status['multiple_choice_tests']['2026-10-20']);
    $this->work->update(['status' => $status]);

    $this->getJson($this->base.'/exercise-options?mc_selection=candidates&catalog_id='.$this->catalog->id.'&date=2026-10-20')->assertConflict();
    $this->postJson($this->base.'/prepare-exercise', $payload)->assertConflict();
    expect(McTest::query()->count())->toBe(1)->and(McAttempt::query()->count())->toBe(2);
    Http::assertNothingSent();
});

test('catalog selection reuses a proven existing test without changing its attempts', function (): void {
    workMcFixture($this);
    $this->partialMock(TeachingWorkMultipleChoice::class)->shouldReceive('configuration')->andReturn(['locked' => false]);
    $this->work->update(['groups' => [['date' => '2026-10-20', 'student_ids' => [$this->student->id]]]]);
    $payload = ['mc_selection' => 'yes', 'catalog_id' => $this->catalog->id, 'test_id' => null,
        'expected_test_id' => null, 'date' => '2026-10-20'];
    $id = $this->postJson($this->base.'/prepare-exercise', $payload)->assertOk()->json('linked_test_id');
    $attempt = McAttempt::query()->where('test_id', $id)->firstOrFail();
    $attempt->update(['started_at' => now()]);
    $before = $attempt->getAttributes();
    $testBefore = $attempt->test->getRawOriginal();
    $status = $this->work->fresh()->status;
    unset($status['multiple_choice_tests']['2026-10-20']);
    $this->work->update(['status' => $status]);
    $this->getJson($this->base.'/exercise-options?mc_selection=candidates&catalog_id='.$this->catalog->id.'&date=2026-10-20')
        ->assertOk()->assertJsonPath('candidates.0.id', $id);
    $this->postJson($this->base.'/prepare-exercise', [...$payload, 'question_count' => 8, 'total_points' => 15, 'duration_minutes' => 30])
        ->assertOk()->assertJsonPath('action', 'reused')->assertJsonPath('linked_test_id', $id);
    expect(McTest::query()->count())->toBe(1)->and($attempt->fresh()->getAttributes())->toBe($before)
        ->and(McTest::query()->findOrFail($id)->getRawOriginal())->toBe($testBefore);
    Http::assertNothingSent();
});

test('catalog selection fails without valid prerequisites and does not leave tests', function (string $problem): void {
    workMcFixture($this);
    $this->partialMock(TeachingWorkMultipleChoice::class)->shouldReceive('configuration')->andReturn(['locked' => false]);
    $groups = [['date' => '2026-10-20', 'student_ids' => [$this->student->id]]];
    if ($problem === 'unknown') {
        $groups[0]['student_ids'][] = 999999;
    }
    $this->work->update(['groups' => $groups, ...match ($problem) {
        'deadline' => ['finish_until_date' => null], 'past_deadline' => ['finish_until_date' => '2026-09-30'], default => [],
    }]);
    if ($problem === 'questions') {
        $questions = $this->catalog->questions;
        $questions[8]['active'] = false;
        $questions[9]['reviewed'] = false;
        $this->catalog->update(['questions' => $questions]);
        $this->getJson($this->base.'/exercise-options?mc_selection=candidates&catalog_id='.$this->catalog->id.'&date=2026-10-20')
            ->assertOk()->assertJsonPath('available_question_count', 8);
    }
    $response = $this->postJson($this->base.'/prepare-exercise', ['mc_selection' => 'yes', 'catalog_id' => $this->catalog->id,
        'test_id' => null, 'expected_test_id' => null, 'date' => '2026-10-20'])->assertUnprocessable();
    expect(json_encode($response->json(), JSON_UNESCAPED_UNICODE))->toContain(match ($problem) {
        'unknown' => 'nicht eindeutig', 'deadline' => 'Enddatum', 'past_deadline' => 'in der Zukunft', 'questions' => 'zu wenige aktive, geprüfte Fragen',
    });
    if ($problem === 'questions') {
        $response->assertJsonValidationErrors('question_count');
    }
    if (in_array($problem, ['deadline', 'past_deadline'], true)) {
        $response->assertJsonValidationErrors('finish_until_date');
    }
    expect(McTest::query()->count())->toBe(0)->and(McAttempt::query()->count())->toBe(0);
    Http::assertNothingSent();
})->with(['unknown', 'deadline', 'past_deadline', 'questions']);

test('catalog selection stops on an unproven possible matching test', function (): void {
    workMcFixture($this);
    $this->partialMock(TeachingWorkMultipleChoice::class)->shouldReceive('configuration')->andReturn(['locked' => false]);
    $this->work->update(['groups' => [['date' => '2026-10-20', 'student_ids' => [$this->student->id]]]]);
    $payload = ['mc_selection' => 'yes', 'catalog_id' => $this->catalog->id, 'test_id' => null,
        'expected_test_id' => null, 'date' => '2026-10-20'];
    $id = $this->postJson($this->base.'/prepare-exercise', $payload)->assertOk()->json('linked_test_id');
    $test = McTest::query()->findOrFail($id);
    $snapshot = $test->snapshot;
    unset($snapshot['schooltool_work']);
    $test->update(['snapshot' => $snapshot]);
    $status = $this->work->fresh()->status;
    unset($status['multiple_choice_tests']['2026-10-20']);
    $this->work->update(['status' => $status]);
    $this->getJson($this->base.'/exercise-options?mc_selection=candidates&catalog_id='.$this->catalog->id.'&date=2026-10-20')->assertConflict();
    $this->postJson($this->base.'/prepare-exercise', $payload)->assertConflict();
    expect(McTest::query()->count())->toBe(1);
    Http::assertNothingSent();
});

test('modern MC selection binds an existing prepared test without changing test or attempts', function (): void {
    workMcBoundModuleFixture($this);
    $this->partialMock(TeachingWorkMultipleChoice::class)->shouldReceive('configuration')->andReturn(['locked' => false]);
    $this->work->update(['groups' => [['date' => '2026-10-20', 'student_ids' => [$this->student->id]]], 'status' => []]);
    $this->test->forceFill(['snapshot' => ['questions' => [['id' => 'q1']]]])->save();
    $attempt = McAttempt::query()->create(['test_id' => $this->test->id, 'student_id' => $this->student->id,
        'student_name' => 'Testperson', 'email' => $this->student->email, 'questions' => [], 'answers' => [],
        'access_token' => 'fixture-token', 'access_token_hash' => hash('sha256', 'fixture-token')]);
    $testBefore = $this->test->fresh()->getAttributes();
    $attemptBefore = $attempt->fresh()->getAttributes();
    $this->getJson($this->base.'/exercise-options?mc_selection=current&date=2026-10-20')->assertOk()->assertJsonPath('linked_test_id', null);
    $workBefore = $this->work->fresh()->getAttributes();
    $this->postJson($this->base.'/prepare-exercise', ['mc_selection' => 'no', 'date' => '2026-10-20', 'test_id' => null, 'expected_test_id' => null])
        ->assertOk()->assertJsonPath('linked_test_id', null);
    expect($this->work->fresh()->getAttributes())->toBe($workBefore);
    $this->getJson($this->base.'/exercise-options?mc_selection=tests&date=2026-10-20')->assertOk()
        ->assertJsonPath('tests.0.id', $this->test->id)->assertJsonMissing(['access_token' => 'fixture-token']);
    $this->postJson($this->base.'/prepare-exercise', ['mc_selection' => 'yes', 'date' => '2026-10-20', 'test_id' => $this->test->id, 'expected_test_id' => null])
        ->assertOk()->assertJsonPath('linked_test_id', $this->test->id);
    $workBefore = $this->work->fresh()->getAttributes();
    $this->postJson($this->base.'/prepare-exercise', ['mc_selection' => 'yes', 'date' => '2026-10-20', 'test_id' => $this->test->id, 'expected_test_id' => $this->test->id])
        ->assertOk()->assertJsonPath('linked_test_id', $this->test->id);
    expect($this->work->fresh()->getAttributes())->toBe($workBefore);
    $this->getJson($this->base.'/student-distribution-preview')->assertOk()->assertJsonPath('data.tests.0.id', $this->test->id);
    $this->postJson($this->base.'/prepare-exercise', ['mc_selection' => 'no', 'date' => '2026-10-20', 'test_id' => null, 'expected_test_id' => $this->test->id])
        ->assertOk()->assertJsonPath('linked_test_id', null);
    expect($this->test->fresh()->getAttributes())->toBe($testBefore)
        ->and($attempt->fresh()->getAttributes())->toBe($attemptBefore);
    $otherWork = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id,
        'status' => ['multiple_choice_tests' => ['all' => ['work_id' => 123, 'test_id' => $this->test->id]]]]);
    $this->getJson($this->base.'/exercise-options?mc_selection=tests&date=2026-10-20')->assertOk()->assertJsonCount(0, 'tests');
    $this->postJson($this->base.'/prepare-exercise', ['mc_selection' => 'yes', 'date' => '2026-10-20', 'test_id' => $this->test->id, 'expected_test_id' => null])->assertUnprocessable();
    $otherWork->update(['status' => []]);
    $otherStudent = User::factory()->create(['school_id' => $this->teacher->school_id, 'schoolyear_id' => $this->teacher->schoolyear_id])->assignRole('student');
    McAttempt::query()->create(['test_id' => $this->test->id, 'student_id' => $otherStudent->id,
        'student_name' => 'Andere Person', 'email' => $otherStudent->email, 'questions' => [], 'answers' => [],
        'access_token' => 'another-token', 'access_token_hash' => hash('sha256', 'another-token')]);
    $this->getJson($this->base.'/exercise-options?mc_selection=tests&date=2026-10-20')->assertOk()->assertJsonCount(0, 'tests');
    $this->postJson($this->base.'/prepare-exercise', ['mc_selection' => 'yes', 'date' => '2026-10-20', 'test_id' => $this->test->id, 'expected_test_id' => null])->assertUnprocessable();
    Http::assertNothingSent();
});

test('modern MC selection refuses stale changes and preserves begun attempts', function (): void {
    workMcBoundModuleFixture($this);
    $this->partialMock(TeachingWorkMultipleChoice::class)->shouldReceive('configuration')->andReturn(['locked' => false]);
    $this->work->update(['groups' => [['date' => '2026-10-20', 'student_ids' => [$this->student->id]]]]);
    $attempt = McAttempt::query()->create(['test_id' => $this->test->id, 'student_id' => $this->student->id,
        'student_name' => 'Testperson', 'email' => $this->student->email, 'questions' => [], 'answers' => [],
        'submitted_at' => now(), 'access_token' => 'fixture-token', 'access_token_hash' => hash('sha256', 'fixture-token')]);
    $before = $attempt->fresh()->getAttributes();
    $this->getJson($this->base.'/exercise-options?mc_selection=tests&date=2026-10-20')->assertOk()->assertJsonPath('tests.0.id', $this->test->id);
    $this->postJson($this->base.'/prepare-exercise', ['mc_selection' => 'yes', 'date' => '2026-10-20', 'test_id' => $this->test->id, 'expected_test_id' => $this->test->id])->assertOk();
    $this->postJson($this->base.'/prepare-exercise', ['mc_selection' => 'no', 'date' => '2026-10-20', 'test_id' => null, 'expected_test_id' => null])->assertConflict();
    $this->postJson($this->base.'/prepare-exercise', ['mc_selection' => 'no', 'date' => '2026-10-20', 'test_id' => null, 'expected_test_id' => $this->test->id])->assertConflict();
    expect($this->work->fresh()->status['multiple_choice_tests']['2026-10-20']['test_id'])->toBe($this->test->id)
        ->and($attempt->fresh()->getAttributes())->toBe($before);
    $this->work->update(['groups' => []]);
    $this->getJson($this->base.'/exercise-options?mc_selection=tests&date=2026-10-20')->assertOk()->assertJsonPath('tests.0.id', $this->test->id);
    Http::assertNothingSent();
});

test('modern MC selection preserves binding when task delivery has begun', function (): void {
    workMcBoundModuleFixture($this);
    $this->partialMock(TeachingWorkMultipleChoice::class)->shouldReceive('configuration')->once()->andReturn([
        'locked' => true, 'message' => 'Der Aufgabenversand hat bereits begonnen.']);
    $before = $this->work->fresh()->getAttributes();
    $this->postJson($this->base.'/prepare-exercise', ['mc_selection' => 'no', 'date' => '2026-10-20', 'test_id' => null, 'expected_test_id' => $this->test->id])
        ->assertConflict()->assertJsonPath('message', 'Der Aufgabenversand hat bereits begonnen.');
    expect($this->work->fresh()->getAttributes())->toBe($before);
    Http::assertNothingSent();
});

test('stale MC preparation requires a confirmed legacy folder', function (array $exercise): void {
    workMcBoundModuleFixture($this);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->once()->andReturn($exercise);
    $before = $this->work->fresh()->getAttributes();
    $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertUnprocessable()
        ->assertJsonPath('message', 'Der bisherige MC-Ablauf ist nur für einen online bestätigten alten Prüfungsordner verfügbar.');
    expect(McTest::query()->count())->toBe(1)->and($this->test->fresh()->snapshot)->toBeNull()
        ->and($this->work->fresh()->getAttributes())->toBe($before);
    Http::assertNothingSent();
})->with([
    'modern' => [['status' => 'found', 'folder' => ['id' => 'id:modern'], 'preparation' => ['status' => 'ready']]],
    'unknown' => [['status' => 'unknown', 'folder' => null]],
    'missing' => [['status' => 'missing', 'folder' => null]],
]);

test('confirmed legacy exercise keeps its original MC handoff', function (): void {
    workMcBoundModuleFixture($this);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->andReturn([
        'status' => 'found', 'folder' => ['id' => 'id:legacy'], 'preparation' => null, 'email_dispatch' => ['status' => 'missing'],
    ]);
    $this->mock(McModuleTemplateSource::class)->shouldReceive('assessmentPoints')->andReturn(35.0);
    $data = $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertOk()->json('data');
    expect($data['context']['exercise_folder']['id'])->toBe('id:legacy')
        ->and($data['context']['multiple_choice_test']['test_id'])->toBe($this->test->id)
        ->and($data['text'])->toContain('create-exercise', 'bestätigten bestehenden Legacy-Leistungsfeststellungsordner')
        ->not->toContain('/AI 2027/10_Arbeitsbereiche/Schule/Skills/Leistungsfeststellungen/Erstellen/SKILL.md');
    Http::assertNothingSent();
});

function workMcFixture(object $context): void
{
    $context->travelTo(CarbonImmutable::parse('2026-10-01 12:00:00', 'Europe/Vienna'));
    Http::preventStrayRequests();
    Mail::fake();
    foreach (['teacher', 'student'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    $school = School::factory()->create();
    $year = Schoolyear::factory()->create(['school_id' => $school->id, 'name' => '2026/27', 'from' => '2026-09-01', 'until' => '2027-07-10']);
    enableSchoolToolModuleForTests($school, 'teaching');
    grantSchoolToolLicenceForTests($school, 'Lehrertool');
    $context->teacher = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id])->assignRole('teacher');
    $context->student = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id])->assignRole('student');
    $area = TeachingEntryArea::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id, 'user_id' => $context->teacher->id]);
    TeachingEntryDefinition::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id, 'user_id' => $context->teacher->id,
        'teaching_entry_area_id' => $area->id, 'short_name' => 'MA', 'name' => 'Leistungsfeststellung', 'category' => 'Benotung',
        'has_properties' => true, 'properties_mode' => 'points', 'maximum_points' => 5]);
    $context->course = TeachingCourse::factory()->forSchool($school)->forSchoolyear($year)->forTeacher($context->teacher)->create(['teaching_entry_area_id' => $area->id]);
    $context->course->teachingCourseStudents()->create(['user_id' => $context->student->id]);
    $context->work = TeachingCourseWork::factory()->create(['teaching_course_id' => $context->course->id,
        'date_for_all_groups' => '2026-10-20', 'finish_until_date' => '2026-10-20', 'finish_until_time' => null,
        'groups' => [], 'is_group_work' => false, 'status' => ['automation_enabled' => true]]);
    $questions = [];
    for ($index = 1; $index <= 10; $index++) {
        $questions[] = ['id' => 'q'.$index, 'prompt' => 'Frage '.$index, 'type' => 'single', 'active' => true, 'reviewed' => true,
            'options' => [['id' => 'a', 'text' => 'A'], ['id' => 'b', 'text' => 'B']], 'correct_option_ids' => ['a']];
    }
    $context->catalog = McCatalog::query()->create(['school_id' => $school->id, 'owner_id' => $context->teacher->id,
        'title' => 'Geprüfte Grundlagen', 'questions' => $questions]);
    $context->base = '/api/admin/teaching/course_works/'.$context->work->id;
    $context->payload = ['mc_mode' => 'new', 'catalog_id' => $context->catalog->id, 'total_points' => 5,
        'mc_points' => 2, 'expected_maximum_points' => 5,
        'duration_minutes' => 15, 'request_id' => 'b10ca8e3-181f-48b7-b842-01a16c855ae8',
        'expected_test_id' => null, 'date' => '2026-10-20'];
    $context->actingAs($context->teacher, 'sanctum');
}

test('the LF prepares its bound draft MC test with its actual candidates and safely resumes the same request', function (): void {
    workMcBoundModuleFixture($this);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->andReturn(['status' => 'found', 'folder' => ['id' => 'id:legacy'],
        'preparation' => null, 'email_dispatch' => ['status' => 'missing']]);
    $before = $this->work->getAttributes();
    $response = $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertOk()
        ->assertJsonPath('data.context.include_multiple_choice', true)
        ->assertJsonPath('data.context.multiple_choice_test.status', 'draft');
    $id = $response->json('data.context.multiple_choice_test.test_id');
    $test = McTest::query()->findOrFail($id);
    expect($test->question_count)->toBe(10)->and($test->duration_seconds)->toBe(900)
        ->and($test->start_mode)->toBe('individual')->and($test->closes_at->setTimezone('Europe/Vienna')->format('Y-m-d H:i:s'))->toBe('2026-10-20 23:59:00')
        ->and($test->attempts()->pluck('student_id')->all())->toBe([$this->student->id]);
    expect($response->json('data.text'))->toContain('Schooltool-MC-Test '.$id, 'Keinen zweiten MC-Test',
        'create-exercise', 'bestätigten bestehenden Legacy-Leistungsfeststellungsordner')
        ->not->toContain('/AI 2027/10_Arbeitsbereiche/Schule/Skills/Leistungsfeststellungen/Erstellen/SKILL.md');
    expect($this->work->fresh()->status['multiple_choice_tests']['2026-10-20']['test_id'])->toBe($id);
    expect($this->work->fresh()->getAttributes())->not->toBe($before);
    expect($this->work->fresh()->groups)->toBe($this->work->groups);
    $response->assertJsonPath('data.context.multiple_choice_test.access_links_ready', false)
        ->assertJsonPath('data.context.multiple_choice_test.exercise_export_url', null)
        ->assertJsonCount(10, 'data.context.multiple_choice_test.question_pool');
    $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertOk()->assertJsonPath('data.context.multiple_choice_test.test_id', $id);
    expect(McTest::query()->count())->toBe(1);
    $this->getJson($this->base.'/exercise-options?date=2026-10-20')->assertOk()->assertJsonPath('linked_test_id', $id)
        ->assertJsonMissingPath('catalogs.0.questions')->assertJsonMissingPath('tests.0.snapshot');
    Mail::assertNothingSent();
    Http::assertNothingSent();
});

test('the LF rejects legacy requests to create tests with any question count', function (): void {
    workMcFixture($this);
    foreach ([0, 7, 11] as $count) {
        $this->postJson($this->base.'/prepare-exercise', [...$this->payload, 'question_count' => $count])
            ->assertUnprocessable()->assertJsonValidationErrors('mc_mode');
    }
    expect(McTest::query()->count())->toBe(0);
});

test('an already bound test can use a small reviewed catalog', function (): void {
    workMcBoundModuleFixture($this);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->andReturn(['status' => 'found', 'folder' => ['id' => 'id:legacy'],
        'preparation' => null, 'email_dispatch' => ['status' => 'missing']]);
    $this->catalog->update(['questions' => array_slice($this->catalog->questions, 0, 4)]);
    $this->test->update(['question_count' => 4]);
    $this->getJson($this->base.'/exercise-options?date=2026-10-20')->assertOk()
        ->assertJsonPath('catalogs.0.reviewed_question_count', 4);
    $this->postJson($this->base.'/prepare-exercise', $this->payload)
        ->assertOk()->assertJsonPath('data.context.multiple_choice_test.question_count', 4);
});

test('work responses show details only for tests bound to the work and its school year', function (): void {
    workMcBoundModuleFixture($this);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->andReturn(['status' => 'found', 'folder' => ['id' => 'id:legacy'],
        'preparation' => null, 'email_dispatch' => ['status' => 'missing']]);
    $id = $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertOk()->json('data.context.multiple_choice_test.test_id');
    $test = McTest::query()->findOrFail($id);
    $otherSchool = School::factory()->create();
    $otherYear = Schoolyear::factory()->create(['school_id' => $otherSchool->id]);
    $foreign = $test->replicate();
    $foreign->school_id = $otherSchool->id;
    $foreign->schoolyear_id = $otherYear->id;
    $foreign->title = 'Fremder Test';
    $foreign->save();
    $notBound = $test->replicate();
    $notBound->title = 'Nicht zugeordneter Test';
    $notBound->save();
    $status = $this->work->fresh()->status;
    $status['multiple_choice_tests']['foreign'] = ['work_id' => $this->work->id, 'test_id' => $foreign->id];
    $status['multiple_choice_tests']['other_work'] = ['work_id' => $this->work->id + 1, 'test_id' => $notBound->id];
    $this->work->update(['status' => $status]);

    $this->getJson($this->base)->assertOk()
        ->assertJsonPath('data.multiple_choice_test_titles.'.$id, $test->title)
        ->assertJsonPath('data.multiple_choice_test_details.'.$id.'.title', $test->title)
        ->assertJsonPath('data.multiple_choice_test_details.'.$id.'.points', (int) $test->total_points)
        ->assertJsonPath('data.multiple_choice_test_details.'.$id.'.question_count', $test->question_count)
        ->assertJsonPath('data.multiple_choice_test_details.'.$id.'.duration_seconds', $test->duration_seconds)
        ->assertJsonMissingPath('data.multiple_choice_test_details.'.$foreign->id)
        ->assertJsonMissingPath('data.multiple_choice_test_details.'.$notBound->id)
        ->assertJsonMissingPath('data.multiple_choice_test_titles.'.$foreign->id)
        ->assertJsonMissingPath('data.multiple_choice_test_titles.'.$notBound->id);
    $this->getJson('/api/admin/teaching/course_works?course_id='.$this->course->id)->assertOk()
        ->assertJsonPath('data.0.multiple_choice_test_titles.'.$id, $test->title)
        ->assertJsonPath('data.0.multiple_choice_test_details.'.$id.'.question_count', $test->question_count)
        ->assertJsonMissingPath('data.0.multiple_choice_test_details.'.$foreign->id)
        ->assertJsonMissingPath('data.0.multiple_choice_test_titles.'.$foreign->id);
});

test('LF MC tests use the saved end time when one is specified', function (): void {
    workMcBoundModuleFixture($this);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->andReturn(['status' => 'found', 'folder' => ['id' => 'id:legacy'],
        'preparation' => null, 'email_dispatch' => ['status' => 'missing']]);
    $this->work->update(['finish_until_date' => '2026-11-13', 'finish_until_time' => '12:25']);
    $id = $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertOk()->json('data.context.multiple_choice_test.test_id');
    expect(McTest::query()->findOrFail($id)->closes_at->setTimezone('Europe/Vienna')->toIso8601String())->toBe('2026-11-13T12:25:00+01:00');
});

test('LF MC creation requires a future saved end date and does not invent one', function (?string $date): void {
    workMcBoundModuleFixture($this);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->andReturn(['status' => 'found', 'folder' => ['id' => 'id:legacy'],
        'preparation' => null, 'email_dispatch' => ['status' => 'missing']]);
    $this->work->update(['finish_until_date' => $date]);
    $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertUnprocessable()->assertJsonValidationErrors('mc_mode');
    expect(McTest::query()->count())->toBe(1)->and($this->test->fresh()->snapshot)->toBeNull();
})->with([null, '2026-09-30']);

test('linking an unstarted common MC test changes its start and deadline without replacing questions or links', function (): void {
    workMcBoundModuleFixture($this);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->andReturn(['status' => 'found', 'folder' => ['id' => 'id:legacy'],
        'preparation' => null, 'email_dispatch' => ['status' => 'missing']]);
    $id = $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertOk()->json('data.context.multiple_choice_test.test_id');
    $test = McTest::query()->findOrFail($id);
    $snapshot = $test->snapshot;
    $snapshot['start_mode'] = 'common';
    $snapshot['closes_at'] = null;
    $test->update(['start_mode' => 'common', 'closes_at' => null, 'snapshot' => $snapshot]);
    $attempts = $test->attempts()->get()->toArray();
    $this->postJson($this->base.'/prepare-exercise', [...$this->payload, 'mc_mode' => 'existing', 'test_id' => $id,
        'expected_test_id' => $id, 'request_id' => 'e9b85247-dc5e-4e97-a281-5c57970eb739'])->assertOk();
    expect($test->fresh()->start_mode)->toBe('individual')
        ->and($test->fresh()->snapshot['start_mode'])->toBe('individual')
        ->and($test->fresh()->snapshot['questions'])->toBe($snapshot['questions'])
        ->and($test->attempts()->get()->toArray())->toBe($attempts);
});

test('linking cannot change a common MC test whose countdown or personal attempt already started', function (bool $countdown): void {
    workMcBoundModuleFixture($this);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->andReturn(['status' => 'found', 'folder' => ['id' => 'id:legacy'],
        'preparation' => null, 'email_dispatch' => ['status' => 'missing']]);
    $id = $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertOk()->json('data.context.multiple_choice_test.test_id');
    $test = McTest::query()->findOrFail($id);
    $test->update(['start_mode' => 'common', 'closes_at' => null, 'starts_at' => $countdown ? now()->addMinute() : null]);
    if (! $countdown) {
        $test->attempts()->first()->update(['started_at' => now()]);
    }
    $before = $test->fresh()->getAttributes();
    $this->postJson($this->base.'/prepare-exercise', [...$this->payload, 'mc_mode' => 'existing', 'test_id' => $id,
        'expected_test_id' => $id, 'request_id' => '29bc5361-d0dc-4fe8-9988-598062327c0e'])->assertUnprocessable()->assertJsonValidationErrors('mc_mode');
    expect($test->fresh()->getAttributes())->toBe($before);
})->with([true, false]);

test('the LF preserves the existing MC test title across course and work names', function (string $workTitle, string $courseTitle, array $classes, string $expectedTitle): void {
    workMcBoundModuleFixture($this);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->andReturn(['status' => 'found', 'folder' => ['id' => 'id:legacy'],
        'preparation' => null, 'email_dispatch' => ['status' => 'missing']]);
    $this->course->update(['title' => $courseTitle, 'classes' => $classes]);
    $this->work->update(['title' => $workTitle]);
    $this->test->update(['title' => $expectedTitle]);

    $response = $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertOk()
        ->assertJsonPath('data.context.multiple_choice_test.title', $expectedTitle);

    expect(McTest::query()->findOrFail($response->json('data.context.multiple_choice_test.test_id'))->title)->toBe($expectedTitle);
})->with([
    'user naming pattern' => ['Informatik', 'INF - 5A - 1', ['5A'], '2026-10-20_Informatik_5A_Gruppe-1'],
    'work title and second course group' => ['Dateimanagement', 'DGB - 3B - 2', ['3B'], '2026-10-20_Dateimanagement_3B_Gruppe-2'],
    'no course group' => ['Informatik', 'Informatik', ['5A'], '2026-10-20_Informatik_5A'],
    'joint classes without invented group' => ['Informatik', 'Informatik', ['5A', '5B'], '2026-10-20_Informatik_5A-5B'],
    'multiword title' => ['Dateien und Ordner', 'DGB - 3B - 1', ['3B'], '2026-10-20_Dateien-und-Ordner_3B_Gruppe-1'],
]);

test('the existing MC test is used for the selected work group date', function (): void {
    workMcBoundModuleFixture($this);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->andReturn(['status' => 'found', 'folder' => ['id' => 'id:legacy'],
        'preparation' => null, 'email_dispatch' => ['status' => 'missing']]);
    $this->course->update(['title' => 'Informatik - 5A - 1', 'classes' => ['5A']]);
    $this->work->update(['title' => 'Dateimanagement', 'date_for_all_groups' => null, 'finish_until_date' => '2026-11-20',
        'groups' => [['date' => '2026-11-13', 'student_ids' => [$this->student->id]]]]);
    $status = $this->work->fresh()->status;
    $status['multiple_choice_tests']['2026-11-13'] = $status['multiple_choice_tests']['2026-10-20'];
    $this->work->update(['status' => $status]);
    $this->test->update(['title' => 'Vorhandener Test']);

    $this->postJson($this->base.'/prepare-exercise', [...$this->payload, 'date' => '2026-11-13'])->assertOk()
        ->assertJsonPath('data.context.multiple_choice_test.title', 'Vorhandener Test');
});

test('an existing MC test is reused without a second test or changing its name or attempts', function (): void {
    workMcBoundModuleFixture($this);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->andReturn(['status' => 'found', 'folder' => ['id' => 'id:legacy'],
        'preparation' => null, 'email_dispatch' => ['status' => 'missing']]);
    $id = $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertOk()->json('data.context.multiple_choice_test.test_id');
    McTest::query()->findOrFail($id)->update(['title' => 'Eigener Testname']);
    $attempts = McTest::query()->findOrFail($id)->attempts()->get()->toArray();
    $this->postJson($this->base.'/prepare-exercise', ['mc_mode' => 'existing', 'mc_points' => 2, 'expected_maximum_points' => 5, 'test_id' => $id, 'expected_test_id' => $id,
        'date' => '2026-10-20', 'request_id' => 'a4c4b2fd-94de-4830-9bdf-c1db638ac17f'])->assertOk()
        ->assertJsonPath('data.context.multiple_choice_test.test_id', $id)
        ->assertJsonPath('data.context.multiple_choice_test.title', 'Eigener Testname');
    expect(McTest::query()->count())->toBe(1)->and(McTest::query()->findOrFail($id)->attempts()->get()->toArray())->toBe($attempts);
    $this->postJson($this->base.'/prepare-exercise', [...$this->payload, 'mc_mode' => 'new', 'request_id' => '571bbef6-a38e-4bb0-8c8c-892eb9361fa5'])
        ->assertUnprocessable();
    expect(McTest::query()->count())->toBe(1);
});

test('a removed linked test cannot be replaced or recreated by this workflow', function (): void {
    workMcBoundModuleFixture($this);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->andReturn(['status' => 'found', 'folder' => ['id' => 'id:legacy'],
        'preparation' => null, 'email_dispatch' => ['status' => 'missing']]);
    $oldId = $this->test->id;
    $this->test->delete();
    $this->getJson($this->base.'/exercise-options?date=2026-10-20')->assertOk()
        ->assertJsonPath('linked_test_id', null)->assertJsonPath('eligible_test', null)->assertJsonPath('expected_test_id', $oldId);
    $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertConflict();
    expect(McTest::query()->count())->toBe(0);
});

test('a numeric expected binding from JSON is compared by ID without weakening stale binding protection', function (): void {
    workMcBoundModuleFixture($this);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->andReturn(['status' => 'found', 'folder' => ['id' => 'id:legacy'],
        'preparation' => null, 'email_dispatch' => ['status' => 'missing']]);
    $id = $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertOk()->json('data.context.multiple_choice_test.test_id');

    $this->postJson($this->base.'/prepare-exercise', [...$this->payload, 'mc_mode' => 'existing', 'test_id' => $id,
        'expected_test_id' => (string) $id, 'request_id' => '5ae82094-4653-4d5b-b63f-c0b89d0df3a7'])->assertOk()
        ->assertJsonPath('data.context.multiple_choice_test.test_id', $id);
    $this->postJson($this->base.'/prepare-exercise', [...$this->payload, 'expected_test_id' => (string) ($id + 1),
        'request_id' => 'c2d40bfb-0886-4b70-bf56-c8b922e2681a'])->assertConflict();
    expect(McTest::query()->count())->toBe(1);
});

test('existing test choices identify their catalog even when it can no longer create a new test', function (): void {
    workMcBoundModuleFixture($this);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->andReturn(['status' => 'found', 'folder' => ['id' => 'id:legacy'],
        'preparation' => null, 'email_dispatch' => ['status' => 'missing']]);
    $id = $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertOk()->json('data.context.multiple_choice_test.test_id');
    $this->catalog->update(['questions' => []]);
    $this->getJson($this->base.'/exercise-options')->assertOk()
        ->assertJsonCount(0, 'catalogs')
        ->assertJsonPath('tests.0.id', $id)
        ->assertJsonPath('tests.0.catalog_id', $this->catalog->id)
        ->assertJsonPath('tests.0.catalog_title', 'Geprüfte Grundlagen')
        ->assertJsonMissingPath('tests.0.questions')
        ->assertJsonMissingPath('tests.0.snapshot');
    $other = User::factory()->create(['school_id' => $this->teacher->school_id, 'schoolyear_id' => $this->teacher->schoolyear_id]);
    $this->catalog->update(['owner_id' => $other->id]);
    $this->getJson($this->base.'/exercise-options')->assertOk()->assertJsonPath('tests.0.catalog_title', null);
});

test('MC choices and creation enforce course and catalog ownership without creating orphan tests', function (): void {
    workMcBoundModuleFixture($this);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->andReturn(['status' => 'found', 'folder' => ['id' => 'id:legacy'],
        'preparation' => null, 'email_dispatch' => ['status' => 'missing']]);
    $before = $this->work->fresh()->status;
    $other = User::factory()->create(['school_id' => $this->teacher->school_id, 'schoolyear_id' => $this->teacher->schoolyear_id])->assignRole('teacher');
    $this->catalog->update(['owner_id' => $other->id]);
    $this->getJson($this->base.'/exercise-options')->assertOk()->assertJsonCount(0, 'catalogs');
    $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertConflict();
    expect(McTest::query()->count())->toBe(1)->and($this->work->fresh()->status)->toBe($before);
    $this->actingAs($other, 'sanctum')->getJson($this->base.'/exercise-options')->assertForbidden();
    $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertForbidden();
});

test('incomplete question pools and dates outside the LF roll back the MC draft and binding', function (): void {
    workMcBoundModuleFixture($this);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->andReturn(['status' => 'found', 'folder' => ['id' => 'id:legacy'],
        'preparation' => null, 'email_dispatch' => ['status' => 'missing']]);
    $before = $this->work->fresh()->status;
    $this->catalog->update(['questions' => array_slice($this->catalog->questions, 0, 9)]);
    $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertUnprocessable();
    $this->postJson($this->base.'/prepare-exercise', [...$this->payload, 'date' => '2026-10-21'])->assertUnprocessable();
    expect(McTest::query()->count())->toBe(1)->and($this->work->fresh()->status)->toBe($before)->and($this->test->fresh()->snapshot)->toBeNull();
});

test('an existing test cannot be bound to candidates missing from its saved attempts', function (): void {
    workMcBoundModuleFixture($this);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->andReturn(['status' => 'found', 'folder' => ['id' => 'id:legacy'],
        'preparation' => null, 'email_dispatch' => ['status' => 'missing']]);
    $id = $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertOk()->json('data.context.multiple_choice_test.test_id');
    $other = User::factory()->create(['school_id' => $this->teacher->school_id, 'schoolyear_id' => $this->teacher->schoolyear_id])->assignRole('student');
    $this->course->teachingCourseStudents()->create(['user_id' => $other->id]);
    $this->work->update(['groups' => [['date' => '2026-10-20', 'student_ids' => [$other->id]]]]);
    $before = $this->work->fresh()->getAttributes();
    $this->postJson($this->base.'/prepare-exercise', ['mc_mode' => 'existing', 'mc_points' => 2, 'expected_maximum_points' => 5, 'test_id' => $id, 'expected_test_id' => $id,
        'date' => '2026-10-20', 'request_id' => 'a4c4b2fd-94de-4830-9bdf-c1db638ac17f'])->assertUnprocessable();
    expect($this->work->fresh()->getAttributes())->toBe($before)
        ->and(McTest::query()->findOrFail($id)->attempts()->pluck('student_id')->all())->toBe([$this->student->id]);
});

test('MC test selection rejects other owners and schoolyears even when an ID is supplied directly', function (string $changed): void {
    workMcBoundModuleFixture($this);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->andReturn(['status' => 'found', 'folder' => ['id' => 'id:legacy'],
        'preparation' => null, 'email_dispatch' => ['status' => 'missing']]);
    $id = $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertOk()->json('data.context.multiple_choice_test.test_id');
    $test = McTest::query()->findOrFail($id);
    $test->update($changed === 'owner' ? ['owner_id' => $this->student->id]
        : ['schoolyear_id' => Schoolyear::factory()->create(['school_id' => $this->teacher->school_id])->id]);
    $this->getJson($this->base.'/exercise-options')->assertOk()->assertJsonCount(0, 'tests')->assertJsonPath('linked_test_id', null);
    $this->postJson($this->base.'/prepare-exercise', ['mc_mode' => 'existing', 'mc_points' => 2, 'expected_maximum_points' => 5, 'test_id' => $id, 'expected_test_id' => $id,
        'date' => '2026-10-20', 'request_id' => 'a4c4b2fd-94de-4830-9bdf-c1db638ac17f'])->assertConflict();
})->with(['owner', 'year']);

test('MC allocation must fit the saved LF points and never creates an orphan test on failure', function (mixed $points): void {
    workMcBoundModuleFixture($this);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->andReturn(['status' => 'found', 'folder' => ['id' => 'id:legacy'],
        'preparation' => null, 'email_dispatch' => ['status' => 'missing']]);
    $before = $this->work->fresh()->status;
    $payload = array_diff_key($this->payload, ['other_points' => true]);
    $this->postJson($this->base.'/prepare-exercise', [...$payload, 'mc_points' => $points])->assertUnprocessable()
        ->assertJsonValidationErrors('mc_points');
    expect(McTest::query()->count())->toBe(1)->and($this->work->fresh()->status)->toBe($before)->and($this->test->fresh()->snapshot)->toBeNull();
})->with([null, 0, -1, 5.01]);

test('an existing MC test records an explicit conversion budget without increasing the LF maximum', function (): void {
    workMcBoundModuleFixture($this);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->andReturn(['status' => 'found', 'folder' => ['id' => 'id:legacy'],
        'preparation' => null, 'email_dispatch' => ['status' => 'missing']]);
    $this->test->update(['total_points' => 10]);
    $this->postJson($this->base.'/prepare-exercise', [...$this->payload, 'total_points' => 10])->assertOk()
        ->assertJsonPath('data.context.multiple_choice_test.points_plan.mc_points', 2)
        ->assertJsonPath('data.context.multiple_choice_test.points_plan.other_points', 3)
        ->assertJsonPath('data.context.multiple_choice_test.points_plan.test_maximum_points', 10);
    expect($this->work->fresh()->status['multiple_choice_tests']['2026-10-20']['points_plan']['maximum_points'])->toEqual(5);
});

test('assessment points can differ from the saved Schooltool scale without changing it', function (): void {
    workMcBoundModuleFixture($this);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->andReturn(['status' => 'found', 'folder' => ['id' => 'id:legacy'],
        'preparation' => null, 'email_dispatch' => ['status' => 'missing']]);
    $this->course->teachingEntryArea->entryDefinitions()->update(['maximum_points' => 15]);
    $payload = [...$this->payload, 'mc_points' => 5, 'total_points' => 5, 'other_points' => 30, 'expected_maximum_points' => 15];

    $response = $this->postJson($this->base.'/prepare-exercise', $payload)->assertOk()
        ->assertJsonPath('data.context.multiple_choice_test.points_plan.maximum_points', 35)
        ->assertJsonPath('data.context.multiple_choice_test.points_plan.mc_points', 5)
        ->assertJsonPath('data.context.multiple_choice_test.points_plan.other_points', 30)
        ->assertJsonPath('data.context.multiple_choice_test.points_plan.schooltool_maximum_points', 15);
    expect($response->json('data.text'))->toContain('create-exercise', 'MC-Anteil', 'Schooltool-Bewertungsskala');
    expect($this->course->teachingEntryArea->entryDefinitions()->first()->maximum_points)->toEqual(15);
    $this->getJson($this->base.'/exercise-options?date=2026-10-20')->assertOk()
        ->assertJsonPath('linked_other_points', 30);
});

test('invalid remaining points do not create a test', function (mixed $otherPoints): void {
    workMcBoundModuleFixture($this);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->andReturn(['status' => 'found', 'folder' => ['id' => 'id:legacy'],
        'preparation' => null, 'email_dispatch' => ['status' => 'missing']]);

    $this->postJson($this->base.'/prepare-exercise', [...$this->payload, 'other_points' => $otherPoints])
        ->assertUnprocessable()->assertJsonValidationErrors('other_points');
    expect(McTest::query()->count())->toBe(1)->and($this->test->fresh()->snapshot)->toBeNull();
})->with([-1, '2.345', 100000]);

test('changed LF totals invalidate an open MC allocation before creating a test', function (): void {
    workMcBoundModuleFixture($this);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->andReturn(['status' => 'found', 'folder' => ['id' => 'id:legacy'],
        'preparation' => null, 'email_dispatch' => ['status' => 'missing']]);
    TeachingEntryDefinition::query()->where('teaching_entry_area_id', $this->course->teaching_entry_area_id)->update(['maximum_points' => 4]);
    $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertConflict();
    expect(McTest::query()->count())->toBe(1)->and($this->test->fresh()->snapshot)->toBeNull();
});

test('a confirmed participant dispatch blocks MC additions even after an earlier unlocked preview', function (string $mode): void {
    workMcBoundModuleFixture($this);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->andReturn(['status' => 'found', 'folder' => ['id' => 'id:legacy'],
        'preparation' => null, 'email_dispatch' => ['status' => 'missing']]);
    app()->instance('env', 'local');
    $this->getJson($this->base.'/exercise-options')->assertOk()->assertJsonPath('configuration.locked', false);
    $hash = str_repeat('a', 64);
    $record = ['student_id' => $this->student->id, 'purpose' => 'tasks', 'origin' => 'dispatch_import',
        'log_sha256' => $hash, 'sent_at' => '2026-10-19T10:00:00Z', 'provider_id' => 'confirmed-1', 'mode' => $mode];
    $status = $this->work->fresh()->status;
    $status['dispatch_logs'] = [['origin' => 'dispatch_import', 'purpose' => 'tasks', 'sha256' => $hash]];
    $status[$mode === 'live' ? 'dispatch_notifications' : 'dispatch_attempts'] = [$record];
    $this->work->update(['status' => $status]);
    $status = $this->work->fresh()->status;
    $this->getJson($this->base.'/exercise-options')->assertOk()->assertJsonPath('configuration.locked', true);
    $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertUnprocessable()->assertJsonValidationErrors('mc_mode');
    expect(McTest::query()->count())->toBe(1)->and($this->work->fresh()->status)->toBe($status);
    $this->getJson($this->base.'/ai-export?purpose=exercise&include_mc=1&date=2026-10-20')->assertOk()
        ->assertJsonPath('data.context.include_multiple_choice', false);
})->with(['live', 'mailpit']);

test('MC mutation rechecks Dropbox dispatch and fails closed for partial or unclear delivery', function (string $dispatchStatus): void {
    workMcBoundModuleFixture($this);
    $connection = DropboxConnection::query()->where('user_id', $this->teacher->id)->firstOrFail();
    $before = $this->work->fresh()->status;
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->twice()
        ->withArgs(fn ($checkedConnection, $checkedWork, $link, $date, $refresh, $initial): bool => $checkedConnection->id === $connection->id && $checkedWork->id === $this->work->id
            && $date === '2026-10-20' && $refresh === true && $initial === false)
        ->andReturn(['status' => 'found', 'folder' => ['id' => 'id:lf'], 'preparation' => null,
            'email_dispatch' => ['status' => $dispatchStatus, 'confirmed_count' => $dispatchStatus === 'partial' ? 1 : 0]]);
    $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertUnprocessable()->assertJsonValidationErrors('mc_mode');
    expect(McTest::query()->count())->toBe(1)->and($this->work->fresh()->status)->toBe($before)->and($this->test->fresh()->snapshot)->toBeNull();
    Http::assertNothingSent();
})->with(['partial', 'unknown']);

test('modern exam MC configuration uses the separately checked dispatch status', function (string $dispatchStatus): void {
    workMcBoundModuleFixture($this);
    $this->mock(McModuleTemplateSource::class)->shouldReceive('assessmentPoints')->andReturn(35.0);
    $modules = $this->partialMock(DropboxModuleLinks::class);
    $modules->shouldReceive('exercise')->withArgs(fn ($connection, $work, $link, $date, $refresh, $initial): bool => $refresh === true)
        ->andReturn(['status' => 'found', 'folder' => ['id' => 'id:lf'],
            'preparation' => ['status' => 'ready'], 'email_dispatch' => ['status' => 'unknown']]);
    $modules->shouldReceive('preparedExerciseDispatch')->andReturn(['status' => $dispatchStatus,
        'confirmed_count' => in_array($dispatchStatus, ['sent', 'partial'], true) ? 1 : 0]);
    $this->getJson($this->base.'/exercise-options?date=2026-10-20')->assertOk()
        ->assertJsonPath('configuration.locked', $dispatchStatus !== 'missing');
    $before = $this->work->fresh()->status;
    $result = $this->postJson($this->base.'/prepare-exercise', [...$this->payload, 'mc_points' => 3, 'other_points' => 30]);
    $result->assertUnprocessable()->assertJsonPath('message', 'Der bisherige MC-Ablauf ist nur für einen online bestätigten alten Prüfungsordner verfügbar.');
    expect($this->work->fresh()->status)->toBe($before);
    Http::assertNothingSent();
})->with(['missing', 'sent', 'partial', 'unknown']);

function workMcBoundModuleFixture(object $context): void
{
    workMcFixture($context);
    $connection = DropboxConnection::factory()->create(['user_id' => $context->teacher->id]);
    TeachingWorkModuleLink::query()->create(['dropbox_connection_id' => $connection->id,
        'teaching_course_work_id' => $context->work->id, 'module_id' => 'a263b873-4f27-4167-90f0-7d94ea0f67fa', 'schema_version' => 1,
        'account_id' => $connection->account_id, 'folder_id' => 'id:module', 'folder_name' => 'Modul',
        'folder_path' => '/Materialien/Lerninhalte/Modul', 'handshake_file_id' => 'id:handshake', 'handshake_revision' => 'r1']);
    $context->catalog->update(['module_sources' => [['module_id' => 'a263b873-4f27-4167-90f0-7d94ea0f67fa',
        'module_folder_id' => 'id:module', 'source_file_id' => 'id:questions']]]);
    $context->test = McTest::query()->create(['school_id' => $context->teacher->school_id, 'schoolyear_id' => $context->teacher->schoolyear_id,
        'owner_id' => $context->teacher->id, 'catalog_id' => $context->catalog->id, 'title' => 'Zugeordneter Test',
        'status' => 'draft', 'question_count' => 10, 'total_points' => 5, 'duration_seconds' => 900, 'lead_seconds' => 60,
        'start_mode' => 'individual', 'closes_at' => CarbonImmutable::parse('2026-10-20 23:59:00', 'Europe/Vienna'),
        'exam_mode' => false, 'balance_types' => true, 'shuffle_questions' => true, 'shuffle_options' => true]);
    $status = $context->work->status;
    $status['multiple_choice_tests']['2026-10-20'] = ['work_id' => $context->work->id, 'test_id' => $context->test->id];
    $context->work->update(['status' => $status]);
    $context->payload = ['mc_mode' => 'existing', 'test_id' => $context->test->id, 'expected_test_id' => $context->test->id,
        'mc_points' => 2, 'other_points' => 3, 'expected_maximum_points' => 5,
        'request_id' => 'b10ca8e3-181f-48b7-b842-01a16c855ae8', 'date' => '2026-10-20'];
}

test('bound module test is the only eligible test for this exercise and its export', function (): void {
    workMcBoundModuleFixture($this);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->andReturn(['status' => 'found', 'folder' => ['id' => 'id:legacy'],
        'preparation' => null, 'email_dispatch' => ['status' => 'missing']]);
    $this->getJson($this->base.'/exercise-options?date=2026-10-20')->assertOk()
        ->assertJsonPath('eligible_test.id', $this->test->id);
    $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertOk()
        ->assertJsonPath('data.context.multiple_choice_test.test_id', $this->test->id);
    $this->getJson($this->base.'/ai-export?purpose=exercise&include_mc=1&date=2026-10-20')->assertOk()
        ->assertJsonPath('data.context.include_multiple_choice', false)->assertJsonMissingPath('data.context.multiple_choice_test');
    expect(McTest::query()->count())->toBe(1);
    Http::assertNothingSent();
});

test('MC exercise copy keeps the verified template and MC points separate from the work type scale', function (): void {
    workMcBoundModuleFixture($this);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->andReturn(['status' => 'found', 'folder' => ['id' => 'id:legacy'],
        'preparation' => null, 'email_dispatch' => ['status' => 'missing']]);
    $this->mock(McModuleTemplateSource::class)->shouldReceive('assessmentPoints')->andReturn(35.0);
    $payload = [...$this->payload, 'mc_points' => 2, 'other_points' => 35];
    $data = $this->postJson($this->base.'/prepare-exercise', $payload)->assertOk()->json('data');
    expect($data['text'])->toContain('create-exercise', 'Multiple-Choice-Fragen einbeziehen: Ja.', 'Keinen zweiten MC-Test erstellen');
    preg_match('/```json\n(.*?)\n```/s', $data['text'], $match);
    $handoff = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
    expect($handoff['include_multiple_choice'])->toBeTrue()
        ->and($handoff['multiple_choice_test']['points_plan']['mc_points'])->toEqual(2)
        ->and($handoff['multiple_choice_test']['points_plan']['other_points'])->toEqual(35)
        ->and($handoff['multiple_choice_test']['points_plan']['maximum_points'])->toEqual(37);
});

test('MC exercise copy identifies an explicitly chosen template total separately from the standard', function (): void {
    workMcBoundModuleFixture($this);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->andReturn(['status' => 'found', 'folder' => ['id' => 'id:legacy'],
        'preparation' => null, 'email_dispatch' => ['status' => 'missing']]);
    $this->mock(McModuleTemplateSource::class)->shouldReceive('assessmentPoints')->andReturn(35.0);
    $data = $this->postJson($this->base.'/prepare-exercise', [...$this->payload, 'mc_points' => 2, 'other_points' => 20])
        ->assertOk()->json('data');
    expect($data['text'])->toContain('create-exercise', 'Multiple-Choice-Fragen einbeziehen: Ja.');
    preg_match('/```json\n(.*?)\n```/s', $data['text'], $match);
    $handoff = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
    expect($handoff['multiple_choice_test']['points_plan']['other_points'])->toEqual(20)
        ->and($handoff['multiple_choice_test']['points_plan']['maximum_points'])->toEqual(22);
});

test('a changed template total prevents MC preparation before modifying the work', function (): void {
    workMcBoundModuleFixture($this);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->andReturn(['status' => 'found', 'folder' => ['id' => 'id:legacy'],
        'preparation' => null, 'email_dispatch' => ['status' => 'missing']]);
    $this->mock(McModuleTemplateSource::class)->shouldReceive('assessmentPoints')->andReturn(30.0);
    $before = $this->work->fresh()->status;
    $this->postJson($this->base.'/prepare-exercise', [...$this->payload, 'other_points' => 35, 'template_points_snapshot' => 35])->assertConflict();
    expect($this->work->fresh()->status)->toBe($before)->and($this->test->fresh()->snapshot)->toBeNull();
});

test('a confirmed module catalog creates one test only when requested and resumes safely', function (): void {
    workMcBoundModuleFixture($this);
    $this->work->update(['status' => ['automation_enabled' => true]]);
    $source = ['fingerprint' => str_repeat('a', 64), 'questions' => $this->catalog->questions,
        'source' => ['module_id' => 'a263b873-4f27-4167-90f0-7d94ea0f67fa', 'module_folder_id' => 'id:module',
            'source_file_id' => 'id:answers', 'source_revision' => 'r1']];
    $this->mock(McModuleTemplateSource::class)->shouldReceive('workCatalog')->andReturn($source);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->andReturn(['status' => 'found', 'folder' => ['id' => 'id:legacy'],
        'preparation' => null, 'email_dispatch' => ['status' => 'missing']]);
    $payload = ['mc_mode' => 'module', 'catalog_fingerprint' => $source['fingerprint'], 'question_count' => 10,
        'duration_minutes' => 7, 'mc_points' => 2, 'other_points' => 35, 'expected_maximum_points' => 5,
        'expected_test_id' => null, 'request_id' => 'ea7f8626-a38e-4f8a-8c8c-892eb9361fa5', 'date' => '2026-10-20'];
    $this->postJson($this->base.'/prepare-exercise', [...$payload, 'catalog_fingerprint' => str_repeat('b', 64)])->assertConflict();
    expect(McTest::query()->count())->toBe(1)->and(McCatalog::query()->count())->toBe(1);
    $id = $this->postJson($this->base.'/prepare-exercise', $payload)->assertOk()->json('data.context.multiple_choice_test.test_id');
    $this->postJson($this->base.'/prepare-exercise', $payload)->assertOk()->assertJsonPath('data.context.multiple_choice_test.test_id', $id);
    $test = McTest::query()->findOrFail($id);
    expect(McTest::query()->count())->toBe(2)->and(McCatalog::query()->count())->toBe(2)
        ->and($test->question_count)->toBe(10)->and((float) $test->total_points)->toBe(2.0)
        ->and($test->duration_seconds)->toBe(420)->and($test->catalog->module_sources[0]['source_file_id'])->toBe('id:answers');
    Mail::assertNothingSent();
});

test('new and foreign tests are rejected and do not modify the work or tests', function (): void {
    workMcBoundModuleFixture($this);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->andReturn(['status' => 'found', 'folder' => ['id' => 'id:legacy'],
        'preparation' => null, 'email_dispatch' => ['status' => 'missing']]);
    $other = $this->test->replicate();
    $other->title = 'Fremder Test';
    $other->save();
    $before = $this->work->fresh()->status;
    $this->postJson($this->base.'/prepare-exercise', [...$this->payload, 'test_id' => $other->id])->assertConflict();
    $this->postJson($this->base.'/prepare-exercise', [...$this->payload, 'mc_mode' => 'new', 'catalog_id' => $this->catalog->id,
        'duration_minutes' => 15, 'total_points' => 5])->assertUnprocessable()->assertJsonValidationErrors('mc_mode');
    expect(McTest::query()->count())->toBe(2)->and($this->work->fresh()->status)->toBe($before)
        ->and($this->test->fresh()->snapshot)->toBeNull();
    Http::assertNothingSent();
});

test('a missing or different module source never becomes eligible', function (string $condition): void {
    workMcBoundModuleFixture($this);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->andReturn(['status' => 'found', 'folder' => ['id' => 'id:legacy'],
        'preparation' => null, 'email_dispatch' => ['status' => 'missing']]);
    if ($condition === 'different') {
        $this->catalog->update(['module_sources' => [['module_id' => 'a263b873-4f27-4167-90f0-7d94ea0f67fa',
            'module_folder_id' => 'id:other', 'source_file_id' => 'id:questions']]]);
    } else {
        $this->work->update(['status' => ['automation_enabled' => true]]);
    }
    $before = $this->work->fresh()->status;
    $this->getJson($this->base.'/exercise-options?date=2026-10-20')->assertOk()->assertJsonPath('eligible_test', null);
    $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertConflict();
    $this->getJson($this->base.'/ai-export?purpose=exercise&include_mc=1&date=2026-10-20')->assertOk()
        ->assertJsonPath('data.context.include_multiple_choice', false);
    $this->getJson($this->base.'/ai-export?purpose=exercise&include_mc=0&date=2026-10-20')->assertOk();
    expect($this->work->fresh()->status)->toBe($before)->and($this->test->fresh()->snapshot)->toBeNull();
})->with(['different', 'missing']);
