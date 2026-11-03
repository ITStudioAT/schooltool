<?php

use App\Jobs\FinalizeMultipleChoiceTest;
use App\Models\McAttempt;
use App\Models\McCatalog;
use App\Models\McTest;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\MultipleChoice\McAttemptActivityService;
use App\Services\MultipleChoice\McTestService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
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

test('individual reopening preserves another submitted attempt and keeps the prior grade and answers in private history', function (): void {
    $this->freezeSecond();
    [$test, $student, $service] = mcEngineFixture(['lead_seconds' => 0, 'duration_seconds' => 60]);
    $other = User::factory()->create(['school_id' => $student->school_id, 'schoolyear_id' => $student->schoolyear_id, 'is_active' => true]);
    $other->assignRole('student');
    $test = $service->publish($test, [$student->id, $other->id]);
    $test = $service->startCountdown($test);
    $first = $test->attempts()->where('student_id', $student->id)->firstOrFail();
    $second = $test->attempts()->where('student_id', $other->id)->firstOrFail();
    $service->saveAnswers($first, ['q1' => ['a']]);
    $service->submit($first);
    $service->saveAnswers($second, ['q2' => ['a']]);
    $service->submit($second);
    $before = $first->fresh();
    $otherBefore = $second->fresh()->getAttributes();
    $this->travel(61)->seconds();
    $test->update(['results_released_at' => now(), 'solutions_released_at' => now()]);
    $testBefore = $test->fresh()->getAttributes();
    $reopened = $service->reopenAttempt($first, 120, (int) $test->owner_id);
    expect($reopened->completed_at)->toBeNull();
    expect($reopened->score)->toBeNull();
    expect($reopened->answers)->toBe($before->answers);
    expect($reopened->questions)->toBe($before->questions);
    expect($reopened->access_token)->toBe($before->access_token);
    expect($reopened->started_at->equalTo($before->started_at))->toBeTrue();
    expect($reopened->reopened_until->equalTo(now()->addSeconds(120)))->toBeTrue();
    expect($reopened->reopen_history[0]['previous']['score'])->toBe('3.33');
    expect($reopened->reopen_history[0]['previous']['answers'])->toBe($before->answers);
    expect($reopened->toArray())->not->toHaveKey('reopen_history');
    expect($second->fresh()->getAttributes())->toBe($otherBefore);
    expect($test->fresh()->getAttributes())->toBe($testBefore);
    expect($service->finalizeExpired($test->id))->toBe(0);
    $service->saveAnswers($reopened, ['q2' => ['a']]);
    expect($service->submit($reopened)->score)->toBe('6.67');
    expect($second->fresh()->getAttributes())->toBe($otherBefore);
});

test('individual deadlines finish independently and can be changed without extending another person', function (): void {
    $this->freezeSecond();
    [$test, $student, $service] = mcEngineFixture(['lead_seconds' => 0, 'duration_seconds' => 60]);
    $other = User::factory()->create(['school_id' => $student->school_id, 'schoolyear_id' => $student->schoolyear_id, 'is_active' => true]);
    $other->assignRole('student');
    $test = $service->startCountdown($service->publish($test, [$student->id, $other->id]));
    $first = $test->attempts()->where('student_id', $student->id)->firstOrFail();
    $second = $test->attempts()->where('student_id', $other->id)->firstOrFail();
    $service->saveAnswers($first, ['q1' => ['a']]);
    $service->saveAnswers($second, ['q1' => ['a']]);
    $this->travel(61)->seconds();
    $first = $service->reopenAttempt($first, 120, (int) $test->owner_id);
    expect($first->reopen_history[0]['previous']['completion_reason'])->toBe('timeout');
    expect($first->reopen_history[0]['previous']['score'])->toBe('3.33');
    $firstEnd = $first->reopened_until;
    $second = $service->reopenAttempt($second, 240, (int) $test->owner_id);
    $second = $service->reopenAttempt($second, 180, (int) $test->owner_id);
    expect($second->reopen_history)->toHaveCount(2);
    expect($first->fresh()->reopened_until->equalTo($firstEnd))->toBeTrue();
    $this->travel(121)->seconds();
    expect($service->finalizeExpired($test->id))->toBe(1);
    expect($first->fresh()->completed_at->equalTo($firstEnd))->toBeTrue();
    expect($second->fresh()->completed_at)->toBeNull();
    $service->saveAnswers($second, ['q2' => ['a']]);
    $service->closeEarly($test);
    expect($second->fresh()->completed_at->equalTo(now()))->toBeTrue();
    expect($second->fresh()->score)->toBe('6.67');
});

