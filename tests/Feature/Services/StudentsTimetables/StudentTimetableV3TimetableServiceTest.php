<?php

use App\Enums\StudentTimetableStudyProgram;
use App\Models\Import116;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\StudentTimetableSubjectMapping;
use App\Models\StudentTimetableSubjectRow;
use App\Models\StudentTimetableV3Timetable;
use App\Models\User;
use App\Services\StudentsTimetables\RobotTimetableBackendSetupService;
use App\Services\StudentsTimetables\StudentTimetableCalculationSettingsService;
use App\Services\StudentsTimetables\StudentTimetableOverviewService;
use App\Services\StudentsTimetables\StudentTimetableRememberedTtEntryService;
use App\Services\StudentsTimetables\StudentTimetablesStudentOverviewService;
use App\Services\StudentsTimetables\StudentTimetableV3SessionScope;
use App\Services\StudentsTimetables\StudentTimetableV3StudentInformationService;
use App\Services\StudentsTimetables\StudentTimetableV3TimetableService;
use App\Services\StudentsTimetables\StudentTimetableV3TimetableStorage;
use App\Services\StudentsTimetables\TimetableDateSlotOverlapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

it('returns a persisted v3 timetable without recalculating or mutating it', function () {
    [$user, , $studentCode, $information, $courseGroups, $timetable] = v3PersistedReadFixture();
    $storedFingerprint = $timetable->getRawOriginal('fingerprint');
    $storedGeneratedAt = $timetable->getRawOriginal('generated_at');
    $storedUpdatedAt = $timetable->getRawOriginal('updated_at');

    $result = v3TimetableReadService(
        $user,
        $information,
        $courseGroups,
        $studentCode,
    )->resultForUser($user, [
        'planning_mode' => 'with_student',
        'student_code' => $studentCode,
    ]);
    $freshTimetable = $timetable->fresh();

    expect($result)
        ->id->toBe($timetable->id)
        ->status->toBe('reused')
        ->reused->toBeTrue()
        ->context->toBe([
            'planning_mode' => 'with_student',
            'student_code' => $studentCode,
        ])
        ->modules->toEqual($timetable->modules)
        ->parameters->toEqual($timetable->parameters)
        ->summary->toEqual($timetable->summary)
        ->timetables->toEqual((new StudentTimetableV3TimetableStorage)->expand($timetable->timetables))
        ->timetables_meta->toMatchArray([
            'current_page' => 1,
            'per_page' => 100,
            'last_page' => 1,
            'total' => 1,
            'offset' => 0,
            'from' => 1,
            'to' => 1,
        ])
        ->and($freshTimetable?->getRawOriginal('fingerprint'))->toBe($storedFingerprint)
        ->and($freshTimetable?->getRawOriginal('generated_at'))->toBe($storedGeneratedAt)
        ->and($freshTimetable?->getRawOriginal('updated_at'))->toBe($storedUpdatedAt)
        ->and(StudentTimetableV3Timetable::query()->count())->toBe(1);
});

it('reads a scoped legacy timetable page without migrating the stored payload', function () {
    [$user, , $studentCode, $information, $courseGroups, $timetable] = v3PersistedReadFixture();
    $expandedTimetables = (new StudentTimetableV3TimetableStorage)->expand($timetable->timetables);
    $legacyTimetables = collect(range(1, 101))
        ->map(fn (int $number): array => [
            ...$expandedTimetables[0],
            'key' => "legacy-{$number}",
            'number' => $number,
        ])
        ->all();
    $timetable->update([
        'summary' => [
            ...$timetable->summary,
            'timetable_count' => 101,
        ],
        'timetables' => $legacyTimetables,
    ]);
    $timetable = $timetable->fresh();
    $storedPayload = $timetable->getRawOriginal('timetables');
    $storedUpdatedAt = $timetable->getRawOriginal('updated_at');

    $service = v3TimetableReadService(
        $user,
        $information,
        $courseGroups,
        $studentCode,
    );
    $result = $service->resultForUser($user, [
        'planning_mode' => 'with_student',
        'student_code' => $studentCode,
    ], 2, (string) $timetable->fingerprint);
    $outsideActualPageRange = v3TimetableReadService(
        $user,
        $information,
        $courseGroups,
        $studentCode,
    )->resultForUser($user, [
        'planning_mode' => 'with_student',
        'student_code' => $studentCode,
    ], 5, (string) $timetable->fingerprint);

    expect($result)
        ->timetables->toHaveCount(1)
        ->and($result['timetables'][0])->toMatchArray([
            'key' => 'legacy-101',
            'number' => 101,
        ])
        ->and($result['timetables_meta'])->toMatchArray([
            'current_page' => 2,
            'per_page' => 100,
            'last_page' => 2,
            'total' => 101,
            'offset' => 100,
            'from' => 101,
            'to' => 101,
        ])
        ->and($outsideActualPageRange)->toBeNull()
        ->and($timetable->fresh()?->getRawOriginal('timetables'))->toBe($storedPayload)
        ->and($timetable->fresh()?->getRawOriginal('updated_at'))->toBe($storedUpdatedAt);
});

it('requires the current fingerprint for persisted follow-up pages', function () {
    [$user] = v3TimetableServiceUser();
    $exception = null;

    try {
        v3TimetableReadService()->resultForUser(
            $user,
            ['planning_mode' => 'without_student'],
            2,
        );
    } catch (ValidationException $caughtException) {
        $exception = $caughtException;
    }

    expect($exception)
        ->toBeInstanceOf(ValidationException::class)
        ->and($exception?->errors())->toHaveKey('fingerprint');
});

it('fails closed when persisted timetable storage is not an array', function () {
    [$user, , $studentCode, $information, $courseGroups, $timetable] = v3PersistedReadFixture();

    DB::table('student_timetable_v3_timetables')
        ->where('id', $timetable->id)
        ->update(['timetables' => json_encode('corrupt', JSON_THROW_ON_ERROR)]);

    $result = v3TimetableReadService(
        $user,
        $information,
        $courseGroups,
        $studentCode,
    )->resultForUser($user, [
        'planning_mode' => 'with_student',
        'student_code' => $studentCode,
    ]);

    expect($result)->toBeNull();
});

it('fails closed when the persisted timetable count is not an integer', function () {
    [$user, , $studentCode, $information, $courseGroups, $timetable] = v3PersistedReadFixture();
    $timetable->update([
        'summary' => [
            ...$timetable->summary,
            'timetable_count' => '1corrupt',
        ],
    ]);

    $result = v3TimetableReadService(
        $user,
        $information,
        $courseGroups,
        $studentCode,
    )->resultForUser($user, [
        'planning_mode' => 'with_student',
        'student_code' => $studentCode,
    ]);

    expect($result)->toBeNull();
});

it('does not restore an expired v3 timetable for an otherwise matching session', function () {
    [$user, , $studentCode, , , $timetable] = v3PersistedReadFixture();
    $timetable->update(['expires_at' => now()->subSecond()]);

    $result = v3TimetableReadService()->resultForUser($user, [
        'planning_mode' => 'with_student',
        'student_code' => $studentCode,
    ]);

    expect($result)->toBeNull()
        ->and($timetable->fresh())->not->toBeNull();
});

it('ignores a persisted v3 timetable when the selected course schedule changed', function (
    string $field,
    mixed $value,
) {
    [$user, , $studentCode, $information, $courseGroups, $timetable] = v3PersistedReadFixture();
    $courseGroups[0][$field] = $value;
    $storedFingerprint = $timetable->getRawOriginal('fingerprint');
    $storedGeneratedAt = $timetable->getRawOriginal('generated_at');
    $storedUpdatedAt = $timetable->getRawOriginal('updated_at');

    $result = v3TimetableReadService(
        $user,
        $information,
        $courseGroups,
        $studentCode,
    )->resultForUser($user, [
        'planning_mode' => 'with_student',
        'student_code' => $studentCode,
    ]);
    $freshTimetable = $timetable->fresh();

    expect($result)->toBeNull()
        ->and($freshTimetable?->getRawOriginal('fingerprint'))->toBe($storedFingerprint)
        ->and($freshTimetable?->getRawOriginal('generated_at'))->toBe($storedGeneratedAt)
        ->and($freshTimetable?->getRawOriginal('updated_at'))->toBe($storedUpdatedAt);
})->with([
    'weekday' => ['weekday', 2],
    'hour' => ['hour', 2],
    'dates' => ['dates', ['2026-09-07', '2026-09-14']],
]);

