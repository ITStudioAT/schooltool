<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\StudentsTimetables\StudentTimetableV3TimetableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

const V3_WORKSPACE_ID = '11111111-1111-4111-8111-111111111111';

it('returns the persisted v3 timetable result for the validated planning context', function (
    string $query,
    array $expectedParameters,
    ?array $serviceResult,
    int $expectedPage,
    ?string $expectedFingerprint,
) {
    [$user, $schoolyear] = v3TimetableControllerUser();
    $service = mock(StudentTimetableV3TimetableService::class);
    $service
        ->shouldReceive('resultForUser')
        ->once()
        ->withArgs(fn (
            User $authUser,
            array $parameters,
            int $page,
            ?string $fingerprint,
        ): bool => $authUser->is($user)
            && (int) $authUser->schoolyear_id === $schoolyear->id
            && $parameters === $expectedParameters
            && $page === $expectedPage
            && $fingerprint === $expectedFingerprint)
        ->andReturn($serviceResult);

    $response = $this->actingAs($user)
        ->getJson("/api/admin/students-timetables/timetable-v3/timetable?{$query}");

    $response
        ->assertSuccessful()
        ->assertExactJson(['data' => $serviceResult]);

    expect((string) $response->headers->get('Cache-Control'))
        ->toContain('private', 'no-store');
})->with([
    'without student, found' => [
        'workspace_id='.V3_WORKSPACE_ID.'&planning_mode=without_student',
        ['workspace_id' => V3_WORKSPACE_ID, 'planning_mode' => 'without_student', 'student_code' => null],
        [
            'id' => 42,
            'status' => 'reused',
            'reused' => true,
            'modules' => [['selection_key' => 'additional:D1']],
            'parameters' => ['planning_mode' => 'without_student'],
            'summary' => ['algorithm_version' => 7, 'timetable_count' => 0],
            'timetables' => [],
            'timetables_meta' => [
                'current_page' => 1,
                'per_page' => 100,
                'last_page' => 1,
                'total' => 0,
            ],
        ],
        1,
        null,
    ],
    'with student, none' => [
        'workspace_id='.V3_WORKSPACE_ID.'&planning_mode=with_student&student_code=student-100',
        ['workspace_id' => V3_WORKSPACE_ID, 'planning_mode' => 'with_student', 'student_code' => 'student-100'],
        null,
        1,
        null,
    ],
    'second page with snapshot fingerprint' => [
        'workspace_id='.V3_WORKSPACE_ID.'&planning_mode=without_student&page=2&fingerprint='.str_repeat('a', 64),
        ['workspace_id' => V3_WORKSPACE_ID, 'planning_mode' => 'without_student', 'student_code' => null],
        [
            'fingerprint' => str_repeat('a', 64),
            'timetables' => [['number' => 101]],
            'timetables_meta' => [
                'current_page' => 2,
                'per_page' => 100,
                'last_page' => 5,
                'total' => 500,
            ],
        ],
        2,
        str_repeat('a', 64),
    ],
    'twentieth page with snapshot fingerprint' => [
        'workspace_id='.V3_WORKSPACE_ID.'&planning_mode=without_student&page=20&fingerprint='.str_repeat('b', 64),
        ['workspace_id' => V3_WORKSPACE_ID, 'planning_mode' => 'without_student', 'student_code' => null],
        [
            'fingerprint' => str_repeat('b', 64),
            'timetables' => [['number' => 1901]],
            'timetables_meta' => [
                'current_page' => 20,
                'per_page' => 100,
                'last_page' => 20,
                'total' => 2000,
            ],
        ],
        20,
        str_repeat('b', 64),
    ],
]);

