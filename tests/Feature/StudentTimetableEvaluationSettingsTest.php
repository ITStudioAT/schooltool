<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\StudentTimetableEvaluationSetting;
use App\Models\User;
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
        ->assertJsonCount(5, 'data.criteria')
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.criteria.0.key', 'saturday_free')
        ->assertJsonPath('data.criteria.0.enabled', false)
        ->assertJsonPath('data.criteria.0.option', 'all_appointments')
        ->assertJsonPath('data.criteria.0.options.1.value', 'ignore_single_date_appointments')
        ->assertJsonPath('data.criteria.4.key', 'ends_by_period_13');
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
                'option' => 'ignore_single_date_appointments',
            ],
            [
                'key' => 'free_days',
                'enabled' => true,
                'priority' => 3,
            ],
            [
                'key' => 'starts_from_period_10',
                'enabled' => false,
                'priority' => 4,
            ],
            [
                'key' => 'ends_by_period_13',
                'enabled' => true,
                'priority' => 5,
            ],
        ],
    ];

    $this->actingAs($user)
        ->putJson('/api/admin/students-timetables/evaluation-settings', $payload)
        ->assertSuccessful()
        ->assertJsonPath('message', 'Bewertungseinstellungen wurden gespeichert.')
        ->assertJsonPath('data.criteria.0.key', 'few_gaps')
        ->assertJsonPath('data.criteria.1.key', 'saturday_free')
        ->assertJsonPath('data.criteria.1.option', 'ignore_single_date_appointments')
        ->assertJsonPath('data.criteria.4.key', 'ends_by_period_13');

    $settings = StudentTimetableEvaluationSetting::query()
        ->where('school_id', $user->school_id)
        ->where('schoolyear_id', $user->schoolyear_id)
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
                ->option->toBe('ignore_single_date_appointments'),
            fn ($criterion) => $criterion
                ->key->toBe('free_days')
                ->enabled->toBeTrue()
                ->priority->toBe(3),
            fn ($criterion) => $criterion
                ->key->toBe('starts_from_period_10')
                ->enabled->toBeFalse()
                ->priority->toBe(4),
            fn ($criterion) => $criterion
                ->key->toBe('ends_by_period_13')
                ->enabled->toBeTrue()
                ->priority->toBe(5),
        );
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
