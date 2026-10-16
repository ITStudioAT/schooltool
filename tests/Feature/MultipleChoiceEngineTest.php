<?php

use App\Models\McAttempt;
use App\Models\McCatalog;
use App\Models\McTest;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\MultipleChoice\McTestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Symfony\Component\Process\Process;

require_once dirname(__DIR__).'/Support/MultipleChoiceTestDatabase.php';
requireMultipleChoiceTestDatabase();

uses(RefreshDatabase::class);

/** @return array{McTest, User, McTestService} */
function mcEngineFixture(array $settings = [], int $questionCount = 3): array
{
    $school = School::factory()->create();
    $year = Schoolyear::factory()->create(['school_id' => $school->id]);
    $teacher = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id]);
    $student = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id, 'is_active' => true]);
    Role::findOrCreate('student', 'web');
    $student->assignRole('student');
    $questions = [];
    for ($index = 1; $index <= $questionCount; $index++) {
        $questions[] = [
            'id' => "q{$index}", 'prompt' => "Frage {$index}", 'type' => $index % 3 === 0 ? 'multiple' : 'single',
            'options' => [['id' => 'a', 'text' => 'Erste Antwort'], ['id' => 'b', 'text' => 'Zweite Antwort'], ['id' => 'c', 'text' => 'Dritte Antwort']],
            'correct_option_ids' => $index % 3 === 0 ? ['a', 'b'] : ['a'], 'active' => true, 'reviewed' => true,
        ];
    }
    $catalog = McCatalog::query()->create(['school_id' => $school->id, 'owner_id' => $teacher->id, 'title' => 'Katalog', 'questions' => $questions]);
    $test = McTest::query()->create(array_merge([
        'school_id' => $school->id, 'owner_id' => $teacher->id, 'catalog_id' => $catalog->id,
        'title' => 'Klassenstart', 'question_count' => 3, 'total_points' => '10.00', 'duration_seconds' => 600,
        'lead_seconds' => 30, 'balance_types' => true, 'shuffle_questions' => true, 'shuffle_options' => true,
    ], $settings));

    return [$test, $student, app(McTestService::class)];
}

test('publication assigns a fixed balanced subset and keeps catalog changes out of existing attempts', function () {
    [$test, $student, $service] = mcEngineFixture(['question_count' => 6], 12);
    $published = $service->publish($test, [$student->id, $student->id]);
    $attempt = $published->attempts()->sole();
    $assigned = $attempt->questions;

    $test->catalog->update(['questions' => []]);
    $service->publish($test, [$student->id]);
    $service->openAttempt($attempt);

    expect($attempt->fresh()->questions)->toBe($assigned)->toHaveCount(6);
    expect(array_unique(array_column($assigned, 'id')))->toHaveCount(6);
    expect(array_filter($assigned, fn (array $question): bool => $question['type'] === 'multiple'))->toHaveCount(2);
    expect($published->attempts()->count())->toBe(1);
    expect($published->snapshot['questions'])->toHaveCount(12);
    expect($attempt->fresh()->ready_at)->not->toBeNull();
    expect($attempt->fresh()->started_at)->toBeNull();
});

test('tokens and solution snapshots are hidden and stored tokens are encrypted', function () {
    [$test, $student, $service] = mcEngineFixture();
    $published = $service->publish($test, [$student->id]);
    $attempt = $published->attempts()->sole();

    expect($attempt->toArray())->not->toHaveKeys(['access_token', 'access_token_hash', 'questions']);
    expect($published->toArray())->not->toHaveKey('snapshot');
    expect(strlen($attempt->access_token))->toBe(64);
    expect(hash('sha256', $attempt->access_token))->toBe($attempt->access_token_hash);
    expect(DB::table('mc_attempts')->where('id', $attempt->id)->value('access_token'))->not->toBe($attempt->access_token);
});

test('publication accepts legacy accounts that existing student login treats as active', function () {
    [$test, $student, $service] = mcEngineFixture();
    $student->forceFill(['is_active' => null])->save();

    expect($service->publish($test, [$student->id])->attempts()->count())->toBe(1);
});