test('individual reopening requires an ended published test, its owner and a bounded duration', function (): void {
    $this->freezeSecond();
    [$test, $student, $service] = mcEngineFixture(['lead_seconds' => 0, 'duration_seconds' => 60]);
    $test = $service->startCountdown($service->publish($test, [$student->id]));
    $attempt = $test->attempts()->sole();
    expect(fn () => $service->reopenAttempt($attempt, 120, (int) $test->owner_id))->toThrow(ValidationException::class);
    $this->travel(61)->seconds();
    foreach ([0, -1, 86401] as $seconds) {
        expect(fn () => $service->reopenAttempt($attempt, $seconds, (int) $test->owner_id))->toThrow(ValidationException::class);
    }
    expect(fn () => $service->reopenAttempt($attempt, 120, (int) $student->id))->toThrow(ValidationException::class);
    expect($attempt->fresh()->reopened_until)->toBeNull();
    expect($attempt->fresh()->reopen_history)->toBeNull();
});

test('individual start is explicit once and resuming never resets a running personal deadline', function () {
    $this->freezeSecond();
    [$test, $student, $service] = mcEngineFixture(['start_mode' => 'individual', 'closes_at' => now()->addHour()]);
    $test = $service->publish($test, [$student->id]);
    $attempt = $test->attempts()->sole();
    $service->openAttempt($attempt);
    expect($attempt->fresh()->started_at)->toBeNull();
    expect(fn () => $service->saveAnswers($attempt, ['q1' => ['a']]))->toThrow(ValidationException::class);
    expect(fn () => $service->startCountdown($test))->toThrow(ValidationException::class);
    $started = $service->startIndividual($attempt);
    $deadline = $started->effectiveDeadline($test);
    $service->saveAnswers($attempt, ['q1' => ['a']]);
    $this->travel(300)->seconds();
    expect($service->startIndividual($attempt)->effectiveDeadline($test)->equalTo($deadline))->toBeTrue();
    expect($service->openAttempt($attempt)->answers)->toBe(['q1' => ['a']]);
    $this->travelTo($deadline);
    expect(fn () => $service->saveAnswers($attempt, ['q1' => ['b']]))->toThrow(ValidationException::class);
    expect($service->finalizeExpired($test->id))->toBe(1);
    $done = $service->startIndividual($attempt);
    expect($done->completion_reason)->toBe('timeout');
    expect($done->answers)->toBe(['q1' => ['a']]);
    expect($done->completed_at->equalTo($deadline))->toBeTrue();
});

test('individual attempts keep independent starts and respect the hard closing deadline', function () {
    $this->freezeSecond();
    [$test, $student, $service] = mcEngineFixture(['start_mode' => 'individual', 'closes_at' => now()->addMinutes(15)]);
    $other = User::factory()->create(['school_id' => $student->school_id, 'schoolyear_id' => $student->schoolyear_id, 'is_active' => true]);
    $other->assignRole('student');
    $test = $service->publish($test, [$student->id, $other->id]);
    [$first, $second] = $test->attempts()->orderBy('id')->get()->all();
    $first = $service->startIndividual($first);
    $this->travel(8)->minutes();
    $second = $service->startIndividual($second);
    expect($second->effectiveDeadline($test)->equalTo($test->closes_at))->toBeTrue();
    $this->travelTo($first->effectiveDeadline($test));
    expect($service->finalizeExpired($test->id))->toBe(1);
    expect($second->fresh()->completed_at)->toBeNull();
    $this->travelTo($test->closes_at);
    (new FinalizeMultipleChoiceTest($test->id))->handle($service);
    expect($second->fresh()->completion_reason)->toBe('timeout');
});

test('personal closing exceptions leave other students and already elapsed duration unchanged', function () {
    $this->freezeSecond();
    [$test, $student, $service] = mcEngineFixture(['start_mode' => 'individual', 'closes_at' => now()->addMinutes(5)]);
    $test = $service->publish($test, [$student->id]);
    $attempt = $service->startIndividual($test->attempts()->sole());
    $originalStart = $attempt->started_at;
    $service->changeClosingDeadline($attempt, now()->addHour()->toIso8601String());
    expect($test->fresh()->closes_at->equalTo($test->closes_at))->toBeTrue();
    expect($attempt->fresh()->effectiveDeadline($test)->equalTo($originalStart->addSeconds(600)))->toBeTrue();
    $this->travel(10)->minutes();
    $service->finalizeExpired($test->id);
    $service->changeClosingDeadline($attempt, now()->addHour()->toIso8601String());
    expect($attempt->fresh()->completed_at)->not->toBeNull();
    $reopened = $service->reopenAttempt($attempt, 120, $test->owner_id);
    expect($reopened->started_at->equalTo($originalStart))->toBeTrue();
    expect($reopened->effectiveDeadline($test)->equalTo(now()->addSeconds(120)))->toBeTrue();
});