it('ignores a persisted v3 timetable when its selected course is no longer active', function () {
    [$user, , $studentCode, $information, , $timetable] = v3PersistedReadFixture();
    $storedUpdatedAt = $timetable->getRawOriginal('updated_at');

    $result = v3TimetableReadService(
        $user,
        $information,
        [],
        $studentCode,
    )->resultForUser($user, [
        'planning_mode' => 'with_student',
        'student_code' => $studentCode,
    ]);

    expect($result)->toBeNull()
        ->and($timetable->fresh()?->getRawOriginal('updated_at'))->toBe($storedUpdatedAt);
});

it('ignores a persisted v3 timetable when an authoritative subject row changed', function () {
    [$user, , $studentCode, $information, $courseGroups, $timetable, $subjectRow] = v3PersistedReadFixture();
    $subjectRow->update(['hours_per_week' => 2]);
    $storedUpdatedAt = $timetable->getRawOriginal('updated_at');

    $result = v3TimetableReadService(
        $user,
        $information,
        $courseGroups,
        $studentCode,
    )->resultForUser($user, [
        'planning_mode' => 'with_student',
        'student_code' => $studentCode,
    ]);

    expect($result)->toBeNull()
        ->and($timetable->fresh()?->getRawOriginal('updated_at'))->toBe($storedUpdatedAt);
});

it('ignores a persisted v3 timetable when an authoritative subject mapping changed', function () {
    [$user, $schoolyear, $studentCode, $information, $courseGroups, $timetable] = v3PersistedReadFixture();
    StudentTimetableSubjectMapping::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'json_subject' => 'D',
        'tt_subject' => 'D',
        'note' => 'Changed after calculation',
        'is_active' => true,
        'source' => 'test',
    ]);
    $storedUpdatedAt = $timetable->getRawOriginal('updated_at');

    $result = v3TimetableReadService(
        $user,
        $information,
        $courseGroups,
        $studentCode,
    )->resultForUser($user, [
        'planning_mode' => 'with_student',
        'student_code' => $studentCode,
    ]);

    expect($result)->toBeNull()
        ->and($timetable->fresh()?->getRawOriginal('updated_at'))->toBe($storedUpdatedAt);
});

it('ignores a persisted v3 timetable when current student planning data changed', function (
    string $field,
    mixed $value,
) {
    [$user, , $studentCode, $information, $courseGroups, $timetable] = v3PersistedReadFixture();
    $information[$field] = $value;
    $storedUpdatedAt = $timetable->getRawOriginal('updated_at');

    $result = v3TimetableReadService(
        $user,
        $information,
        $courseGroups,
        $studentCode,
    )->resultForUser($user, [
        'planning_mode' => 'with_student',
        'student_code' => $studentCode,
    ]);

    expect($result)->toBeNull()
        ->and($timetable->fresh()?->getRawOriginal('updated_at'))->toBe($storedUpdatedAt);
})->with([
    'semester' => ['semester', 2],
    'study program' => ['study_program', StudentTimetableStudyProgram::Kompaktstudium->value],
    'missing student' => ['student_code', ''],
]);

it('ignores a persisted v3 timetable generated by an unknown or older algorithm', function (array $summary) {
    [$user, $schoolyear] = v3TimetableServiceUser();
    $timetable = StudentTimetableV3Timetable::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'session_id_hash' => app(StudentTimetableV3SessionScope::class)->currentHash(),
        'context_key' => 'without-student',
        'planning_mode' => 'without_student',
        'student_code' => null,
        'fingerprint' => str_repeat('c', 64),
        'modules' => [['selection_key' => 'additional:Rev2', 'code' => 'Rev2']],
        'parameters' => ['planning_mode' => 'without_student'],
        'summary' => $summary,
        'timetables' => [['number' => 1]],
        'generated_at' => now()->subMinute(),
        'expires_at' => app(StudentTimetableV3SessionScope::class)->expiresAt(),
    ]);
    $storedUpdatedAt = $timetable->getRawOriginal('updated_at');

    $result = v3TimetableReadService()->resultForUser($user, [
        'planning_mode' => 'without_student',
    ]);

    expect($result)->toBeNull()
        ->and($timetable->fresh()?->getRawOriginal('updated_at'))->toBe($storedUpdatedAt)
        ->and(StudentTimetableV3Timetable::query()->count())->toBe(1);
})->with([
    'missing version' => [['timetable_count' => 18]],
    'older version' => [['algorithm_version' => 6, 'timetable_count' => 18]],
]);

it('returns null when no persisted v3 timetable exists for the exact user and planning context', function () {
    [$user, $schoolyear] = v3TimetableServiceUser();
    $otherUser = User::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
    ]);
    $otherSchool = School::factory()->create();
    $otherSchoolyear = Schoolyear::factory()->create(['school_id' => $user->school_id]);
    $studentContext = 'student:'.hash('sha256', 'student-100');
    $baseTimetable = [
        'context_key' => $studentContext,
        'planning_mode' => 'with_student',
        'student_code' => 'student-100',
        'fingerprint' => str_repeat('b', 64),
        'modules' => [],
        'parameters' => [],
        'summary' => [],
        'timetables' => [],
        'generated_at' => now(),
    ];

    StudentTimetableV3Timetable::query()->create([
        ...$baseTimetable,
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $otherUser->id,
    ]);
    StudentTimetableV3Timetable::query()->create([
        ...$baseTimetable,
        'school_id' => $user->school_id,
        'schoolyear_id' => $otherSchoolyear->id,
        'user_id' => $user->id,
    ]);
    StudentTimetableV3Timetable::query()->create([
        ...$baseTimetable,
        'school_id' => $otherSchool->id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
    ]);
    StudentTimetableV3Timetable::query()->create([
        ...$baseTimetable,
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'context_key' => 'student:'.hash('sha256', 'student-200'),
        'student_code' => 'student-200',
    ]);

    $result = v3TimetableReadService()->resultForUser($user, [
        'planning_mode' => 'with_student',
        'student_code' => 'student-100',
    ]);

    expect($result)->toBeNull()
        ->and(StudentTimetableV3Timetable::query()->count())->toBe(4);
});

it('validates with-student and without-student persisted v3 timetable contexts', function (
    array $parameters,
    string $errorKey,
) {
    [$user] = v3TimetableServiceUser();
    $exception = null;

    try {
        v3TimetableReadService()->resultForUser($user, $parameters);
    } catch (ValidationException $caughtException) {
        $exception = $caughtException;
    }

    expect($exception)
        ->toBeInstanceOf(ValidationException::class)
        ->and($exception?->errors())->toHaveKey($errorKey)
        ->and(StudentTimetableV3Timetable::query()->count())->toBe(0);
})->with([
    'with student requires student code' => [
        ['planning_mode' => 'with_student'],
        'student_code',
    ],
    'without student rejects student code' => [
        ['planning_mode' => 'without_student', 'student_code' => 'student-100'],
        'student_code',
    ],
]);

it('creates, updates, and reuses all possible v3 timetables for a planning context', function () {
    [$user, $schoolyear] = v3TimetableServiceUser();

    v3TimetableServiceSubjectRow($user, $schoolyear, 'D1', 'D', 'Deutsch 1', 1);
    v3TimetableServiceSubjectRow($user, $schoolyear, 'M1', 'M', 'Mathematik 1', 2);

    $courseGroups = [
        v3TimetableServiceCourseGroup('d1-a', 'D1-A', 'D1', 1, 1),
        v3TimetableServiceCourseGroup('d1-b', 'D1-B', 'D1', 1, 2),
        v3TimetableServiceCourseGroup('m1-a', 'M1-A', 'M1', 2, 1),
        v3TimetableServiceCourseGroup('m1-b', 'M1-B', 'M1', 2, 2),
    ];
    $service = v3TimetableService(
        $user,
        v3TimetableServiceInformation(),
        $courseGroups,
    );
    $modules = ['additional:M1', 'additional:D1'];
    $parameters = [
        'planning_mode' => 'without_student',
        'selected_course_keys' => ['m1-b', 'd1-a', 'm1-a', 'd1-b'],
    ];

    $created = $service->createOrUpdateForUser($user, $modules, $parameters);

    expect($created)
        ->status->toBe('created')
        ->reused->toBeFalse()
        ->summary->algorithm_version->toBe(7)
        ->summary->timetable_count->toBe(4)
        ->summary->timetable_variation_count->toBe(4)
        ->summary->full_green_timetable_count->toBe(4)
        ->summary->green_timetable_count->toBe(0)
        ->summary->conflict_timetable_count->toBe(0)
        ->timetables->toHaveCount(4)
        ->and($created['summary'])->not->toHaveKey('solution_plan')
        ->and(array_column($created['timetables'], 'number'))->toBe([1, 2, 3, 4])
        ->and(array_unique(array_column($created['timetables'], 'type')))->toBe(['full_green']);

    foreach ($created['timetables'] as $timetable) {
        expect(collect($timetable['slots'])->pluck('code')->sort()->values()->all())
            ->toBe(['D1', 'M1']);
    }

    $record = StudentTimetableV3Timetable::query()->sole();

    expect($record)
        ->school_id->toBe($user->school_id)
        ->schoolyear_id->toBe($schoolyear->id)
        ->user_id->toBe($user->id)
        ->planning_mode->toBe('without_student')
        ->student_code->toBeNull()
        ->and($record->timetables['storage_version'])->toBe(1)
        ->and($record->timetables['timetables'])->toHaveCount(4)
        ->and($record->timetables['lessons'])->not->toBeEmpty();

    $updated = $service->createOrUpdateForUser($user, $modules, [
        ...$parameters,
        'selected_course_keys' => ['d1-a', 'm1-a', 'm1-b'],
    ]);

    expect($updated)
        ->id->toBe($created['id'])
        ->status->toBe('updated')
        ->reused->toBeFalse()
        ->summary->algorithm_version->toBe(7)
        ->summary->timetable_count->toBe(2)
        ->timetables->toHaveCount(2)
        ->fingerprint->not->toBe($created['fingerprint'])
        ->and(StudentTimetableV3Timetable::query()->count())->toBe(1);

    $reused = $service->createOrUpdateForUser($user, [
        ['selection_key' => 'additional:D1', 'selected_course_keys' => ['d1-a']],
        ['selection_key' => 'additional:M1', 'selected_course_keys' => ['m1-b', 'm1-a', 'm1-a']],
    ]);

    expect($reused)
        ->id->toBe($created['id'])
        ->status->toBe('reused')
        ->reused->toBeTrue()
        ->fingerprint->toBe($updated['fingerprint'])
        ->timetables->toEqual($updated['timetables']);
});