it('validates the v3 timetable result planning context', function (string $query, array $errors) {
    [$user] = v3TimetableControllerUser();

    mock(StudentTimetableV3TimetableService::class)
        ->shouldNotReceive('resultForUser');

    $this->actingAs($user)
        ->getJson("/api/admin/students-timetables/timetable-v3/timetable?{$query}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errors);
})->with([
    'with student requires student code' => [
        'workspace_id='.V3_WORKSPACE_ID.'&planning_mode=with_student',
        ['student_code'],
    ],
    'without student prohibits student code' => [
        'workspace_id='.V3_WORKSPACE_ID.'&planning_mode=without_student&student_code=student-100',
        ['student_code'],
    ],
    'workspace is required' => ['planning_mode=without_student', ['workspace_id']],
    'planning mode is required' => ['workspace_id='.V3_WORKSPACE_ID, ['planning_mode']],
    'planning mode is restricted' => [
        'workspace_id='.V3_WORKSPACE_ID.'&planning_mode=unknown',
        ['planning_mode'],
    ],
    'page starts at one' => [
        'workspace_id='.V3_WORKSPACE_ID.'&planning_mode=without_student&page=0',
        ['page'],
    ],
    'page does not exceed persisted result pages' => [
        'workspace_id='.V3_WORKSPACE_ID.'&planning_mode=without_student&page=21',
        ['page'],
    ],
    'page must be an integer' => [
        'workspace_id='.V3_WORKSPACE_ID.'&planning_mode=without_student&page=1.5',
        ['page'],
    ],
    'page must not be an array' => [
        'workspace_id='.V3_WORKSPACE_ID.'&planning_mode=without_student&page[]=1',
        ['page'],
    ],
    'fingerprint is restricted to a sha-256 value' => [
        'workspace_id='.V3_WORKSPACE_ID.'&planning_mode=without_student&fingerprint=invalid',
        ['fingerprint'],
    ],
    'page size cannot be controlled by the client' => [
        'workspace_id='.V3_WORKSPACE_ID.'&planning_mode=without_student&per_page=500',
        ['per_page'],
    ],
    'follow-up pages require a snapshot fingerprint' => [
        'workspace_id='.V3_WORKSPACE_ID.'&planning_mode=without_student&page=2',
        ['fingerprint'],
    ],
]);

it('requires authentication for loading a persisted v3 timetable result', function () {
    $this->getJson('/api/admin/students-timetables/timetable-v3/timetable?workspace_id='.V3_WORKSPACE_ID.'&planning_mode=without_student')
        ->assertUnauthorized();
});

it('forbids loading a persisted v3 timetable result without a students timetables moderator role', function () {
    [$user] = v3TimetableControllerUser(roleName: 'teacher');

    mock(StudentTimetableV3TimetableService::class)
        ->shouldNotReceive('resultForUser');

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/timetable-v3/timetable?workspace_id='.V3_WORKSPACE_ID.'&planning_mode=without_student')
        ->assertForbidden();
});

it('calculates v3 timetables with server-side weekday constraints', function (bool $allowSaturdayLessons, array $availableWeekdays) {
    [$user, $schoolyear] = v3TimetableControllerUser();
    $serviceResult = [
        'id' => 42,
        'status' => 'created',
        'summary' => [
            'timetable_count' => 2,
            'conflict_timetable_count' => 1,
        ],
        'timetables' => [
            ['type' => 'full_green', 'number' => 1],
            ['type' => 'green', 'number' => 1],
        ],
    ];
    $service = mock(StudentTimetableV3TimetableService::class);
    $service
        ->shouldReceive('createOrUpdateForUser')
        ->once()
        ->withArgs(function (User $authUser, array $modules, array $parameters) use ($user, $schoolyear, $availableWeekdays): bool {
            return $authUser->is($user)
                && (int) $authUser->schoolyear_id === $schoolyear->id
                && $modules === ['additional:D1']
                && $parameters === [
                    'workspace_id' => V3_WORKSPACE_ID,
                    'planning_mode' => 'without_student',
                    'student_code' => null,
                    'selection' => [
                        'religion' => 'ETH',
                        'language' => 'L',
                        'branch' => null,
                        'arts_subject' => 'ME',
                    ],
                    'selected_course_keys' => ['d1-a'],
                    'constraints' => ['availableWeekdays' => $availableWeekdays],
                ];
        })
        ->andReturn($serviceResult);

    $this->actingAs($user)
        ->putJson('/api/admin/students-timetables/timetable-v3/timetable', [
            'modules' => ['additional:D1'],
            'parameters' => [
                'workspace_id' => V3_WORKSPACE_ID,
                'planning_mode' => 'without_student',
                'student_code' => null,
                'selection' => [
                    'religion' => 'ETH',
                    'language' => 'L',
                    'branch' => null,
                    'arts_subject' => 'ME',
                ],
                'selected_course_keys' => ['d1-a'],
            ],
            'options' => ['allow_saturday_lessons' => $allowSaturdayLessons],
        ])
        ->assertSuccessful()
        ->assertJsonPath('message', 'Die möglichen Stundenpläne wurden berechnet.')
        ->assertJsonPath('data.id', 42)
        ->assertJsonPath('data.summary.timetable_count', 2)
        ->assertJsonCount(2, 'data.timetables');

    expect($user->refresh()->schoolyear_id)->toBe($schoolyear->id);
})->with([
    'Monday to Friday' => [false, [1, 2, 3, 4, 5]],
    'Monday to Saturday' => [true, [1, 2, 3, 4, 5, 6]],
]);

it('streams the combination count, five-percent progress, and completed result', function () {
    [$user] = v3TimetableControllerUser();
    $serviceResult = [
        'id' => 42,
        'status' => 'created',
        'summary' => [
            'timetable_count' => 500,
            'possible_timetable_count' => 1420,
            'timetables_truncated' => true,
        ],
        'timetables' => collect(range(1, 100))
            ->map(fn (int $number): array => ['number' => $number])
            ->all(),
        'timetables_meta' => [
            'current_page' => 1,
            'per_page' => 100,
            'last_page' => 5,
            'total' => 500,
        ],
    ];
    mock(StudentTimetableV3TimetableService::class)
        ->shouldReceive('createOrUpdateForUser')
        ->once()
        ->andReturnUsing(function (
            User $authUser,
            array $modules,
            array $parameters,
            callable $progressCallback,
        ) use ($user, $serviceResult): array {
            expect($authUser->is($user))->toBeTrue()
                ->and($modules)->toBe(['additional:D1'])
                ->and($parameters['constraints']['availableWeekdays'])->toBe([1, 2, 3, 4, 5]);

            $progressCallback(0, 0, 'preparing', 0);
            $progressCallback(40, 1420, 'checking', 710);
            $progressCallback(95, 1420, 'persisting', 1420);
            $progressCallback(100, 1420, 'complete', 1420);

            return $serviceResult;
        });

    $response = $this->actingAs($user)
        ->withHeader('X-Timetable-Progress', 'stream')
        ->putJson(
            '/api/admin/students-timetables/timetable-v3/timetable',
            v3TimetableControllerPayload(),
        );

    $response->assertSuccessful()
        ->assertStreamed()
        ->assertHeader('Content-Type', 'application/x-ndjson; charset=UTF-8')
        ->assertHeader('X-Accel-Buffering', 'no');

    expect((string) $response->headers->get('Cache-Control'))
        ->toContain('no-cache', 'no-transform');

    $events = collect(preg_split('/\R/', trim($response->streamedContent())))
        ->map(fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR));

    expect($events->pluck('type')->all())->toBe(['progress', 'progress', 'progress', 'progress', 'complete'])
        ->and($events->where('type', 'progress')->pluck('progress_percent')->all())->toBe([0, 40, 95, 100])
        ->and($events->where('type', 'progress')->pluck('phase')->all())->toBe([
            'preparing',
            'checking',
            'persisting',
            'complete',
        ])
        ->and($events->where('type', 'progress')->pluck('checked_combination_count')->all())
        ->toBe([0, 710, 1420, 1420])
        ->and($events->first()['combination_count'])->toBe(0)
        ->and($events->last()['data']['summary'])->toMatchArray([
            'timetable_count' => 500,
            'possible_timetable_count' => 1420,
            'timetables_truncated' => true,
        ])
        ->and($events->last()['data']['timetables'])->toHaveCount(100)
        ->and(array_column($events->last()['data']['timetables'], 'number'))->toBe(range(1, 100))
        ->and($events->last()['data']['timetables_meta'])->toMatchArray([
            'current_page' => 1,
            'per_page' => 100,
            'last_page' => 5,
            'total' => 500,
        ]);
});