test('personal closing exceptions preserve their instant with a UTC database session', function (string $closing, string $expected): void {
    $this->travelTo(CarbonImmutable::parse('2026-01-15T08:00:00+01:00'));
    $timezone = DB::selectOne('SELECT @@session.time_zone AS timezone')->timezone;

    try {
        DB::statement("SET SESSION time_zone = '+00:00'");
        [$test, $student, $service] = mcEngineFixture(['start_mode' => 'individual', 'closes_at' => now()->addHour()]);
        $test = $service->publish($test, [$student->id]);
        $attempt = $test->attempts()->sole();
        $changed = $service->changeClosingDeadline($attempt, $closing);

        expect($changed->closes_at->format('Y-m-d H:i:s'))->toBe($expected);
        expect($changed->closes_at->equalTo($closing))->toBeTrue();
        expect($changed->completed_at)->toBeNull();
        expect($test->fresh()->closes_at->equalTo($test->closes_at))->toBeTrue();
    } finally {
        DB::statement('SET SESSION time_zone = ?', [$timezone]);
    }
})->with([
    'Vienna winter' => ['2026-01-15T11:00:00+01:00', '2026-01-15 11:00:00'],
    'UTC winter' => ['2026-01-15T10:00:00Z', '2026-01-15 11:00:00'],
    'Vienna summer' => ['2026-07-15T11:00:00+02:00', '2026-07-15 11:00:00'],
    'UTC summer' => ['2026-07-15T09:00:00Z', '2026-07-15 11:00:00'],
]);

test('unstarted individual assignments expire at closing and a teacher exception can allow their first start', function () {
    $this->freezeSecond();
    [$test, $student, $service] = mcEngineFixture(['start_mode' => 'individual', 'closes_at' => now()->addMinute()]);
    $test = $service->publish($test, [$student->id]);
    $attempt = $test->attempts()->sole();
    $this->travel(60)->seconds();
    expect($service->startIndividual($attempt)->completion_reason)->toBe('timeout');
    expect($attempt->fresh()->started_at)->toBeNull();
    expect($attempt->fresh()->score)->toBeNull();
    $service->changeClosingDeadline($attempt, now()->addHour()->toIso8601String());
    expect($attempt->fresh()->completed_at)->toBeNull();
    expect($service->startIndividual($attempt)->started_at)->not->toBeNull();
});

test('teacher pause freezes common attempts and resumes exactly once with preserved answers and remaining time', function () {
    $this->freezeSecond();
    [$test, $student, $service] = mcEngineFixture(['lead_seconds' => 0]);
    $test = $service->startCountdown($service->publish($test, [$student->id]));
    $attempt = $service->openAttempt($test->attempts()->sole());
    $service->saveAnswers($attempt, ['q1' => ['a']]);
    $this->travel(30)->seconds();
    $paused = $service->pause($test, $test->owner_id);
    $service->pause($test, $test->owner_id);
    $this->travel(1200)->seconds();
    expect($service->finalizeExpired($test->id))->toBe(0);
    expect($service->openAttempt($attempt)->answers)->toBe(['q1' => ['a']]);
    expect(fn () => $service->saveAnswers($attempt, ['q1' => ['b']]))->toThrow(ValidationException::class);
    expect(fn () => $service->submit($attempt))->toThrow(ValidationException::class);
    $resumed = $service->resume($test, $test->owner_id);
    expect($resumed->paused_at)->toBeNull();
    expect($resumed->ends_at->equalTo($test->ends_at->addSeconds(1200)))->toBeTrue();
    expect((int) now()->diffInSeconds($resumed->ends_at))->toBe(570);
    expect($attempt->fresh()->pause_seconds)->toBe(1200);
    expect($service->resume($test, $test->owner_id)->ends_at->equalTo($resumed->ends_at))->toBeTrue();
    expect($attempt->fresh()->answers)->toBe(['q1' => ['a']]);
    expect($resumed->pause_history)->toHaveCount(1);
});