it('keeps different workspace calculations for the same student isolated', function () {
    [$user, $schoolyear] = v3TimetableServiceUser();
    $studentCode = 'same-student';
    $firstWorkspaceId = '44444444-4444-4444-8444-444444444441';
    $secondWorkspaceId = '44444444-4444-4444-8444-444444444442';

    v3TimetableServiceSubjectRow($user, $schoolyear, 'D1', 'D', 'Deutsch 1', 1);
    $information = v3TimetableServiceInformation(includeMathematics: false);
    $information['student_code'] = $studentCode;
    $courseGroups = [
        v3TimetableServiceCourseGroup('d1-a', 'D1-A', 'D1', 1, 1),
        v3TimetableServiceCourseGroup('d1-b', 'D1-B', 'D1', 2, 1),
    ];
    $service = v3TimetableService($user, $information, $courseGroups, $studentCode);

    $first = $service->createOrUpdateForUser($user, ['additional:D1'], [
        'workspace_id' => $firstWorkspaceId,
        'planning_mode' => 'with_student',
        'student_code' => $studentCode,
        'selected_course_keys' => ['d1-a'],
    ]);
    $second = $service->createOrUpdateForUser($user, ['additional:D1'], [
        'workspace_id' => $secondWorkspaceId,
        'planning_mode' => 'with_student',
        'student_code' => $studentCode,
        'selected_course_keys' => ['d1-b'],
    ]);

    $restoredFirst = v3TimetableReadService($user, $information, $courseGroups, $studentCode)->resultForUser($user, [
        'workspace_id' => $firstWorkspaceId,
        'planning_mode' => 'with_student',
        'student_code' => $studentCode,
    ]);
    $restoredSecond = v3TimetableReadService($user, $information, $courseGroups, $studentCode)->resultForUser($user, [
        'workspace_id' => $secondWorkspaceId,
        'planning_mode' => 'with_student',
        'student_code' => $studentCode,
    ]);

    expect($first['id'])->not->toBe($second['id'])
        ->and($restoredFirst['id'])->toBe($first['id'])
        ->and($restoredFirst['parameters']['selected_course_keys'])->toBe(['d1-a'])
        ->and($restoredSecond['id'])->toBe($second['id'])
        ->and($restoredSecond['parameters']['selected_course_keys'])->toBe(['d1-b'])
        ->and(StudentTimetableV3Timetable::query()->count())->toBe(2);
});

it('keeps the same workspace isolated between concurrent login sessions', function () {
    [$user, $schoolyear] = v3TimetableServiceUser();
    $workspaceId = '55555555-5555-4555-8555-555555555555';
    $firstSessionId = str_repeat('a', 40);
    $secondSessionId = str_repeat('b', 40);

    v3TimetableServiceSubjectRow($user, $schoolyear, 'D1', 'D', 'Deutsch 1', 1);
    $information = v3TimetableServiceInformation(includeMathematics: false);
    $courseGroups = [
        v3TimetableServiceCourseGroup('d1-a', 'D1-A', 'D1', 1, 1),
        v3TimetableServiceCourseGroup('d1-b', 'D1-B', 'D1', 2, 1),
    ];
    $service = v3TimetableService($user, $information, $courseGroups);

    session()->setId($firstSessionId);
    $first = $service->createOrUpdateForUser($user, ['additional:D1'], [
        'workspace_id' => $workspaceId,
        'planning_mode' => 'without_student',
        'selected_course_keys' => ['d1-a'],
    ]);

    session()->setId($secondSessionId);
    $second = $service->createOrUpdateForUser($user, ['additional:D1'], [
        'workspace_id' => $workspaceId,
        'planning_mode' => 'without_student',
        'selected_course_keys' => ['d1-b'],
    ]);
    $restoredSecond = v3TimetableReadService($user, $information, $courseGroups)->resultForUser($user, [
        'workspace_id' => $workspaceId,
        'planning_mode' => 'without_student',
    ]);

    session()->setId($firstSessionId);
    $restoredFirst = v3TimetableReadService($user, $information, $courseGroups)->resultForUser($user, [
        'workspace_id' => $workspaceId,
        'planning_mode' => 'without_student',
    ]);
    $records = StudentTimetableV3Timetable::query()->orderBy('id')->get();

    expect($first['id'])->not->toBe($second['id'])
        ->and($restoredFirst['id'])->toBe($first['id'])
        ->and($restoredFirst['parameters']['selected_course_keys'])->toBe(['d1-a'])
        ->and($restoredSecond['id'])->toBe($second['id'])
        ->and($restoredSecond['parameters']['selected_course_keys'])->toBe(['d1-b'])
        ->and($records)->toHaveCount(2)
        ->and($records->pluck('session_id_hash')->unique())->toHaveCount(2)
        ->and($records->pluck('session_id_hash'))->not->toContain($firstSessionId, $secondSessionId)
        ->and($records->every(fn (StudentTimetableV3Timetable $record): bool => $record->expires_at->isFuture()))
        ->toBeTrue();
});

it('marks distance learning in generated v3 timetables using normal study hours', function () {
    [$user, $schoolyear] = v3TimetableServiceUser();
    $subjectRow = v3TimetableServiceSubjectRow($user, $schoolyear, 'D1', 'D', 'Deutsch 1', 1);
    $subjectRow->update([
        'study_program' => StudentTimetableStudyProgram::Kompaktstudium,
        'hours_per_week' => 2,
    ]);
    $information = v3TimetableServiceInformation(includeMathematics: false);
    $information['study_program'] = StudentTimetableStudyProgram::Kompaktstudium->value;
    $information['module_selection_groups'][0]['modules'][0]['hours'] = 4;
    $information['module_selection_groups'][0]['modules'][0]['courses'] = [[
        'key' => 'd1-distance-first',
        'keys' => ['d1-distance-first', 'd1-distance-second'],
        'title' => 'D1 - 4A - TEST',
    ]];
    $courseGroups = [
        v3TimetableServiceCourseGroup('d1-distance-first', 'D1 - 4A - TEST', 'D1', 1, 1),
        v3TimetableServiceCourseGroup('d1-distance-second', 'D1 - 4A - TEST', 'D1', 1, 2),
    ];
    $service = v3TimetableService($user, $information, $courseGroups);

    $result = $service->createOrUpdateForUser($user, ['additional:D1'], [
        'planning_mode' => 'without_student',
        'selected_course_keys' => ['d1-distance-first', 'd1-distance-second'],
    ]);
    $slots = collect($result['timetables'][0]['slots']);

    expect($result)
        ->summary->algorithm_version->toBe(7)
        ->timetables->toHaveCount(1)
        ->and($slots)->toHaveCount(2)
        ->and($slots->every(
            fn (array $slot): bool => ($slot['isDistanceLearningCourse'] ?? false) === true,
        ))->toBeTrue()
        ->and($result['timetables'][0]['metrics']['distance_learning_count'])->toBe(1);
});

