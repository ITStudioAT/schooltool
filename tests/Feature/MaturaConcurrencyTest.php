<?php

use App\Models\MaturaVisit;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\Matura\MaturaSetupService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Symfony\Component\Process\Process;

uses(DatabaseTruncation::class);

test('simultaneous database connections cannot overbook reservations or toilet occupancy', function () {
    $school = School::factory()->create();
    $year = Schoolyear::factory()->create(['school_id' => $school->id]);
    $user = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id, 'is_active' => true]);
    Role::findOrCreate('teacher', 'web');
    $user->assignRole('teacher');
    $session = app(MaturaSetupService::class)->save($user, [
        'name' => 'Parallelität', 'exam_date' => '2026-10-04', 'schoolyear_id' => $year->id, 'waiting_places' => 0,
        'rooms' => [
            ['name' => 'A', 'student_ids' => [], 'manual_students' => [['name' => 'Test Eins']]],
            ['name' => 'B', 'student_ids' => [], 'manual_students' => [['name' => 'Test Zwei']]],
        ],
    ]);
    $session->update(['status' => 'active']);
    $visits = $session->students()->get()->map(fn ($student) => $session->visits()->create([
        'matura_student_id' => $student->id, 'matura_room_id' => $student->matura_room_id,
        'status' => 'requested', 'requested_at' => now(), 'request_key' => Str::uuid(),
    ]));

    $runConcurrently = function (string $action, string $status) use ($visits, $session, $user): array {
        $start = microtime(true) + 2;
        $processes = $visits->map(function (MaturaVisit $visit) use ($action, $status, $start, $session, $user): Process {
            $process = new Process([
                PHP_BINARY, base_path('tests/Support/matura-concurrency-worker.php'),
                (string) $user->id, (string) $session->id,
                json_encode(['action' => $action, 'visit_id' => $visit->id, 'expected_status' => $status, 'operation_key' => (string) Str::uuid()], JSON_THROW_ON_ERROR),
                (string) $start,
            ], base_path(), [
                'APP_ENV' => 'testing', 'DB_DATABASE' => config('database.connections.mysql.database'),
                'DB_DATABASE_TEST' => config('database.connections.mysql.database'),
                'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
            ], timeout: 20);
            $process->start();

            return $process;
        });

        return $processes->map(function (Process $process): string {
            $process->wait();
            expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());

            return trim($process->getOutput());
        })->sort()->values()->all();
    };

    expect($runConcurrently('approve', 'requested'))->toBe(['200', '409']);
    expect($session->visits()->whereIn('status', MaturaVisit::Reserved)->count())->toBe(1);

    $session->update(['waiting_places' => 1]);
    $session->visits()->update(['status' => 'arrived', 'approved_at' => now(), 'departed_at' => now(), 'arrived_at' => now()]);
    expect($runConcurrently('enter', 'arrived'))->toBe(['200', '409']);
    expect($session->visits()->where('status', 'toilet')->count())->toBe(1);
});
