<?php

use App\Models\NumberSystemGameRun;
use App\Models\NumberSystemGameSession;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\User;
use App\Services\NumberSystems\NumberSystemEngine;
use App\Services\NumberSystems\NumberSystemGameService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Symfony\Component\Process\Process;

require_once dirname(__DIR__).'/Support/MultipleChoiceTestDatabase.php';
requireMultipleChoiceTestDatabase();
uses(DatabaseTruncation::class);

afterEach(function (): void {
    if (! isset($this->concurrencyFixture)) {
        return;
    }

    $fixture = $this->concurrencyFixture;
    $fixture['session']->delete();
    $fixture['membership']->forceDelete();
    $fixture['course']->delete();
    $fixture['student']->delete();
    $fixture['teacher']->delete();
    $fixture['year']->delete();
    $fixture['school']->delete();
});

test('simultaneous connections accept one revision and one answer without losing or duplicating progress', function (): void {
    $school = School::factory()->create();
    $year = Schoolyear::factory()->create(['school_id' => $school->id]);
    $teacher = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id]);
    $course = TeachingCourse::factory()->forSchool($school)->forSchoolyear($year)->forTeacher($teacher)->create();
    $session = NumberSystemGameSession::factory()->create(['teaching_course_id' => $course->id, 'status' => 'running']);
    $student = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id]);
    $membership = $course->teachingCourseStudents()->create(['user_id' => $student->id, 'import116_id' => null, 'canceled_at' => null]);
    $this->concurrencyFixture = compact('session', 'membership', 'course', 'student', 'teacher', 'year', 'school');
    $run = app(NumberSystemGameService::class)->join($student, $session->join_code, null, null);
    $environment = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306',
        'DB_DATABASE' => config('database.connections.mysql.database'), 'DB_DATABASE_TEST' => config('database.connections.mysql.database'),
        'DB_USERNAME' => config('database.connections.mysql.username'), 'DB_PASSWORD' => config('database.connections.mysql.password'),
        'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
        'SCHOOLTOOL_MC_TEST_RECEIPT' => getenv('SCHOOLTOOL_MC_TEST_RECEIPT'), 'TEST_TOKEN' => 'number-systems-concurrency',
        'APP_CONFIG_CACHE' => 'bootstrap/cache/number-systems-concurrency.config.php'];
    $code = <<<'PHP'
require getcwd().'/vendor/autoload.php';
require getcwd().'/tests/Support/MultipleChoiceTestDatabase.php';
requireMultipleChoiceTestDatabase();
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$run = App\Models\NumberSystemGameRun::findOrFail((int) $argv[1]);
$student = App\Models\User::findOrFail((int) $argv[2]);
$data = json_decode($argv[3], true, flags: JSON_THROW_ON_ERROR);
try {
    $app->make(App\Services\NumberSystems\NumberSystemGameService::class)->mutate($run, $student, $data['action'], $data);
    echo '200';
} catch (Symfony\Component\HttpKernel\Exception\HttpException $failure) {
    echo $failure->getStatusCode();
}
PHP;
    $race = function (array $drafts, string $action) use ($run, $student, $environment, $code): void {
        $run->refresh();
        $workers = [];
        try {
            foreach ($drafts as $draft) {
                $payload = ['action' => $action, 'revision' => $run->revision, 'mission_id' => $run->puzzles[0]['id'], 'draft' => $draft];
                $worker = new Process([PHP_BINARY, '-r', $code, (string) $run->id, (string) $student->id, json_encode($payload, JSON_THROW_ON_ERROR)], base_path(), $environment);
                $worker->setTimeout(20)->start();
                $workers[] = $worker;
            }
            $statuses = [];
            foreach ($workers as $worker) {
                expect($worker->wait())->toBe(0, $worker->getErrorOutput());
                $statuses[] = $worker->getOutput();
            }
            sort($statuses);
            expect($statuses)->toBe(['200', '409']);
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
        }
    };
    $race([['answer' => 'first'], ['answer' => 'second']], 'draft');
    expect($run->fresh()->revision)->toBe(1);
    expect($run->fresh()->draft['answer'])->toBeIn(['first', 'second']);
    $engine = new NumberSystemEngine;
    $draft = [];
    foreach ($run->puzzles[0]['solution'] as $key => $spec) {
        $draft[$key] = $spec['type'] === 'number' ? $engine->notation($spec['value'], $spec['base'], $spec['width'] ?? null) : $spec['value'];
    }
    $race([$draft, $draft], 'answer');
    $run = NumberSystemGameRun::findOrFail($run->id);
    expect($run->revision)->toBe(2)->and($run->solved)->toBeTrue()->and($run->attempts)->toBe(1)->and($run->history)->toHaveCount(1);
});