test('invalid active reviewed questions cannot be published', function (string $defect) {
    [$test, $student, $service] = mcEngineFixture();
    $questions = $test->catalog->questions;
    if ($defect === 'missing solution') {
        $questions[0]['correct_option_ids'] = [];
    } elseif ($defect === 'unknown solution') {
        $questions[0]['correct_option_ids'] = ['other'];
    } elseif ($defect === 'single with two solutions') {
        $questions[0]['correct_option_ids'] = ['a', 'b'];
    } else {
        $questions[0]['options'][1]['id'] = 'a';
    }
    $test->catalog->update(['questions' => $questions]);

    expect(fn () => $service->publish($test, [$student->id]))->toThrow(ValidationException::class);
    expect($test->fresh()->status)->toBe('draft');
    expect(McAttempt::query()->count())->toBe(0);
})->with(['missing solution', 'unknown solution', 'single with two solutions', 'duplicate option']);

test('draft or archived questions do not enter a published test', function () {
    [$test, $student, $service] = mcEngineFixture([], 6);
    $questions = $test->catalog->questions;
    $questions[0]['active'] = false;
    $questions[1]['reviewed'] = false;
    $test->catalog->update(['questions' => $questions]);

    $published = $service->publish($test, [$student->id]);

    expect(array_column($published->snapshot['questions'], 'id'))->toBe(['q3', 'q4', 'q5', 'q6']);
    expect(array_column($published->attempts()->sole()->questions, 'id'))->not->toContain('q1', 'q2');
});

test('publication rejects accounts outside its school and nonstudents and inactive students', function (string $invalid) {
    [$test, $student, $service] = mcEngineFixture();
    if ($invalid === 'other school') {
        $student->school_id = School::factory()->create()->id;
    } elseif ($invalid === 'inactive') {
        $student->is_active = false;
    } else {
        $student->removeRole('student');
    }
    $student->save();

    expect(fn () => $service->publish($test, [$student->id]))->toThrow(ValidationException::class);
    expect(McAttempt::query()->count())->toBe(0);
})->with(['other school', 'nonstudent', 'inactive']);

test('server countdown is shared idempotent and cannot be reset by repeated start', function () {
    $this->freezeSecond();
    [$test, $student, $service] = mcEngineFixture();
    $published = $service->publish($test, [$student->id]);
    $attempt = $published->attempts()->sole();
    $scheduled = $service->startCountdown($published);
    $start = $scheduled->starts_at;
    $end = $scheduled->ends_at;

    expect($start->equalTo(now()->addSeconds(30)))->toBeTrue();
    expect($end->diffInSeconds($start, true))->toBe(600.0);
    expect(fn () => $service->saveAnswers($attempt, ['q1' => ['a']]))->toThrow(ValidationException::class);
    expect(fn () => $service->submit($attempt))->toThrow(ValidationException::class);
    $this->travel(45)->seconds();
    $again = $service->startCountdown($test);
    $opened = $service->openAttempt($attempt);

    expect($again->starts_at->equalTo($start))->toBeTrue();
    expect($again->ends_at->equalTo($end))->toBeTrue();
    expect($opened->started_at->equalTo(now()))->toBeTrue();
});

test('autosaves merge changed questions and allow clearing while enforcing option identity and selection type', function () {
    $this->freezeSecond();
    [$test, $student, $service] = mcEngineFixture(['lead_seconds' => 0]);
    $published = $service->publish($test, [$student->id]);
    $service->startCountdown($published);
    $attempt = $published->attempts()->sole();

    $service->saveAnswers($attempt, ['q1' => ['a']]);
    $service->saveAnswers($attempt, ['q2' => ['b']]);
    $service->saveAnswers($attempt, ['q1' => []]);

    expect($attempt->fresh()->answers)->toBe(['q1' => [], 'q2' => ['b']]);
    foreach ([['q1' => ['a', 'b']], ['q3' => ['a', 'a']], ['q1' => ['wrong']], ['foreign' => ['a']], ['q3' => 'a']] as $invalid) {
        expect(fn () => $service->saveAnswers($attempt, $invalid))->toThrow(ValidationException::class);
    }
    expect($attempt->fresh()->answers)->toBe(['q1' => [], 'q2' => ['b']]);
});