it('rejects stale or unrelated course keys without overwriting the last v3 result', function () {
    [$user, $schoolyear] = v3TimetableServiceUser();

    v3TimetableServiceSubjectRow($user, $schoolyear, 'D1', 'D', 'Deutsch 1', 1);

    $service = v3TimetableService(
        $user,
        v3TimetableServiceInformation(includeMathematics: false),
        [v3TimetableServiceCourseGroup('d1-a', 'D1-A', 'D1', 1, 1)],
    );
    $created = $service->createOrUpdateForUser($user, ['additional:D1'], [
        'selected_course_keys' => ['d1-a'],
    ]);

    expect(fn () => $service->createOrUpdateForUser($user, ['additional:D1'], [
        'selected_course_keys' => ['d1-a', 'foreign-course-key'],
    ]))->toThrow(ValidationException::class);

    $record = StudentTimetableV3Timetable::query()->sole();

    expect($record)
        ->fingerprint->toBe($created['fingerprint'])
        ->and((new StudentTimetableV3TimetableStorage)->expand($record->timetables))->toEqual($created['timetables']);
});

it('rejects generation when any selected module is missing from the resolved courses', function () {
    [$user, $schoolyear] = v3TimetableServiceUser();

    v3TimetableServiceSubjectRow($user, $schoolyear, 'D1', 'D', 'Deutsch 1', 1);
    v3TimetableServiceSubjectRow($user, $schoolyear, 'D1', 'D', 'Deutsch 1 Duplikat', 2);

    $service = v3TimetableService(
        $user,
        v3TimetableServiceInformation(),
        [
            v3TimetableServiceCourseGroup('d1-a', 'D1-A', 'D1', 1, 1),
            v3TimetableServiceCourseGroup('m1-a', 'M1-A', 'M1', 2, 1),
        ],
    );
    $exception = null;

    try {
        $service->createOrUpdateForUser($user, ['additional:D1', 'additional:M1'], [
            'planning_mode' => 'without_student',
            'selected_course_keys' => ['d1-a', 'm1-a'],
        ]);
    } catch (ValidationException $caughtException) {
        $exception = $caughtException;
    }

    expect($exception)
        ->toBeInstanceOf(ValidationException::class)
        ->and($exception?->errors())->toHaveKey('modules')
        ->and(StudentTimetableV3Timetable::query()->count())->toBe(0);
});

it('resolves compact generic religion rows to Rev2 in every generated timetable', function () {
    [$user, $schoolyear] = v3TimetableServiceUser();

    StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'study_program' => StudentTimetableStudyProgram::Kompaktstudium,
        'semester' => 2,
        'branch' => 'common',
        'json_code' => 'R2',
        'json_subject' => 'R',
        'name' => 'Religion 2',
        'hours_per_week' => 1,
        'is_active' => true,
        'sort_order' => 1,
        'source' => 'test',
    ]);
    $information = v3TimetableServiceInformation(includeMathematics: false);
    $information['study_program'] = StudentTimetableStudyProgram::Kompaktstudium->value;
    $information['selection_fields'][0]['selected_value'] = 'Rev';
    $information['module_selection_groups'][0]['modules'] = [[
        'selection_key' => 'additional:Rev2',
        'code' => 'Rev2',
        'name' => 'Religion evangelisch 2',
        'hours' => 1,
        'courses' => [
            ['key' => 'rev2-a', 'keys' => ['rev2-a'], 'title' => 'Rev2-A'],
            ['key' => 'rev2-b', 'keys' => ['rev2-b'], 'title' => 'Rev2-B'],
        ],
    ]];
    $service = v3TimetableService(
        $user,
        $information,
        [
            v3TimetableServiceCourseGroup('rev2-a', 'Rev2-A', 'Rev2', 1, 1),
            v3TimetableServiceCourseGroup('rev2-b', 'Rev2-B', 'Rev2', 2, 1),
        ],
    );

    $result = $service->createOrUpdateForUser($user, ['additional:Rev2'], [
        'planning_mode' => 'without_student',
        'selected_course_keys' => ['rev2-a', 'rev2-b'],
    ]);

    expect($result)
        ->summary->selected_module_count->toBe(1)
        ->summary->timetable_variation_count->toBe(2)
        ->summary->timetable_count->toBe(2)
        ->timetables->toHaveCount(2);

    foreach ($result['timetables'] as $timetable) {
        expect(collect($timetable['slots'])->pluck('code')->unique()->values()->all())
            ->toBe(['Rev2']);
    }
});

it('builds a generation catalog without student eligibility filters', function () {
    [$user, $schoolyear] = v3TimetableServiceUser();
    Import116::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'student_code' => 'student-1',
        'school_level' => '05_1',
        'religion' => 'röm.-kath.',
        'import_user_id' => $user->id,
        'exists_date' => now(),
    ]);
    StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'study_program' => StudentTimetableStudyProgram::Normalstudium,
        'semester' => 3,
        'branch' => 'common',
        'json_code' => 'R/ET3',
        'json_subject' => 'R/ET',
        'name' => 'Religion/Ethik 3',
        'hours_per_week' => 1,
        'is_active' => true,
        'sort_order' => 1,
        'source' => 'test',
    ]);
    StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'study_program' => StudentTimetableStudyProgram::Normalstudium,
        'semester' => 2,
        'branch' => 'wirtschaftskundlich',
        'json_code' => 'INF2',
        'json_subject' => 'INF',
        'name' => 'Informatik 2',
        'hours_per_week' => 1,
        'is_active' => true,
        'sort_order' => 2,
        'source' => 'test',
    ]);
    $selection = [
        'semester' => 1,
        'religion' => 'Rk',
        'language' => 'L',
        'branch' => 'gymnasial',
        'arts_subject' => 'ME',
    ];
    $overviewService = app(StudentTimetablesStudentOverviewService::class);
    $filteredSummary = $overviewService->selectionSummaryForStudentCode(
        $user,
        'student-1',
        $selection,
        strictSelectionOverride: true,
        studyProgram: StudentTimetableStudyProgram::Normalstudium,
    );
    $generationSummary = $overviewService->selectionSummaryForStudentCode(
        $user,
        'student-1',
        $selection,
        strictSelectionOverride: true,
        studyProgram: StudentTimetableStudyProgram::Normalstudium,
        includeAllSelectableModules: true,
    );
    $moduleCodes = fn (array $summary): array => collect($summary['module_selection_groups'])
        ->flatMap(fn (array $group): array => $group['modules'])
        ->pluck('code')
        ->all();

    expect($moduleCodes($filteredSummary))
        ->not->toContain('ETH3', 'INF2')
        ->and($moduleCodes($generationSummary))
        ->toContain('ETH3', 'INF2');
});

it('uses selected modules without rechecking student eligibility during generation', function (array $case) {
    [$user, $schoolyear] = v3TimetableServiceUser();
    $studentCode = 'student-1';

    StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'study_program' => StudentTimetableStudyProgram::Normalstudium,
        'semester' => $case['semester'],
        'branch' => $case['subject_branch'],
        'json_code' => $case['subject_code'],
        'json_subject' => $case['subject'],
        'name' => $case['module_code'],
        'hours_per_week' => 1,
        'is_active' => true,
        'sort_order' => 1,
        'source' => 'test',
    ]);
    $information = v3TimetableServiceInformation(includeMathematics: false);
    $information['student_code'] = $studentCode;
    $information['semester'] = $case['semester'];
    $information['selection_fields'] = collect($information['selection_fields'])
        ->map(fn (array $field): array => [
            ...$field,
            'selected_value' => array_key_exists($field['key'], $case['selection'])
                ? $case['selection'][$field['key']]
                : $field['selected_value'],
        ])
        ->all();
    $information['module_selection_groups'][0]['modules'] = [[
        'selection_key' => "additional:{$case['module_code']}",
        'code' => $case['module_code'],
        'name' => $case['module_code'],
        'hours' => 1,
        'courses' => [[
            'key' => $case['course_key'],
            'keys' => [$case['course_key']],
            'title' => "{$case['module_code']}-A",
        ]],
    ]];
    $courseGroup = v3TimetableServiceCourseGroup(
        $case['course_key'],
        "{$case['module_code']}-A",
        $case['module_code'],
        1,
        1,
    );
    $courseGroup['semester'] = $case['semester'];
    $service = v3TimetableService($user, $information, [$courseGroup], $studentCode);

    $result = $service->createOrUpdateForUser($user, ["additional:{$case['module_code']}"], [
        'planning_mode' => 'with_student',
        'student_code' => $studentCode,
        'selected_course_keys' => [$case['course_key']],
    ]);

    expect($result)
        ->summary->selected_module_count->toBe(1)
        ->summary->timetable_count->toBe(1)
        ->timetables->toHaveCount(1)
        ->and(collect($result['timetables'][0]['slots'])->pluck('code')->unique()->values()->all())
        ->toBe([$case['module_code']]);
})->with([
    'catholic student selects ethics' => [[
        'semester' => 3,
        'selection' => ['religion' => 'Rk'],
        'subject_branch' => 'common',
        'subject_code' => 'R/ET3',
        'subject' => 'R/ET',
        'module_code' => 'ETH3',
        'course_key' => 'eth3-a',
    ]],
    'gymnasial student selects wirtschaftskundlich informatics' => [[
        'semester' => 2,
        'selection' => ['branch' => 'gymnasial'],
        'subject_branch' => 'wirtschaftskundlich',
        'subject_code' => 'INF2',
        'subject' => 'INF',
        'module_code' => 'INF2',
        'course_key' => 'inf2-a',
    ]],
]);