it('streams a safe error without reporting completion when persistence fails', function () {
    [$user] = v3TimetableControllerUser();
    mock(StudentTimetableV3TimetableService::class)
        ->shouldReceive('createOrUpdateForUser')
        ->once()
        ->andReturnUsing(function (
            User $authUser,
            array $modules,
            array $parameters,
            callable $progressCallback,
        ): never {
            $progressCallback(95, 1420, 'persisting', 1420);

            throw new RuntimeException('private database details');
        });

    $response = $this->actingAs($user)
        ->withHeader('X-Timetable-Progress', 'stream')
        ->putJson(
            '/api/admin/students-timetables/timetable-v3/timetable',
            v3TimetableControllerPayload(),
        );

    $events = collect(preg_split('/\R/', trim($response->streamedContent())))
        ->map(fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR));

    expect($events->pluck('type')->all())->toBe(['progress', 'error'])
        ->and($events->first())->toMatchArray([
            'progress_percent' => 95,
            'phase' => 'persisting',
            'checked_combination_count' => 1420,
        ])
        ->and($events->last()['message'])->toBe('Bitte versuchen Sie die Berechnung erneut.')
        ->and($response->streamedContent())->not->toContain('private database details')
        ->and($events->pluck('progress_percent')->filter()->contains(100))->toBeFalse();
});