test('exact selection scores equal question weights and rounds only the final sum', function (array $answers, string $score, int $correct) {
    $this->freezeSecond();
    [$test, $student, $service] = mcEngineFixture(['lead_seconds' => 0]);
    $published = $service->publish($test, [$student->id]);
    $service->startCountdown($published);
    $attempt = $published->attempts()->sole();
    $service->saveAnswers($attempt, $answers);

    $completed = $service->submit($attempt);

    expect($completed->score)->toBe($score);
    expect($completed->correct_count)->toBe($correct);
    expect($completed->completion_reason)->toBe('submitted');
    expect($completed->started_at)->not->toBeNull();
})->with([
    'two of three' => [['q1' => ['a'], 'q2' => ['a'], 'q3' => ['a']], '6.67', 2],
    'all correct unordered multiple' => [['q1' => ['a'], 'q2' => ['a'], 'q3' => ['b', 'a']], '10.00', 3],
    'extra wrong multiple zero' => [['q3' => ['a', 'b', 'c']], '0.00', 0],
    'one of three' => [['q3' => ['a', 'b']], '3.33', 1],
    'no answers submitted' => [[], '0.00', 0],
]);

test('expired attempts close without client calls and preserve never started status', function () {
    $this->freezeSecond();
    [$test, $student, $service] = mcEngineFixture(['lead_seconds' => 0]);
    $published = $service->publish($test, [$student->id]);
    $scheduled = $service->startCountdown($published);
    $attempt = $published->attempts()->sole();
    $this->travelTo($scheduled->ends_at);

    expect($service->finalizeExpired())->toBe(1);
    expect($service->finalizeExpired())->toBe(0);
    $completed = $attempt->fresh();
    expect($completed->completion_reason)->toBe('timeout');
    expect($completed->completed_at->equalTo($scheduled->ends_at))->toBeTrue();
    expect($completed->started_at)->toBeNull();
    expect($completed->score)->toBeNull();
    expect(fn () => $service->saveAnswers($attempt, ['q1' => ['a']]))->toThrow(ValidationException::class);
});

test('deadline rejects new answers before delayed collector and late submit finalizes the persisted answers once', function () {
    $this->freezeSecond();
    [$test, $student, $service] = mcEngineFixture(['lead_seconds' => 0]);
    $published = $service->publish($test, [$student->id]);
    $scheduled = $service->startCountdown($published);
    $attempt = $published->attempts()->sole();
    $service->saveAnswers($attempt, ['q1' => ['a']]);
    $this->travelTo($scheduled->ends_at);

    expect(fn () => $service->saveAnswers($attempt, ['q2' => ['a']]))->toThrow(ValidationException::class);
    $completed = $service->submit($attempt);
    $again = $service->submit($attempt);

    expect($completed->score)->toBe('3.33');
    expect($completed->completion_reason)->toBe('timeout');
    expect($completed->submitted_at)->toBeNull();
    expect($again->toArray())->toBe($completed->toArray());
    expect($service->finalizeExpired($test->id))->toBe(0);
});

test('stale device saves cannot change a submitted attempt or immutable publication', function () {
    $this->freezeSecond();
    [$test, $student, $service] = mcEngineFixture(['lead_seconds' => 0]);
    $published = $service->publish($test, [$student->id]);
    $service->startCountdown($published);
    $stale = $published->attempts()->sole();
    $service->saveAnswers($stale, ['q1' => ['a']]);
    $completed = $service->submit($stale);

    expect(fn () => $service->saveAnswers($stale, ['q2' => ['a']]))->toThrow(ValidationException::class);
    expect(fn () => $published->update(['total_points' => 20]))->toThrow(ValidationException::class);
    expect(fn () => $stale->update(['questions' => []]))->toThrow(ValidationException::class);
    expect($completed->fresh()->score)->toBe('3.33');
});