it('persists zero possible timetables when every selected module resolves but all variants conflict', function () {
    [$user, $schoolyear] = v3TimetableServiceUser();

    v3TimetableServiceSubjectRow($user, $schoolyear, 'D1', 'D', 'Deutsch 1', 1);
    v3TimetableServiceSubjectRow($user, $schoolyear, 'M1', 'M', 'Mathematik 1', 2);

    $service = v3TimetableService(
        $user,
        v3TimetableServiceInformation(),
        [
            v3TimetableServiceCourseGroup('d1-a', 'D1-A', 'D1', 1, 1),
            v3TimetableServiceCourseGroup('m1-a', 'M1-A', 'M1', 1, 1),
        ],
    );

    $progressPhases = [];
    $result = $service->createOrUpdateForUser(
        $user,
        ['additional:D1', 'additional:M1'],
        [
            'planning_mode' => 'without_student',
            'selected_course_keys' => ['d1-a', 'm1-a'],
        ],
        function (
            int $progressPercent,
            int $combinationCount,
            string $phase,
            int $checkedCombinationCount,
        ) use (&$progressPhases): void {
            $progressPhases[] = $phase;
        },
    );
    $record = StudentTimetableV3Timetable::query()->sole();

    expect($result)
        ->status->toBe('created')
        ->summary->selected_module_count->toBe(2)
        ->summary->timetable_variation_count->toBe(1)
        ->summary->full_green_timetable_count->toBe(0)
        ->summary->green_timetable_count->toBe(0)
        ->summary->conflict_timetable_count->toBe(1)
        ->summary->timetable_count->toBe(0)
        ->summary->solution_plan->toBe([
            'strategy' => 'remove_one_module',
            'module_removal_scenarios' => [
                [
                    'removed_module_selection_key' => 'additional:D1',
                    'removed_module_code' => 'D1',
                    'removed_module_name' => 'Deutsch 1',
                    'possible_timetable_count' => 1,
                    'full_green_timetable_count' => 1,
                    'green_timetable_count' => 0,
                    'status' => 'calculated',
                ],
                [
                    'removed_module_selection_key' => 'additional:M1',
                    'removed_module_code' => 'M1',
                    'removed_module_name' => 'Mathematik 1',
                    'possible_timetable_count' => 1,
                    'full_green_timetable_count' => 1,
                    'green_timetable_count' => 0,
                    'status' => 'calculated',
                ],
            ],
        ])
        ->timetables->toBe([])
        ->and($record->summary)->toMatchArray([
            'selected_module_count' => 2,
            'timetable_variation_count' => 1,
            'conflict_timetable_count' => 1,
            'timetable_count' => 0,
            'solution_plan' => $result['summary']['solution_plan'],
        ])
        ->and($record->timetables)->toEqual([
            'storage_version' => 1,
            'lessons' => [],
            'timetables' => [],
        ])
        ->and($progressPhases)->toContain('analyzing_solutions', 'persisting', 'complete');
});

it('refuses to materialize a partial list when the complete result exceeds the v3 limit', function () {
    $backendSetupService = new RobotTimetableBackendSetupService(
        new StudentTimetableRememberedTtEntryService,
        new TimetableDateSlotOverlapService,
    );
    $subjectRows = [
        v3TimetableServiceRobotSubject('D1'),
        v3TimetableServiceRobotSubject('M1'),
    ];
    $courseGroups = [
        v3TimetableServiceCourseGroup('d1-a', 'D1-A', 'D1', 1, 1),
        v3TimetableServiceCourseGroup('d1-b', 'D1-B', 'D1', 1, 2),
        v3TimetableServiceCourseGroup('m1-a', 'M1-A', 'M1', 2, 1),
        v3TimetableServiceCourseGroup('m1-b', 'M1-B', 'M1', 2, 2),
    ];

    expect(fn () => $backendSetupService->calculateAllTimetableVariations(
        subjectRows: $subjectRows,
        subjectMappings: [],
        courseGroups: $courseGroups,
        settings: v3TimetableServiceRobotSettings(),
        maximumTimetables: 3,
    ))->toThrow(ValidationException::class);
});

it('materializes every timetable with counts and numbering that match each quality type', function () {
    $backendSetupService = new RobotTimetableBackendSetupService(
        new StudentTimetableRememberedTtEntryService,
        new TimetableDateSlotOverlapService,
    );
    $result = $backendSetupService->calculateAllTimetableVariations(
        subjectRows: [
            v3TimetableServiceRobotSubject('D1'),
            v3TimetableServiceRobotSubject('M1'),
        ],
        subjectMappings: [],
        courseGroups: [
            v3TimetableServiceCourseGroup('d1-a', 'D1-A', 'D1', 1, 1),
            v3TimetableServiceCourseGroup('d1-b', 'D1-B', 'D1', 1, 2),
            v3TimetableServiceCourseGroup('m1-green', 'M1-GREEN', 'M1', 2, 1),
            v3TimetableServiceCourseGroup('m1-conflict', 'M1-CONFLICT', 'M1', 1, 1),
        ],
        settings: v3TimetableServiceRobotSettings(),
        maximumTimetables: 10,
    );
    $timetablesByType = collect($result['timetables'])->groupBy('type');

    expect($result['timetables'])->toHaveCount($result['timetable_variation_count'])
        ->and($timetablesByType->get('full_green', collect()))
        ->toHaveCount($result['full_green_timetable_count'])
        ->and($timetablesByType->get('green', collect()))
        ->toHaveCount($result['green_timetable_count'])
        ->and($timetablesByType->get('conflict', collect()))
        ->toHaveCount($result['red_timetable_count']);

    foreach ($timetablesByType as $timetables) {
        expect($timetables->pluck('number')->values()->all())->toBe(range(1, $timetables->count()));
    }
});

it('materializes only possible timetables while retaining conflict counts', function () {
    $backendSetupService = new RobotTimetableBackendSetupService(
        new StudentTimetableRememberedTtEntryService,
        new TimetableDateSlotOverlapService,
    );
    $result = $backendSetupService->calculateAllPossibleTimetableVariations(
        subjectRows: [
            v3TimetableServiceRobotSubject('D1'),
            v3TimetableServiceRobotSubject('M1'),
        ],
        subjectMappings: [],
        courseGroups: [
            v3TimetableServiceCourseGroup('d1-a', 'D1-A', 'D1', 1, 1),
            v3TimetableServiceCourseGroup('d1-b', 'D1-B', 'D1', 1, 2),
            v3TimetableServiceCourseGroup('m1-green', 'M1-GREEN', 'M1', 2, 1),
            v3TimetableServiceCourseGroup('m1-conflict', 'M1-CONFLICT', 'M1', 1, 1),
        ],
        settings: v3TimetableServiceRobotSettings(),
        maximumTimetables: 3,
    );

    expect($result)
        ->timetable_variation_count->toBe(4)
        ->red_timetable_count->toBe(1)
        ->timetables->toHaveCount(3)
        ->and(collect($result['timetables'])->pluck('type')->unique()->values()->all())
        ->not->toContain('conflict');

    $limitedResult = $backendSetupService->calculateAllPossibleTimetableVariations(
        subjectRows: [
            v3TimetableServiceRobotSubject('D1'),
            v3TimetableServiceRobotSubject('M1'),
        ],
        subjectMappings: [],
        courseGroups: [
            v3TimetableServiceCourseGroup('d1-a', 'D1-A', 'D1', 1, 1),
            v3TimetableServiceCourseGroup('d1-b', 'D1-B', 'D1', 1, 2),
            v3TimetableServiceCourseGroup('m1-green', 'M1-GREEN', 'M1', 2, 1),
            v3TimetableServiceCourseGroup('m1-conflict', 'M1-CONFLICT', 'M1', 1, 1),
        ],
        settings: v3TimetableServiceRobotSettings(),
        maximumTimetables: 2,
    );

    expect($limitedResult)
        ->timetable_variation_count->toBe(4)
        ->full_green_timetable_count->toBe(3)
        ->green_timetable_count->toBe(0)
        ->red_timetable_count->toBe(1)
        ->timetables->toHaveCount(2)
        ->and(array_column($limitedResult['timetables'], 'type'))->toBe(['full_green', 'full_green']);
});