test('individual pause shifts personal timers and hard deadlines without starting unstarted pupils', function () {
    $this->freezeSecond();
    [$test, $student, $service] = mcEngineFixture(['start_mode' => 'individual', 'closes_at' => now()->addHour()]);
    $other = User::factory()->create(['school_id' => $student->school_id, 'schoolyear_id' => $student->schoolyear_id, 'is_active' => true]);
    $other->assignRole('student');
    $test = $service->publish($test, [$student->id, $other->id]);
    [$first, $second] = $test->attempts()->orderBy('id')->get()->all();
    $first = $service->startIndividual($first);
    $service->changeClosingDeadline($first, now()->addHours(2)->toIso8601String());
    $before = $first->fresh()->effectiveDeadline($test);
    $this->travel(30)->seconds();
    $service->pause($test, $test->owner_id);
    $this->travel(120)->seconds();
    expect(fn () => $service->startIndividual($second))->toThrow(ValidationException::class);
    $resumed = $service->resume($test, $test->owner_id);
    expect($first->fresh()->effectiveDeadline($resumed)->equalTo($before->addSeconds(120)))->toBeTrue();
    expect($first->fresh()->closes_at->equalTo($test->closes_at->addHours(1)->addSeconds(120)))->toBeTrue();
    expect($resumed->closes_at->equalTo($test->closes_at->addSeconds(120)))->toBeTrue();
    expect($second->fresh()->started_at)->toBeNull();
    expect($second->fresh()->pause_seconds)->toBe(0);
    expect($service->startIndividual($second)->effectiveDeadline($resumed)->equalTo(now()->addSeconds(600)))->toBeTrue();
});

test('a personal pause blocks only that pupil and credits overlapping global pauses once', function () {
    $this->freezeSecond();
    [$test, $student, $service] = mcEngineFixture(['start_mode' => 'individual', 'closes_at' => now()->addHour()]);
    $other = User::factory()->create(['school_id' => $student->school_id, 'schoolyear_id' => $student->schoolyear_id, 'is_active' => true]);
    $other->assignRole('student');
    $test = $service->publish($test, [$student->id, $other->id]);
    [$first, $second] = $test->attempts()->orderBy('id')->get()->all();
    $first = $service->startIndividual($first);
    $deadline = $first->effectiveDeadline($test);
    $service->controlAttempts($test, [$first->id], 'pause', $test->owner_id);
    expect(fn () => $service->saveAnswers($first, ['q1' => ['a']]))->toThrow(ValidationException::class);
    expect($service->startIndividual($second)->started_at)->not->toBeNull();
    $this->travel(30)->seconds();
    $service->pause($test, $test->owner_id);
    $this->travel(60)->seconds();
    $test = $service->resume($test, $test->owner_id);
    $this->travel(30)->seconds();
    $service->controlAttempts($test, [$first->id], 'resume', $test->owner_id);
    expect($first->fresh()->effectiveDeadline($test)->equalTo($deadline->addSeconds(120)))->toBeTrue();
    expect($first->fresh()->individualPausedAt())->toBeNull();
});

test('reset clears only selected answers and rejects stale attempts', function () {
    $this->freezeSecond();
    [$test, $student, $service] = mcEngineFixture(['start_mode' => 'individual', 'closes_at' => now()->addHour()]);
    $test = $service->publish($test, [$student->id]);
    $attempt = $service->startIndividual($test->attempts()->sole());
    $attempt = $service->saveAnswers($attempt, ['q1' => ['a']]);
    $revision = $attempt->controlRevision();
    $service->controlAttempts($test, [$attempt->id], 'reset', $test->owner_id, [$attempt->id => $revision]);
    $reset = $attempt->fresh();
    expect($reset->answers)->toBe([])->and($reset->started_at)->toBeNull()->and($reset->control()['version'])->toBe(1);
    expect(fn () => $service->saveAnswers($attempt, ['q1' => ['b']], 0))->toThrow(HttpException::class);
    $reset = $service->startIndividual($reset, 1);
    expect($service->saveAnswers($reset, ['q1' => ['b']], 1)->answers)->toBe(['q1' => ['b']]);
});

