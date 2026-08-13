<?php

use App\Enums\StudentTimetableStudyProgram;
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
use App\Services\StudentsTimetables\StudentTimetableV3StudentInformationService;
use App\Services\StudentsTimetables\StudentTimetableV3TimetableService;
use App\Services\StudentsTimetables\TimetableDateSlotOverlapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        ->timetables->toEqual($timetable->timetables)
        ->and($freshTimetable?->getRawOriginal('fingerprint'))->toBe($storedFingerprint)
        ->and($freshTimetable?->getRawOriginal('generated_at'))->toBe($storedGeneratedAt)
        ->and($freshTimetable?->getRawOriginal('updated_at'))->toBe($storedUpdatedAt)
        ->and(StudentTimetableV3Timetable::query()->count())->toBe(1);
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
        'context_key' => 'without-student',
        'planning_mode' => 'without_student',
        'student_code' => null,
        'fingerprint' => str_repeat('c', 64),
        'modules' => [['selection_key' => 'additional:Rev2', 'code' => 'Rev2']],
        'parameters' => ['planning_mode' => 'without_student'],
        'summary' => $summary,
        'timetables' => [['number' => 1]],
        'generated_at' => now()->subMinute(),
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
    'older version' => [['algorithm_version' => 2, 'timetable_count' => 18]],
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
        ->summary->algorithm_version->toBe(3)
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
        ->and($record->timetables)->toHaveCount(4);

    $updated = $service->createOrUpdateForUser($user, $modules, [
        ...$parameters,
        'selected_course_keys' => ['d1-a', 'm1-a', 'm1-b'],
    ]);

    expect($updated)
        ->id->toBe($created['id'])
        ->status->toBe('updated')
        ->reused->toBeFalse()
        ->summary->algorithm_version->toBe(3)
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
        ->and($record->timetables)->toEqual($created['timetables']);
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

    $result = $service->createOrUpdateForUser($user, ['additional:D1', 'additional:M1'], [
        'planning_mode' => 'without_student',
        'selected_course_keys' => ['d1-a', 'm1-a'],
    ]);
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
        ->and($record->timetables)->toBe([]);
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

    expect(fn () => $backendSetupService->calculateAllPossibleTimetableVariations(
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
    ))->toThrow(ValidationException::class);
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
    );
    $persistedTimetable = StudentTimetableV3Timetable::query()->sole();

    expect($result)
        ->summary->timetable_count->toBe(2)
        ->summary->timetable_variation_count->toBe(3)
        ->summary->full_green_timetable_count->toBe(1)
        ->summary->green_timetable_count->toBe(1)
        ->summary->conflict_timetable_count->toBe(1)
        ->and(array_column($result['timetables'], 'type'))->toBe(['full_green', 'green'])
        ->and($persistedTimetable->summary)->toMatchArray([
            'timetable_count' => 2,
            'timetable_variation_count' => 3,
            'full_green_timetable_count' => 1,
            'green_timetable_count' => 1,
            'conflict_timetable_count' => 1,
        ])
        ->and(array_column($persistedTimetable->timetables, 'type'))->toBe(['full_green', 'green']);
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
): StudentTimetableV3TimetableService {
    $informationService = Mockery::mock(StudentTimetableV3StudentInformationService::class);
    $informationService
        ->shouldReceive('informationForStudent')
        ->with($user, $studentCode, [], true)
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
        new RobotTimetableBackendSetupService(
            $rememberedTtEntryService,
            new TimetableDateSlotOverlapService,
        ),
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
            ): bool => $authUser->is($user)
                && $currentStudentCode === $studentCode
                && ! $seedCompactSubjectPlanIfMissing
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