it('checks and materializes all 1420 possible timetables below the 2000 limit', function () {
    $backendSetupService = new RobotTimetableBackendSetupService(
        new StudentTimetableRememberedTtEntryService,
        new TimetableDateSlotOverlapService,
    );
    $courseGroups = [
        ...collect(range(1, 20))
            ->map(fn (int $number): array => v3TimetableServiceCourseGroup(
                "d1-{$number}",
                "D1-{$number}",
                'D1',
                1,
                1,
            ))
            ->all(),
        ...collect(range(1, 71))
            ->map(fn (int $number): array => v3TimetableServiceCourseGroup(
                "m1-{$number}",
                "M1-{$number}",
                'M1',
                2,
                1,
            ))
            ->all(),
    ];
    $progress = [];

    $result = $backendSetupService->calculateAllPossibleTimetableVariations(
        subjectRows: [
            v3TimetableServiceRobotSubject('D1'),
            v3TimetableServiceRobotSubject('M1'),
        ],
        subjectMappings: [],
        courseGroups: $courseGroups,
        settings: v3TimetableServiceRobotSettings(),
        maximumTimetables: 2000,
        calculateNoSaturdayTimetableCount: false,
        progressCallback: function (
            int $progressPercent,
            int $combinationCount,
            string $phase,
            int $checkedCombinationCount,
        ) use (&$progress): void {
            $progress[] = [$progressPercent, $combinationCount, $phase, $checkedCombinationCount];
        },
    );

    expect($result)
        ->timetable_variation_count->toBe(1420)
        ->full_green_timetable_count->toBe(1420)
        ->green_timetable_count->toBe(0)
        ->red_timetable_count->toBe(0)
        ->no_saturday_timetable_count->toBe(0)
        ->timetables->toHaveCount(1420)
        ->and(array_column($result['timetables'], 'number'))->toBe(range(1, 1420))
        ->and(array_column($progress, 0))->toBe([0, ...range(5, 90, 5)])
        ->and(array_column($progress, 2))->toBe([
            ...array_fill(0, 17, 'checking'),
            'materializing',
            'compacting',
        ])
        ->and(collect($progress)->pluck(1)->unique()->values()->all())->toBe([1420])
        ->and(array_column($progress, 3))->toBe(
            collect($progress)->pluck(3)->sort()->values()->all(),
        )
        ->and($progress[array_key_last($progress)][3])->toBe(1420);
});

it('persists full green and green timetables while retaining omitted conflict counts', function () {
    [$user, $schoolyear] = v3TimetableServiceUser();

    v3TimetableServiceSubjectRow($user, $schoolyear, 'D1', 'D', 'Deutsch 1', 1);
    v3TimetableServiceSubjectRow($user, $schoolyear, 'M1', 'M', 'Mathematik 1', 2);

    $information = v3TimetableServiceInformation();
    $information['module_selection_groups'][0]['modules'][0]['courses'] = [
        ['key' => 'd1-base', 'keys' => ['d1-base'], 'title' => 'D1-Basis'],
    ];
    $information['module_selection_groups'][0]['modules'][1]['courses'] = [
        ['key' => 'm1-full-green', 'keys' => ['m1-full-green'], 'title' => 'M1-Vollgrün'],
        ['key' => 'm1-green', 'keys' => ['m1-green'], 'title' => 'M1-Grün'],
        ['key' => 'm1-conflict', 'keys' => ['m1-conflict'], 'title' => 'M1-Konflikt'],
    ];
    $occasionalGreenCourseGroup = v3TimetableServiceCourseGroup(
        'm1-green',
        'M1-GREEN',
        'M1',
        1,
        1,
    );
    $occasionalGreenCourseGroup['dates'] = ['2026-09-07'];
    $occasionalGreenCourseGroup['dates_count'] = 1;
    $service = v3TimetableService(
        $user,
        $information,
        [
            v3TimetableServiceCourseGroup('d1-base', 'D1-BASE', 'D1', 1, 1),
            v3TimetableServiceCourseGroup('m1-full-green', 'M1-FULL-GREEN', 'M1', 2, 1),
            $occasionalGreenCourseGroup,
            v3TimetableServiceCourseGroup('m1-conflict', 'M1-CONFLICT', 'M1', 1, 1),
        ],
    );
    $progress = [];

    $result = $service->createOrUpdateForUser(
        $user,
        ['additional:D1', 'additional:M1'],
        [
            'planning_mode' => 'without_student',
            'selected_course_keys' => [
                'd1-base',
                'm1-full-green',
                'm1-green',
                'm1-conflict',
            ],
        ],
        function (
            int $progressPercent,
            int $combinationCount,
            string $phase,
            int $checkedCombinationCount,
        ) use (&$progress): void {
            $progress[] = [
                $progressPercent,
                $combinationCount,
                $phase,
                $checkedCombinationCount,
                StudentTimetableV3Timetable::query()->exists(),
            ];
        },
    );
    $persistedTimetable = StudentTimetableV3Timetable::query()->sole();

    expect($result)
        ->summary->timetable_count->toBe(2)
        ->summary->timetable_variation_count->toBe(3)
        ->summary->full_green_timetable_count->toBe(1)
        ->summary->green_timetable_count->toBe(1)
        ->summary->conflict_timetable_count->toBe(1)
        ->and(array_column($progress, 0))->toBe([0, 0, ...range(5, 100, 5)])
        ->and(array_column($progress, 2))->toBe([
            'preparing',
            ...array_fill(0, 17, 'checking'),
            'materializing',
            'compacting',
            'persisting',
            'complete',
        ])
        ->and(collect($progress)->pluck(1)->unique()->values()->all())->toBe([0, 3])
        ->and(collect($progress)->firstWhere(0, 95)[4])->toBeFalse()
        ->and(collect($progress)->firstWhere(0, 100)[4])->toBeTrue()
        ->and(array_column($result['timetables'], 'type'))->toBe(['full_green', 'green'])
        ->and($persistedTimetable->summary)->toMatchArray([
            'timetable_count' => 2,
            'timetable_variation_count' => 3,
            'full_green_timetable_count' => 1,
            'green_timetable_count' => 1,
            'conflict_timetable_count' => 1,
        ])
        ->and(array_column($persistedTimetable->timetables['timetables'], 'type'))->toBe(['full_green', 'green']);
});