test('a personal pause before starting prevents only that pupil from starting', function () {
    $this->freezeSecond();
    [$test, $student, $service] = mcEngineFixture(['start_mode' => 'individual', 'closes_at' => now()->addHour()]);
    $test = $service->publish($test, [$student->id]);
    $attempt = $test->attempts()->sole();
    $service->controlAttempts($test, [$attempt->id], 'pause', $test->owner_id);
    expect(fn () => $service->startIndividual($attempt))->toThrow(ValidationException::class);
    $this->travel(120)->seconds();
    $service->controlAttempts($test, [$attempt->id], 'resume', $test->owner_id);
    expect($service->startIndividual($attempt)->started_at)->not->toBeNull();
    expect($attempt->fresh()->closingDeadline($test)->equalTo($test->closes_at->addSeconds(120)))->toBeTrue();
});

test('resetting a common test allows a fresh personal start after the common timer ends', function () {
    $this->freezeSecond();
    [$test, $student, $service] = mcEngineFixture(['lead_seconds' => 0]);
    $test = $service->startCountdown($service->publish($test, [$student->id]));
    $attempt = $service->openAttempt($test->attempts()->sole());
    $service->controlAttempts($test, [$attempt->id], 'reset', $test->owner_id, [$attempt->id => $attempt->controlRevision()]);
    $this->travel(601)->seconds();
    $attempt = $service->openAttempt($attempt->fresh());
    expect($attempt->started_at)->toBeNull();
    $attempt = $service->startIndividual($attempt, 1);
    expect($attempt->effectiveDeadline($test)->equalTo(now()->addSeconds(600)))->toBeTrue();
    $attempt = $service->submit($attempt, 1);
    $reopened = $service->reopenAttempt($attempt, 120, $test->owner_id);
    expect($reopened->effectiveDeadline($test)->equalTo(now()->addSeconds(120)))->toBeTrue();
});

test('reset all requires an unchanged complete selection and clears every selected result', function () {
    $this->freezeSecond();
    [$test, $student, $service] = mcEngineFixture(['start_mode' => 'individual', 'closes_at' => now()->addHour()]);
    $other = User::factory()->create(['school_id' => $student->school_id, 'schoolyear_id' => $student->schoolyear_id, 'is_active' => true]);
    $other->assignRole('student');
    $test = $service->publish($test, [$student->id, $other->id]);
    [$first, $second] = $test->attempts()->orderBy('id')->get()->all();
    $first = $service->startIndividual($first);
    $first = $service->saveAnswers($first, ['q1' => ['a']]);
    $first = $service->submit($first);
    $second = $service->startIndividual($second);
    $second = $service->saveAnswers($second, ['q1' => ['b']]);
    $expected = [$first->id => $first->controlRevision(), $second->id => $second->controlRevision()];
    expect(fn () => $service->controlAttempts($test, [], 'reset', $test->owner_id, [$first->id => $expected[$first->id]], true))
        ->toThrow(ValidationException::class);
    expect($first->fresh()->answers)->toBe(['q1' => ['a']]);
    $service->controlAttempts($test, [], 'reset', $test->owner_id, $expected, true);
    foreach ([$first, $second] as $attempt) {
        expect($attempt->fresh()->answers)->toBe([])->and($attempt->fresh()->completed_at)->toBeNull()
            ->and($attempt->fresh()->started_at)->toBeNull()->and($attempt->fresh()->control()['version'])->toBe(1);
    }
});

test('exam activity merges blur and hidden events and retries without grading or double counting', function () {
    $this->freezeSecond();
    [$test, $student, $service] = mcEngineFixture(['exam_mode' => true, 'lead_seconds' => 0]);
    $test = $service->startCountdown($service->publish($test, [$student->id]));
    $attempt = $service->openAttempt($test->attempts()->sole());
    $activity = app(McAttemptActivityService::class);
    $session = (string) Str::uuid();
    $event = fn (int $sequence, bool $visible, bool $focused, bool $fullscreen): array => compact('sequence', 'visible', 'focused', 'fullscreen') + ['at' => now()->toIso8601String()];
    $activity->record($attempt, $session, [$event(1, true, true, true)]);
    $this->travel(5)->seconds();
    $left = $event(2, true, false, false);
    $activity->record($attempt, $session, [$left, $event(3, false, false, false)]);
    $this->travel(10)->seconds();
    $activity->record($attempt, $session, [$left]);
    $summary = $activity->summary($attempt->fresh(), $test);
    expect($summary['away_count'])->toBe(1);
    expect($summary['away_seconds'])->toBe(10);
    expect($summary['fullscreen_exit_count'])->toBe(1);
    $this->travel(10)->seconds();
    $activity->record($attempt, $session, [$event(4, true, true, false)]);
    $summary = $activity->summary($attempt->fresh(), $test);
    expect($summary['away_count'])->toBe(1);
    expect($summary['away_seconds'])->toBe(20);
    expect($summary['periods'])->toHaveCount(1);
    expect($attempt->fresh()->completed_at)->toBeNull();
    expect($attempt->fresh()->score)->toBeNull();
    expect($attempt->fresh()->answers)->toBe([]);
    expect($attempt->fresh()->toArray())->not->toHaveKey('activity_state');
});