test('concurrent device saves merge and concurrent submissions produce one immutable outcome', function () {
    $this->freezeSecond();
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('This row-lock concurrency regression requires the isolated MySQL test workflow.');
    }
    $initialCounts = [McCatalog::query()->count(), McTest::query()->count(), McAttempt::query()->count()];
    $existingStudentRole = Role::query()->where('name', 'student')->where('guard_name', 'web')->value('id');
    [$test, $student, $service] = mcEngineFixture(['lead_seconds' => 0]);
    $published = $service->publish($test, [$student->id]);
    $service->startCountdown($published);
    $attempt = $published->attempts()->sole();
    $createdRole = $existingStudentRole === null ? $student->roles()->where('name', 'student')->value('roles.id') : null;
    $userIds = [(int) $test->owner_id, (int) $student->id];
    $schoolId = (int) $test->school_id;
    $schoolyearId = (int) $student->schoolyear_id;
    $catalogId = (int) $test->catalog_id;
    $connection = DB::connection();
    $connection->commit();
    $workerCode = <<<'PHP'
require getcwd().'/vendor/autoload.php';
require getcwd().'/tests/Support/MultipleChoiceTestDatabase.php';
requireMultipleChoiceTestDatabase();
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\Date::setTestNow(getenv('SCHOOLTOOL_MC_TEST_NOW'));
$service = $app->make(App\Services\MultipleChoice\McTestService::class);
$attempt = App\Models\McAttempt::query()->findOrFail((int) $argv[1]);
if ($argv[2] === 'save') {
    $service->saveAnswers($attempt, json_decode($argv[3], true, flags: JSON_THROW_ON_ERROR));
} else {
    $service->submit($attempt);
}
PHP;
    $environment = [
        'APP_ENV' => 'testing', 'APP_KEY' => config('app.key'),
        'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $connection->getDatabaseName(),
        'DB_DATABASE_TEST' => $connection->getDatabaseName(),
        'DB_HOST' => config('database.connections.mysql.host'),
        'DB_PORT' => (string) config('database.connections.mysql.port'),
        'DB_USERNAME' => config('database.connections.mysql.username'),
        'DB_PASSWORD' => config('database.connections.mysql.password'),
        'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
        'SCHOOLTOOL_MC_TEST_RECEIPT' => (string) ($_SERVER['SCHOOLTOOL_MC_TEST_RECEIPT'] ?? $_ENV['SCHOOLTOOL_MC_TEST_RECEIPT'] ?? getenv('SCHOOLTOOL_MC_TEST_RECEIPT')),
        'SCHOOLTOOL_MC_TEST_NOW' => now()->toIso8601String(),
        'APP_CONFIG_CACHE' => 'bootstrap/cache/mc-concurrency-'.bin2hex(random_bytes(6)).'.config.php',
        'TEST_TOKEN' => 'mc-core-concurrency',
    ];
    $workers = [];
    try {
        foreach ([['q1' => ['a']], ['q2' => ['a']]] as $answers) {
            $worker = new Process([PHP_BINARY, '-r', $workerCode, (string) $attempt->id, 'save', json_encode($answers, JSON_THROW_ON_ERROR)], base_path(), $environment);
            $worker->setTimeout(15)->start();
            $workers[] = $worker;
        }
        foreach ($workers as $worker) {
            $exitCode = $worker->wait();
            $this->assertSame(0, $exitCode, $worker->getErrorOutput().$worker->getOutput());
        }
        $saved = $attempt->fresh()->answers;
        ksort($saved);
        expect($saved)->toBe(['q1' => ['a'], 'q2' => ['a']]);

        $workers = [];
        foreach (range(1, 2) as $device) {
            $worker = new Process([PHP_BINARY, '-r', $workerCode, (string) $attempt->id, 'submit'], base_path(), $environment);
            $worker->setTimeout(15)->start();
            $workers[] = $worker;
        }
        foreach ($workers as $worker) {
            $exitCode = $worker->wait();
            $this->assertSame(0, $exitCode, $worker->getErrorOutput().$worker->getOutput());
        }
        expect($attempt->fresh()->score)->toBe('6.67');
        expect($attempt->fresh()->completion_reason)->toBe('submitted');
        expect(fn () => $service->saveAnswers($attempt, ['q3' => ['a', 'b']]))->toThrow(ValidationException::class);
    } finally {
        foreach ($workers as $worker) {
            if ($worker->isRunning()) {
                $worker->stop();
            }
        }
        try {
            DB::transaction(function () use ($test, $catalogId, $userIds, $schoolyearId, $schoolId, $createdRole): void {
                McAttempt::query()->where('test_id', $test->id)->delete();
                McTest::query()->whereKey($test->id)->delete();
                McCatalog::query()->whereKey($catalogId)->delete();
                DB::table('model_has_roles')->where('model_type', User::class)->whereIn('model_id', $userIds)->delete();
                User::query()->whereIn('id', $userIds)->delete();
                Schoolyear::query()->whereKey($schoolyearId)->delete();
                School::query()->whereKey($schoolId)->delete();
                if ($createdRole !== null) {
                    Role::query()->find($createdRole)?->delete();
                }
            });
            expect([McCatalog::query()->count(), McTest::query()->count(), McAttempt::query()->count()])->toBe($initialCounts);
        } finally {
            $connection->beginTransaction();
        }
    }
});