it('caps persisted timetables at 2000 while returning fixed pages of 100', function () {
    [$user, $schoolyear] = v3TimetableServiceUser();
    v3TimetableServiceSubjectRow($user, $schoolyear, 'D1', 'D', 'Deutsch 1', 1);

    $courseGroup = v3TimetableServiceCourseGroup('d1-a', 'D1-A', 'D1', 1, 1);
    $lesson = [
        'key' => 'D1',
        'code' => 'D1',
        'name' => 'Deutsch 1',
        'sourceLabel' => 'D1 A',
        'alternativeLabels' => ['D1 A'],
        'courseGroup' => [
            ...$courseGroup,
            'shared_details' => str_repeat('x', 18_000),
        ],
        'dateRangeLabel' => '',
        'conflicts' => [],
        'isOccasional' => false,
        'isAdditionalCourse' => false,
        'isDistanceLearningCourse' => false,
    ];
    $timetables = collect(range(1, 2001))
        ->map(fn (int $number): array => [
            'key' => "backend-full_green-{$number}",
            'number' => $number,
            'type' => 'full_green',
            'metrics' => ['regular_conflict_count' => 0],
            'statusMessage' => 'Voller grüner Stundenplan',
            'additionalCoursesAccepted' => false,
            'acceptedAdditionalCourseCount' => 0,
            'missingAdditionalCourses' => [],
            'qualityCriteria' => [],
            'slots' => ['1-1' => $lesson],
            'occasionalAppointments' => [],
            'problems' => [],
        ])
        ->all();
    $backendSetupService = Mockery::mock(RobotTimetableBackendSetupService::class);
    $backendSetupService
        ->shouldReceive('calculateAllPossibleTimetableVariations')
        ->once()
        ->andReturn([
            'timetable_variation_count' => 2500,
            'full_green_timetable_count' => 2500,
            'green_timetable_count' => 0,
            'red_timetable_count' => 0,
            'problem_courses' => [],
            'timetables' => $timetables,
        ]);
    $service = v3TimetableService(
        $user,
        v3TimetableServiceInformation(includeMathematics: false),
        [$courseGroup],
        backendSetupService: $backendSetupService,
    );

    expect(strlen(json_encode($timetables, JSON_THROW_ON_ERROR)))->toBeGreaterThan(8_388_608);

    $created = $service->createOrUpdateForUser($user, ['additional:D1'], [
        'planning_mode' => 'without_student',
        'selected_course_keys' => ['d1-a'],
    ]);
    $persistedTimetables = StudentTimetableV3Timetable::query()->sole()->timetables;
    $reused = $service->createOrUpdateForUser($user, ['additional:D1'], [
        'planning_mode' => 'without_student',
        'selected_course_keys' => ['d1-a'],
    ]);
    $persistedTimetable = StudentTimetableV3Timetable::query()->sole();
    $storedPayload = $persistedTimetable->getRawOriginal('timetables');
    $storedFingerprint = $persistedTimetable->getRawOriginal('fingerprint');
    $storedGeneratedAt = $persistedTimetable->getRawOriginal('generated_at');
    $storedUpdatedAt = $persistedTimetable->getRawOriginal('updated_at');
    $storedExpiresAt = $persistedTimetable->getRawOriginal('expires_at');
    $secondPage = v3TimetableReadService(
        $user,
        v3TimetableServiceInformation(includeMathematics: false),
        [$courseGroup],
    )->resultForUser(
        $user,
        ['planning_mode' => 'without_student'],
        2,
        $created['fingerprint'],
    );
    $twentiethPage = v3TimetableReadService(
        $user,
        v3TimetableServiceInformation(includeMathematics: false),
        [$courseGroup],
    )->resultForUser(
        $user,
        ['planning_mode' => 'without_student'],
        20,
        $created['fingerprint'],
    );
    $freshTimetable = $persistedTimetable->fresh();

    expect($created)
        ->summary->timetable_count->toBe(2000)
        ->summary->possible_timetable_count->toBe(2500)
        ->summary->timetables_truncated->toBeTrue()
        ->timetables->toEqual(array_slice($timetables, 0, 100))
        ->timetables_meta->toMatchArray([
            'current_page' => 1,
            'per_page' => 100,
            'last_page' => 20,
            'total' => 2000,
            'offset' => 0,
            'from' => 1,
            'to' => 100,
        ])
        ->and($persistedTimetables['storage_version'])->toBe(1)
        ->and($persistedTimetables['lessons'])->toHaveCount(1)
        ->and($persistedTimetables['timetables'])->toHaveCount(2000)
        ->and(strlen(json_encode($persistedTimetables, JSON_THROW_ON_ERROR)))->toBeLessThan(8_388_608)
        ->and($reused['status'])->toBe('reused')
        ->and($reused['timetables'])->toEqual(array_slice($timetables, 0, 100))
        ->and($secondPage['timetables'])->toEqual(array_slice($timetables, 100, 100))
        ->and(array_column($secondPage['timetables'], 'number'))->toBe(range(101, 200))
        ->and($secondPage['timetables_meta'])->toMatchArray([
            'current_page' => 2,
            'per_page' => 100,
            'last_page' => 20,
            'total' => 2000,
            'offset' => 100,
            'from' => 101,
            'to' => 200,
        ])
        ->and(array_column($twentiethPage['timetables'], 'number'))->toBe(range(1901, 2000))
        ->and($twentiethPage['timetables_meta'])->toMatchArray([
            'current_page' => 20,
            'per_page' => 100,
            'last_page' => 20,
            'total' => 2000,
            'offset' => 1900,
            'from' => 1901,
            'to' => 2000,
        ])
        ->and($freshTimetable?->getRawOriginal('timetables'))->toBe($storedPayload)
        ->and($freshTimetable?->getRawOriginal('fingerprint'))->toBe($storedFingerprint)
        ->and($freshTimetable?->getRawOriginal('generated_at'))->toBe($storedGeneratedAt)
        ->and($freshTimetable?->getRawOriginal('updated_at'))->toBe($storedUpdatedAt)
        ->and($freshTimetable?->getRawOriginal('expires_at'))->toBe($storedExpiresAt)
        ->and(v3TimetableReadService()->resultForUser(
            $user,
            ['planning_mode' => 'without_student'],
            2,
            str_repeat('f', 64),
        ))->toBeNull();
});

it('keeps the eight MiB compact storage guard with the higher timetable limit', function () {
    [$user, $schoolyear] = v3TimetableServiceUser();
    v3TimetableServiceSubjectRow($user, $schoolyear, 'D1', 'D', 'Deutsch 1', 1);

    $courseGroup = v3TimetableServiceCourseGroup('d1-a', 'D1-A', 'D1', 1, 1);
    $backendSetupService = Mockery::mock(RobotTimetableBackendSetupService::class);
    $backendSetupService
        ->shouldReceive('calculateAllPossibleTimetableVariations')
        ->once()
        ->andReturn([
            'timetable_variation_count' => 1,
            'full_green_timetable_count' => 1,
            'green_timetable_count' => 0,
            'red_timetable_count' => 0,
            'problem_courses' => [],
            'timetables' => [[
                'key' => 'oversized-full-green-1',
                'number' => 1,
                'type' => 'full_green',
                'statusMessage' => str_repeat('x', 8_388_608),
                'slots' => [],
            ]],
        ]);
    $service = v3TimetableService(
        $user,
        v3TimetableServiceInformation(includeMathematics: false),
        [$courseGroup],
        backendSetupService: $backendSetupService,
    );

    expect(fn () => $service->createOrUpdateForUser($user, ['additional:D1'], [
        'planning_mode' => 'without_student',
        'selected_course_keys' => ['d1-a'],
    ]))->toThrow(ValidationException::class)
        ->and(StudentTimetableV3Timetable::query()->count())->toBe(0);
});

it('changes the possible timetable count when Saturday lessons are allowed', function () {
    $backendSetupService = new RobotTimetableBackendSetupService(
        new StudentTimetableRememberedTtEntryService,
        new TimetableDateSlotOverlapService,
    );
    $settings = v3TimetableServiceRobotSettings();
    $settings['selected_course_keys'] = ['D1'];
    $settings['constraints']['availableWeekdays'] = [1, 2, 3, 4, 5];
    $courseGroups = [
        v3TimetableServiceCourseGroup('d1-friday', 'D1-FRIDAY', 'D1', 5, 1),
        v3TimetableServiceCourseGroup('d1-saturday', 'D1-SATURDAY', 'D1', 6, 1),
    ];

    $withoutSaturday = $backendSetupService->calculateAllPossibleTimetableVariations(
        subjectRows: [v3TimetableServiceRobotSubject('D1')],
        subjectMappings: [],
        courseGroups: $courseGroups,
        settings: $settings,
        maximumTimetables: 10,
    );
    $settings['constraints']['availableWeekdays'][] = 6;
    $withSaturday = $backendSetupService->calculateAllPossibleTimetableVariations(
        subjectRows: [v3TimetableServiceRobotSubject('D1')],
        subjectMappings: [],
        courseGroups: $courseGroups,
        settings: $settings,
        maximumTimetables: 10,
    );

    expect($withoutSaturday)
        ->timetable_variation_count->toBe(1)
        ->timetables->toHaveCount(1)
        ->and($withSaturday)
        ->timetable_variation_count->toBe(2)
        ->timetables->toHaveCount(2);
});

it('keeps generated timetables isolated by user, school, and schoolyear', function () {
    [$localUser, $localSchoolyear] = v3TimetableServiceUser();
    [$foreignUser, $foreignSchoolyear] = v3TimetableServiceUser();

    v3TimetableServiceSubjectRow($localUser, $localSchoolyear, 'D1', 'D', 'Deutsch 1', 1);
    v3TimetableServiceSubjectRow($foreignUser, $foreignSchoolyear, 'D1', 'D', 'Foreign Deutsch 1', 1);

    $foreignTimetable = StudentTimetableV3Timetable::query()->create([
        'school_id' => $foreignUser->school_id,
        'schoolyear_id' => $foreignSchoolyear->id,
        'user_id' => $foreignUser->id,
        'context_key' => 'without-student',
        'planning_mode' => 'without_student',
        'student_code' => null,
        'fingerprint' => str_repeat('f', 64),
        'modules' => [['selection_key' => 'foreign:D1']],
        'parameters' => ['sentinel' => 'foreign'],
        'summary' => ['timetable_count' => 1],
        'timetables' => [['key' => 'foreign-sentinel']],
        'generated_at' => now(),
    ]);
    $service = v3TimetableService(
        $localUser,
        v3TimetableServiceInformation(includeMathematics: false),
        [v3TimetableServiceCourseGroup('d1-a', 'D1-A', 'D1', 1, 1)],
    );

    $localResult = $service->createOrUpdateForUser($localUser, ['additional:D1'], [
        'selected_course_keys' => ['d1-a'],
    ]);

    expect($localResult['id'])->not->toBe($foreignTimetable->id)
        ->and(StudentTimetableV3Timetable::query()->count())->toBe(2)
        ->and($foreignTimetable->fresh())
        ->fingerprint->toBe(str_repeat('f', 64))
        ->parameters->toBe(['sentinel' => 'foreign'])
        ->timetables->toBe([['key' => 'foreign-sentinel']]);

    $localUser->forceFill(['schoolyear_id' => $foreignSchoolyear->id]);

    expect(fn () => $service->createOrUpdateForUser($localUser, ['additional:D1'], [
        'selected_course_keys' => ['d1-a'],
    ]))->toThrow(
        HttpException::class,
        'Das ausgewählte Schuljahr gehört nicht zur ausgewählten Schule.',
    );
});

