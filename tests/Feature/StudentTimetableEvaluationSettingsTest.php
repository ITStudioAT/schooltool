<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\StudentTimetableEvaluationSetting;
use App\Models\User;
use App\Services\StudentsTimetables\StudentTimetableEvaluationSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('stores timetable evaluation settings for a schoolyear', function () {
    $school = School::factory()->create();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $school->id,
    ]);

    $evaluationSetting = StudentTimetableEvaluationSetting::create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'settings' => [
            'result_weights' => [
                'full_green' => 100,
                'green' => 60,
            ],
        ],
    ]);

    expect($evaluationSetting->settings)
        ->toBe([
            'result_weights' => [
                'full_green' => 100,
                'green' => 60,
            ],
        ])
        ->and($evaluationSetting->school->is($school))->toBeTrue()
        ->and($evaluationSetting->schoolyear->is($schoolyear))->toBeTrue();

    $this->assertDatabaseHas('student_timetable_evaluation_settings', [
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
    ]);
});

it('returns default timetable evaluation settings for the active schoolyear', function () {
    $user = createEvaluationSettingsUser();

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/evaluation-settings')
        ->assertSuccessful()
        ->assertJsonCount(7, 'data.criteria')
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.criteria.0.key', 'saturday_free')
        ->assertJsonPath('data.criteria.0.enabled', false)
        ->assertJsonPath('data.criteria.0.option', null)
        ->assertJsonPath('data.criteria.0.options', [])
        ->assertJsonPath('data.criteria.1.key', 'prefer_distance_learning')
        ->assertJsonPath('data.criteria.2.key', 'avoid_distance_learning')
        ->assertJsonPath('data.criteria.6.key', 'ends_by_period_13');
});

it('persists timetable evaluation settings with priorities and options', function () {
    $user = createEvaluationSettingsUser();

    $payload = [
        'criteria' => [
            [
                'key' => 'few_gaps',
                'enabled' => true,
                'priority' => 1,
            ],
            [
                'key' => 'saturday_free',
                'enabled' => true,
                'priority' => 2,
            ],
            [
                'key' => 'prefer_distance_learning',
                'enabled' => false,
                'priority' => 3,
            ],
            [
                'key' => 'avoid_distance_learning',
                'enabled' => false,
                'priority' => 4,
            ],
            [
                'key' => 'free_days',
                'enabled' => true,
                'priority' => 5,
            ],
            [
                'key' => 'starts_from_period_10',
                'enabled' => false,
                'priority' => 6,
            ],
            [
                'key' => 'ends_by_period_13',
                'enabled' => true,
                'priority' => 7,
            ],
        ],
    ];

    $this->actingAs($user)
        ->putJson('/api/admin/students-timetables/evaluation-settings', $payload)
        ->assertSuccessful()
        ->assertJsonPath('message', 'Bewertungseinstellungen wurden gespeichert.')
        ->assertJsonPath('data.criteria.0.key', 'few_gaps')
        ->assertJsonPath('data.criteria.1.key', 'saturday_free')
        ->assertJsonPath('data.criteria.1.option', null)
        ->assertJsonPath('data.criteria.6.key', 'ends_by_period_13');

    $settings = StudentTimetableEvaluationSetting::query()
        ->where('school_id', $user->school_id)
        ->where('schoolyear_id', $user->schoolyear_id)
        ->where('user_id', $user->id)
        ->firstOrFail()
        ->settings;

    expect($settings['criteria'])
        ->sequence(
            fn ($criterion) => $criterion
                ->key->toBe('few_gaps')
                ->enabled->toBeTrue()
                ->priority->toBe(1),
            fn ($criterion) => $criterion
                ->key->toBe('saturday_free')
                ->enabled->toBeTrue()
                ->priority->toBe(2)
                ->option->toBeNull(),
            fn ($criterion) => $criterion
                ->key->toBe('prefer_distance_learning')
                ->enabled->toBeFalse()
                ->priority->toBe(3),
            fn ($criterion) => $criterion
                ->key->toBe('avoid_distance_learning')
                ->enabled->toBeFalse()
                ->priority->toBe(4),
            fn ($criterion) => $criterion
                ->key->toBe('free_days')
                ->enabled->toBeTrue()
                ->priority->toBe(5),
            fn ($criterion) => $criterion
                ->key->toBe('starts_from_period_10')
                ->enabled->toBeFalse()
                ->priority->toBe(6),
            fn ($criterion) => $criterion
                ->key->toBe('ends_by_period_13')
                ->enabled->toBeTrue()
                ->priority->toBe(7),
        );
});