it('validates the strict v3 timetable payload and planning mode context', function () {
    [$user] = v3TimetableControllerUser();

    mock(StudentTimetableV3TimetableService::class)
        ->shouldNotReceive('createOrUpdateForUser');

    $this->actingAs($user)
        ->putJson('/api/admin/students-timetables/timetable-v3/timetable', [
            'modules' => ['additional:D1', 'additional:D1'],
            'parameters' => [
                'workspace_id' => V3_WORKSPACE_ID,
                'planning_mode' => 'with_student',
                'student_code' => null,
                'selection' => [
                    'religion' => null,
                    'unexpected' => 'value',
                ],
                'selected_course_keys' => [],
            ],
            'options' => [
                'allow_saturday_lessons' => false,
                'unexpected' => true,
            ],
            'unexpected' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'modules.1',
            'parameters.student_code',
            'parameters.selection',
            'parameters.selected_course_keys',
            'options',
            'payload',
        ]);
});

it('requires authentication for v3 timetable calculation', function () {
    $this->putJson('/api/admin/students-timetables/timetable-v3/timetable', [])
        ->assertUnauthorized();
});

it('forbids authenticated users without a students timetables moderator role', function () {
    [$user] = v3TimetableControllerUser(roleName: 'teacher');

    mock(StudentTimetableV3TimetableService::class)
        ->shouldNotReceive('createOrUpdateForUser');

    $this->actingAs($user)
        ->putJson('/api/admin/students-timetables/timetable-v3/timetable', v3TimetableControllerPayload())
        ->assertForbidden();
});

it('forbids v3 timetable calculation without a school licence', function () {
    [$user] = v3TimetableControllerUser(withLicence: false);

    mock(StudentTimetableV3TimetableService::class)
        ->shouldNotReceive('createOrUpdateForUser');

    $this->actingAs($user)
        ->putJson('/api/admin/students-timetables/timetable-v3/timetable', v3TimetableControllerPayload())
        ->assertForbidden();
});

/** @return array{User, Schoolyear} */
function v3TimetableControllerUser(
    string $roleName = 'studentstimetables_moderator',
    bool $withLicence = true,
): array {
    $school = School::factory()->create();
    $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

    SchoolTool::factory()->create([
        'school_id' => $school->id,
        'active_schoolyear_id' => $schoolyear->id,
        'students_timetables_visible_admin' => true,
        'students_timetables_visible_user' => true,
        'students_timetables_user_test_mode' => false,
        'students_timetables_user_comming_soon' => false,
    ]);
    $licence = Licence::query()->create([
        'name' => 'StudentsTimetables',
        'long_name' => 'Tool zum Verwalten von Schülerstundenplänen',
        'price_per_year' => 200,
    ]);
    if ($withLicence) {
        SchoolLicence::query()->create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => now()->addMonth()->toDateString(),
        ]);
    }

    Role::firstOrCreate([
        'name' => $roleName,
        'guard_name' => 'web',
    ]);
    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => null,
    ]);
    $user->assignRole($roleName);

    return [$user, $schoolyear];
}

/** @return array<string, mixed> */
function v3TimetableControllerPayload(): array
{
    return [
        'modules' => ['additional:D1'],
        'parameters' => [
            'workspace_id' => V3_WORKSPACE_ID,
            'planning_mode' => 'without_student',
            'student_code' => null,
            'selection' => [
                'religion' => 'ETH',
                'language' => 'L',
                'branch' => null,
                'arts_subject' => 'ME',
            ],
            'selected_course_keys' => ['d1-a'],
        ],
        'options' => ['allow_saturday_lessons' => false],
    ];
}