/** @return array{User, Schoolyear} */
function v3TimetableServiceUser(): array
{
    $school = School::factory()->create();
    $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
    ]);

    return [$user, $schoolyear];
}

function v3TimetableServiceSubjectRow(
    User $user,
    Schoolyear $schoolyear,
    string $code,
    string $subject,
    string $name,
    int $sortOrder,
): StudentTimetableSubjectRow {
    return StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'study_program' => StudentTimetableStudyProgram::Normalstudium,
        'semester' => 1,
        'branch' => 'common',
        'json_code' => $code,
        'json_subject' => $subject,
        'name' => $name,
        'hours_per_week' => 1,
        'is_active' => true,
        'sort_order' => $sortOrder,
        'source' => 'test',
    ]);
}

/** @return array<string, mixed> */
function v3TimetableServiceInformation(bool $includeMathematics = true): array
{
    $modules = [
        [
            'selection_key' => 'additional:D1',
            'code' => 'D1',
            'name' => 'Deutsch 1',
            'hours' => 1,
            'courses' => [
                ['key' => 'd1-a', 'keys' => ['d1-a'], 'title' => 'D1-A'],
                ['key' => 'd1-b', 'keys' => ['d1-b'], 'title' => 'D1-B'],
            ],
        ],
    ];

    if ($includeMathematics) {
        $modules[] = [
            'selection_key' => 'additional:M1',
            'code' => 'M1',
            'name' => 'Mathematik 1',
            'hours' => 1,
            'courses' => [
                ['key' => 'm1-a', 'keys' => ['m1-a'], 'title' => 'M1-A'],
                ['key' => 'm1-b', 'keys' => ['m1-b'], 'title' => 'M1-B'],
            ],
        ];
    }

    return [
        'study_program' => StudentTimetableStudyProgram::Normalstudium->value,
        'semester' => 1,
        'selection_fields' => [
            ['key' => 'religion', 'selected_value' => 'ETH'],
            ['key' => 'language', 'selected_value' => 'L'],
            ['key' => 'branch', 'selected_value' => null],
            ['key' => 'arts_subject', 'selected_value' => 'ME'],
        ],
        'module_selection_groups' => [[
            'key' => 'additional',
            'label' => 'Zusätzliche Module',
            'modules' => $modules,
        ]],
    ];
}

/** @return array<string, mixed> */
function v3TimetableServiceCourseGroup(
    string $key,
    string $label,
    string $course,
    int $weekday,
    int $hour,
): array {
    return [
        'key' => $key,
        'semester' => 1,
        'weekday' => $weekday,
        'hour' => $hour,
        'title' => $label,
        'display_label' => $label,
        'course' => $course,
        'subject' => $course,
        'module_code' => $course,
        'class_name' => $label,
        'dates' => [],
        'dates_count' => 20,
    ];
}

/**
 * @param  list<array<string, mixed>>  $courseGroups
 */
function v3TimetableService(
    User $user,
    array $information,
    array $courseGroups,
    ?string $studentCode = null,
    ?RobotTimetableBackendSetupService $backendSetupService = null,
): StudentTimetableV3TimetableService {
    $informationService = Mockery::mock(StudentTimetableV3StudentInformationService::class);
    $informationService
        ->shouldReceive('informationForStudent')
        ->with($user, $studentCode, [], true, true)
        ->andReturn($information);
    $overviewService = Mockery::mock(StudentTimetableOverviewService::class);
    $overviewService
        ->shouldReceive('courseGroupsForUser')
        ->with($user)
        ->andReturn($courseGroups);
    $rememberedTtEntryService = new StudentTimetableRememberedTtEntryService;

    return new StudentTimetableV3TimetableService(
        $informationService,
        $overviewService,
        $rememberedTtEntryService,
        new StudentTimetableCalculationSettingsService,
        $backendSetupService ?? new RobotTimetableBackendSetupService(
            $rememberedTtEntryService,
            new TimetableDateSlotOverlapService,
        ),
        new StudentTimetableV3TimetableStorage,
        app(StudentTimetableV3SessionScope::class),
    );
}

/**
 * @param  array<string, mixed>  $information
 * @param  list<array<string, mixed>>  $courseGroups
 */
function v3TimetableReadService(
    ?User $user = null,
    array $information = [],
    array $courseGroups = [],
    ?string $studentCode = null,
): StudentTimetableV3TimetableService {
    $informationService = Mockery::mock(StudentTimetableV3StudentInformationService::class);
    $overviewService = Mockery::mock(StudentTimetableOverviewService::class);
    $backendSetupService = Mockery::mock(RobotTimetableBackendSetupService::class);

    if ($user) {
        $informationService
            ->shouldReceive('informationForStudent')
            ->once()
            ->withArgs(fn (
                User $authUser,
                ?string $currentStudentCode,
                array $selection,
                bool $seedCompactSubjectPlanIfMissing,
                bool $includeAllSelectableModules,
            ): bool => $authUser->is($user)
                && $currentStudentCode === $studentCode
                && ! $seedCompactSubjectPlanIfMissing
                && $includeAllSelectableModules
                && $selection == [
                    'religion' => 'ETH',
                    'language' => 'L',
                    'branch' => null,
                    'arts_subject' => 'ME',
                ])
            ->andReturn($information);
        $overviewService
            ->shouldReceive('courseGroupsForUser')
            ->zeroOrMoreTimes()
            ->with($user)
            ->andReturn($courseGroups);
        $backendSetupService
            ->shouldNotReceive('calculateAllPossibleTimetableVariations');
    }

    $rememberedTtEntryService = new StudentTimetableRememberedTtEntryService;

    return new StudentTimetableV3TimetableService(
        $informationService,
        $overviewService,
        $rememberedTtEntryService,
        new StudentTimetableCalculationSettingsService,
        $backendSetupService,
        new StudentTimetableV3TimetableStorage,
        app(StudentTimetableV3SessionScope::class),
    );
}

/**
 * @return array{
 *     User,
 *     Schoolyear,
 *     string,
 *     array<string, mixed>,
 *     list<array<string, mixed>>,
 *     StudentTimetableV3Timetable,
 *     StudentTimetableSubjectRow
 * }
 */
function v3PersistedReadFixture(): array
{
    [$user, $schoolyear] = v3TimetableServiceUser();
    $studentCode = 'student-100';
    $subjectRow = v3TimetableServiceSubjectRow($user, $schoolyear, 'D1', 'D', 'Deutsch 1', 1);
    $information = v3TimetableServiceInformation(includeMathematics: false);
    $information['student_code'] = $studentCode;
    $courseGroups = [v3TimetableServiceCourseGroup('d1-a', 'D1-A', 'D1', 1, 1)];
    $service = v3TimetableService($user, $information, $courseGroups, $studentCode);

    $service->createOrUpdateForUser($user, ['additional:D1'], [
        'planning_mode' => 'with_student',
        'student_code' => $studentCode,
        'selected_course_keys' => ['d1-a'],
    ]);

    return [
        $user,
        $schoolyear,
        $studentCode,
        $information,
        $courseGroups,
        StudentTimetableV3Timetable::query()->sole(),
        $subjectRow,
    ];
}

/** @return array<string, mixed> */
function v3TimetableServiceRobotSubject(string $code): array
{
    return [
        'id' => $code,
        'semester' => 1,
        'branch' => 'common',
        'json_code' => $code,
        'json_subject' => preg_replace('/\d+$/', '', $code),
        'name' => $code,
        'hours_per_week' => 1,
        'is_active' => true,
    ];
}

/** @return array<string, mixed> */
function v3TimetableServiceRobotSettings(): array
{
    return [
        'selection' => [
            'semester' => 1,
            'religion' => 'ETH',
            'branch' => null,
            'artsSubject' => 'ME',
            'language' => 'L',
        ],
        'constraints' => [
            'availableWeekdays' => [1, 2, 3, 4, 5, 6],
            'availableTimes' => [1, 2],
            'excludedWeekdayTimes' => [],
        ],
        'selected_course_keys' => ['D1', 'M1'],
        'deselected_course_keys' => [],
        'selected_course_group_keys' => [],
        'deselected_course_group_keys' => [],
        'selected_additional_course_keys' => [],
        'selected_additional_courses_required' => false,
        'selected_timetable_type' => 'full_green',
        'selected_timetable_number' => 1,
    ];
}