test('another active test window prevents false absence and teacher pauses add no absence duration', function () {
    $this->freezeSecond();
    [$test, $student, $service] = mcEngineFixture(['exam_mode' => true, 'lead_seconds' => 0]);
    $test = $service->startCountdown($service->publish($test, [$student->id]));
    $attempt = $service->openAttempt($test->attempts()->sole());
    $activity = app(McAttemptActivityService::class);
    $first = (string) Str::uuid();
    $second = (string) Str::uuid();
    $event = fn (int $sequence, bool $present): array => ['sequence' => $sequence, 'at' => now()->toIso8601String(), 'visible' => $present, 'focused' => $present, 'fullscreen' => false];
    $activity->record($attempt, $first, [$event(1, true)]);
    $activity->record($attempt, $second, [$event(1, true)]);
    $activity->record($attempt, $first, [$event(2, false)]);
    expect($activity->summary($attempt->fresh(), $test)['away_count'])->toBe(0);
    $activity->record($attempt, $second, [$event(2, false)]);
    $this->travel(10)->seconds();
    $service->pause($test, $test->owner_id);
    $this->travel(60)->seconds();
    expect($activity->summary($attempt->fresh(), $test->fresh())['away_seconds'])->toBe(10);
    $resumed = $service->resume($test, $test->owner_id);
    $activity->record($attempt, $first, [$event(3, true)]);
    expect($activity->summary($attempt->fresh(), $resumed)['away_seconds'])->toBe(10);
});

test('a monitored attempt submits and preserves its final absence log without changing its protected assignment', function () {
    $this->freezeSecond();
    [$test, $student, $service] = mcEngineFixture(['exam_mode' => true, 'lead_seconds' => 0]);
    $test = $service->startCountdown($service->publish($test, [$student->id]));
    $attempt = $service->openAttempt($test->attempts()->sole());
    $questions = $attempt->questions;
    $token = $attempt->access_token_hash;
    $service->saveAnswers($attempt, ['q1' => ['a']]);
    $activity = app(McAttemptActivityService::class);
    $activity->record($attempt, (string) Str::uuid(), [['sequence' => 1, 'at' => now()->toIso8601String(), 'visible' => false, 'focused' => false, 'fullscreen' => false]]);
    $this->travel(12)->seconds();
    $submitted = $service->submit($attempt);
    expect($submitted->completion_reason)->toBe('submitted');
    expect($submitted->answers)->toBe(['q1' => ['a']]);
    expect($submitted->questions)->toBe($questions);
    expect($submitted->access_token_hash)->toBe($token);
    $summary = $activity->summary($submitted, $test);
    expect($summary['away_seconds'])->toBe(12);
    expect($summary['periods'])->toHaveCount(1);
    $submitted->forceFill(['activity_state' => []]);
    expect(fn () => $submitted->save())->toThrow(ValidationException::class);
});

test('expired exam activity stops reporting current absence even before timeout finalization', function () {
    $this->freezeSecond();
    [$test, $student, $service] = mcEngineFixture(['exam_mode' => true, 'lead_seconds' => 0]);
    $test = $service->startCountdown($service->publish($test, [$student->id]));
    $attempt = $service->openAttempt($test->attempts()->sole());
    $activity = app(McAttemptActivityService::class);
    $activity->record($attempt, (string) Str::uuid(), [['sequence' => 1, 'at' => now()->toIso8601String(), 'visible' => false, 'focused' => false, 'fullscreen' => false]]);
    $this->travelTo($test->ends_at->addMinutes(2));
    $summary = $activity->summary($attempt->fresh(), $test);
    expect($attempt->fresh()->completed_at)->toBeNull();
    expect($summary['state'])->toBe('expired');
    expect($summary['away_seconds'])->toBe(600);
    expect($summary['periods'][0]['ended_at'])->toBe($test->ends_at->toIso8601String());
    $this->travel(60)->seconds();
    expect($activity->summary($attempt->fresh(), $test)['away_seconds'])->toBe(600);
    expect($service->finalizeExpired($test->id))->toBe(1);
    expect($activity->summary($attempt->fresh(), $test)['state'])->toBe('finished');
});