it('keeps timetable evaluation settings separate for each user', function () {
    $user = createEvaluationSettingsUser();
    $otherUser = User::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $user->schoolyear_id,
    ]);
    $otherUser->assignRole('admin');

    $this->actingAs($user)
        ->putJson('/api/admin/students-timetables/evaluation-settings', [
            'criteria' => [
                ['key' => 'saturday_free', 'enabled' => true, 'priority' => 1],
                ['key' => 'prefer_distance_learning', 'enabled' => false, 'priority' => 2],
                ['key' => 'avoid_distance_learning', 'enabled' => false, 'priority' => 3],
                ['key' => 'free_days', 'enabled' => false, 'priority' => 4],
                ['key' => 'few_gaps', 'enabled' => false, 'priority' => 5],
                ['key' => 'starts_from_period_10', 'enabled' => false, 'priority' => 6],
                ['key' => 'ends_by_period_13', 'enabled' => false, 'priority' => 7],
            ],
        ])
        ->assertSuccessful();

    $this->actingAs($otherUser)
        ->putJson('/api/admin/students-timetables/evaluation-settings', [
            'criteria' => [
                ['key' => 'saturday_free', 'enabled' => false, 'priority' => 1],
                ['key' => 'prefer_distance_learning', 'enabled' => false, 'priority' => 2],
                ['key' => 'avoid_distance_learning', 'enabled' => false, 'priority' => 3],
                ['key' => 'free_days', 'enabled' => true, 'priority' => 4],
                ['key' => 'few_gaps', 'enabled' => false, 'priority' => 5],
                ['key' => 'starts_from_period_10', 'enabled' => false, 'priority' => 6],
                ['key' => 'ends_by_period_13', 'enabled' => false, 'priority' => 7],
            ],
        ])
        ->assertSuccessful();

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/evaluation-settings')
        ->assertSuccessful()
        ->assertJsonPath('data.criteria.0.key', 'saturday_free')
        ->assertJsonPath('data.criteria.0.enabled', true)
        ->assertJsonPath('data.criteria.3.key', 'free_days')
        ->assertJsonPath('data.criteria.3.enabled', false);

    $this->actingAs($otherUser)
        ->getJson('/api/admin/students-timetables/evaluation-settings')
        ->assertSuccessful()
        ->assertJsonPath('data.criteria.0.key', 'saturday_free')
        ->assertJsonPath('data.criteria.0.enabled', false)
        ->assertJsonPath('data.criteria.3.key', 'free_days')
        ->assertJsonPath('data.criteria.3.enabled', true);

    expect(StudentTimetableEvaluationSetting::query()
        ->where('school_id', $user->school_id)
        ->where('schoolyear_id', $user->schoolyear_id)
        ->count())->toBe(2);
});

it('uses the school active schoolyear for user evaluation settings when no user schoolyear is selected', function () {
    $user = createEvaluationSettingsUser();
    $schoolyearId = $user->schoolyear_id;
    $user->forceFill(['schoolyear_id' => null])->save();

    SchoolTool::query()
        ->where('school_id', $user->school_id)
        ->update(['active_schoolyear_id' => $schoolyearId]);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/evaluation-settings')
        ->assertSuccessful()
        ->assertJsonCount(7, 'data.criteria');
});

it('allows students timetables moderators to save their own evaluation settings', function () {
    $user = createEvaluationSettingsUser();
    Role::firstOrCreate([
        'name' => 'studentstimetables_moderator',
        'guard_name' => 'web',
    ]);
    $user->syncRoles(['studentstimetables_moderator']);

    $this->actingAs($user)
        ->putJson('/api/admin/students-timetables/evaluation-settings', [
            'criteria' => [
                ['key' => 'saturday_free', 'enabled' => true, 'priority' => 1],
                ['key' => 'prefer_distance_learning', 'enabled' => false, 'priority' => 2],
                ['key' => 'avoid_distance_learning', 'enabled' => false, 'priority' => 3],
                ['key' => 'free_days', 'enabled' => false, 'priority' => 4],
                ['key' => 'few_gaps', 'enabled' => false, 'priority' => 5],
                ['key' => 'starts_from_period_10', 'enabled' => false, 'priority' => 6],
                ['key' => 'ends_by_period_13', 'enabled' => false, 'priority' => 7],
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.criteria.0.key', 'saturday_free')
        ->assertJsonPath('data.criteria.0.enabled', true);
});

it('builds active run criteria from a submitted payload without persisting it', function () {
    $user = createEvaluationSettingsUser();

    StudentTimetableEvaluationSetting::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $user->schoolyear_id,
        'settings' => [
            'criteria' => [
                [
                    'key' => 'saturday_free',
                    'enabled' => true,
                    'priority' => 1,
                    'option' => null,
                ],
                [
                    'key' => 'free_days',
                    'enabled' => false,
                    'priority' => 2,
                    'option' => null,
                ],
            ],
        ],
    ]);

    $criteria = (new StudentTimetableEvaluationSettingsService)->activeCriteriaForRun([
        [
            'key' => 'saturday_free',
            'enabled' => false,
            'priority' => 1,
        ],
        [
            'key' => 'free_days',
            'enabled' => true,
            'priority' => 2,
        ],
    ]);

    expect($criteria)
        ->toHaveCount(1)
        ->sequence(fn ($criterion) => $criterion
            ->key->toBe('free_days')
            ->label->toBe('Anzahl freie Tage')
            ->enabled->toBeTrue()
            ->priority->toBe(3));

    expect(StudentTimetableEvaluationSetting::query()
        ->where('school_id', $user->school_id)
        ->where('schoolyear_id', $user->schoolyear_id)
        ->firstOrFail()
        ->settings['criteria'][0]['enabled'])->toBeTrue();
});

it('keeps distance learning preference criteria mutually exclusive', function () {
    $criteria = (new StudentTimetableEvaluationSettingsService)->activeCriteriaForRun([
        [
            'key' => 'avoid_distance_learning',
            'enabled' => true,
            'priority' => 1,
        ],
        [
            'key' => 'prefer_distance_learning',
            'enabled' => true,
            'priority' => 2,
        ],
    ]);

    expect($criteria)
        ->toHaveCount(1)
        ->sequence(fn ($criterion) => $criterion
            ->key->toBe('avoid_distance_learning')
            ->label->toBe('Kein Fernunterricht bevorzugt')
            ->enabled->toBeTrue()
            ->priority->toBe(2));
});

function createEvaluationSettingsUser(): User
{
    $school = School::factory()->create();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $school->id,
    ]);

    SchoolTool::factory()->create([
        'school_id' => $school->id,
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

    SchoolLicence::query()->create([
        'school_id' => $school->id,
        'licence_id' => $licence->id,
        'valid_until' => now()->addMonth()->toDateString(),
    ]);

    Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
    ]);
    $user->assignRole('admin');

    return $user;
}
