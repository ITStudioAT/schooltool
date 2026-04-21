<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCurriculum;
use App\Models\TeachingImportedCurriculum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    collect([
        'admin',
        'teaching_admin',
        'teacher',
        'user',
    ])->each(fn (string $role) => Role::firstOrCreate([
        'name' => $role,
        'guard_name' => 'web',
    ]));

    $this->school = School::factory()->create([
        'short_name' => 'CURR',
        'long_name' => 'Curriculum Test School',
    ]);

    $teachingLicence = Licence::firstOrCreate(
        ['name' => 'Lehrertool'],
        ['long_name' => 'Lehrertool', 'is_selectable' => true]
    );

    $this->school->licences()->attach($teachingLicence->id, [
        'valid_until' => now()->addYear()->toDateString(),
    ]);

    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'from' => '2026-09-01',
        'until' => '2027-07-31',
    ]);

    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'teaching_visible_admin' => true,
        'teaching_visible_user' => true,
    ]);

    $this->teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'teacher@curriculum.test',
    ]);
    $this->teacher->assignRole('teacher');

    $this->otherSchool = School::factory()->create();
    $this->otherSchoolyear = Schoolyear::factory()->create([
        'school_id' => $this->otherSchool->id,
        'from' => '2026-09-01',
        'until' => '2027-07-31',
    ]);

    SchoolTool::factory()->create([
        'school_id' => $this->otherSchool->id,
        'teaching_visible_admin' => true,
        'teaching_visible_user' => true,
    ]);

    $this->otherTeacher = User::factory()->create([
        'school_id' => $this->otherSchool->id,
        'schoolyear_id' => $this->otherSchoolyear->id,
        'email' => 'other-teacher@curriculum.test',
    ]);
    $this->otherTeacher->assignRole('teacher');
});

test('teacher can import a curriculum json and reimport updates the existing imported curriculum', function () {
    $payload = [
        'export_type' => 'teaching_curriculum',
        'schema_version' => 1,
        'curriculum_key' => '11111111-1111-1111-1111-111111111111',
        'exported_at' => '2026-04-21T16:00:00+02:00',
        'curriculum' => [
            'title' => 'Deutsch 5A',
            'description' => 'Importierte Vorlage',
            'semester_count' => 2,
            'free_weeks' => ['2025-09-08'],
            'topics' => [
                [
                    'id' => 'topic-1',
                    'title' => 'Lesen',
                    'assignment_type' => 'month',
                    'month_keys' => ['2025-09'],
                    'week_keys' => [],
                    'units' => [
                        [
                            'id' => 'unit-1',
                            'title' => 'Lesetagebuch',
                            'is_exam' => false,
                            'assignment_type' => 'weeks',
                            'month_keys' => [],
                            'week_keys' => ['2025-09-08'],
                            'checked_week_keys' => ['2025-09-08'],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $response = $this->actingAs($this->teacher, 'sanctum')->post('/api/admin/teaching/imported-curricula/import', [
        'file' => UploadedFile::fake()->createWithContent('curriculum.json', json_encode($payload, JSON_THROW_ON_ERROR)),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Deutsch 5A')
        ->assertJsonPath('data.curriculum_key', '11111111-1111-1111-1111-111111111111')
        ->assertJsonPath('data.topics.0.units.0.checked_week_keys.0', '2025-09-08');

    expect(TeachingImportedCurriculum::query()->count())->toBe(1)
        ->and(TeachingImportedCurriculum::query()->firstOrFail()->title)->toBe('Deutsch 5A');

    $payload['curriculum']['title'] = 'Deutsch 5A aktualisiert';

    $this->actingAs($this->teacher, 'sanctum')->post('/api/admin/teaching/imported-curricula/import', [
        'file' => UploadedFile::fake()->createWithContent('curriculum.json', json_encode($payload, JSON_THROW_ON_ERROR)),
    ])->assertOk()
        ->assertJsonPath('data.title', 'Deutsch 5A aktualisiert');

    expect(TeachingImportedCurriculum::query()->count())->toBe(1)
        ->and(TeachingImportedCurriculum::query()->firstOrFail()->title)->toBe('Deutsch 5A aktualisiert');
});

test('teacher can adopt an imported curriculum as a personal curriculum', function () {
    $importedCurriculum = TeachingImportedCurriculum::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'curriculum_key' => '22222222-2222-2222-2222-222222222222',
        'title' => 'Französisch 2A',
        'description' => 'Externe Vorlage',
        'semester_count' => 2,
        'free_weeks' => ['2025-09-08'],
        'topics' => [
            [
                'id' => 'topic-1',
                'title' => 'Unité 1',
                'assignment_type' => 'month',
                'month_key' => '2025-09',
                'month_keys' => ['2025-09'],
                'week_keys' => [],
                'units' => [
                    [
                        'id' => 'unit-1',
                        'title' => 'Bonjour',
                        'is_exam' => false,
                        'assignment_type' => 'weeks',
                        'month_key' => null,
                        'month_keys' => [],
                        'week_keys' => ['2025-09-08'],
                        'checked_week_keys' => ['2025-09-08'],
                    ],
                ],
            ],
        ],
    ]);

    $response = $this->actingAs($this->teacher, 'sanctum')
        ->postJson("/api/admin/teaching/imported-curricula/{$importedCurriculum->id}/adopt");

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Französisch 2A')
        ->assertJsonPath('data.free_weeks.0', '2026-09-07')
        ->assertJsonPath('data.topics.0.month_key', '2026-09')
        ->assertJsonPath('data.topics.0.units.0.week_keys.0', '2026-09-07')
        ->assertJsonPath('data.topics.0.units.0.checked_week_keys.0', '2026-09-07');

    expect(TeachingCurriculum::query()->count())->toBe(1)
        ->and(TeachingCurriculum::query()->firstOrFail()->user_id)->toBe($this->teacher->id)
        ->and(TeachingCurriculum::query()->firstOrFail()->export_key)->not->toBeNull();
});

test('teacher only sees own imported curricula and cannot adopt foreign imported curricula', function () {
    $ownImportedCurriculum = TeachingImportedCurriculum::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'title' => 'Eigenes Import-Curriculum',
    ]);

    $foreignImportedCurriculum = TeachingImportedCurriculum::factory()->create([
        'school_id' => $this->otherSchool->id,
        'user_id' => $this->otherTeacher->id,
        'title' => 'Fremdes Import-Curriculum',
    ]);

    $this->actingAs($this->teacher, 'sanctum')
        ->getJson('/api/admin/teaching/imported-curricula')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ownImportedCurriculum->id);

    $this->actingAs($this->teacher, 'sanctum')
        ->postJson("/api/admin/teaching/imported-curricula/{$foreignImportedCurriculum->id}/adopt")
        ->assertForbidden();
});

test('teacher can delete an imported curriculum but not a foreign one', function () {
    $ownImportedCurriculum = TeachingImportedCurriculum::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'title' => 'Eigenes Import-Curriculum',
    ]);

    $foreignImportedCurriculum = TeachingImportedCurriculum::factory()->create([
        'school_id' => $this->otherSchool->id,
        'user_id' => $this->otherTeacher->id,
        'title' => 'Fremdes Import-Curriculum',
    ]);

    $this->actingAs($this->teacher, 'sanctum')
        ->deleteJson("/api/admin/teaching/imported-curricula/{$ownImportedCurriculum->id}")
        ->assertNoContent();

    expect(TeachingImportedCurriculum::query()->whereKey($ownImportedCurriculum->id)->exists())->toBeFalse()
        ->and(TeachingImportedCurriculum::query()->whereKey($foreignImportedCurriculum->id)->exists())->toBeTrue();

    $this->actingAs($this->teacher, 'sanctum')
        ->deleteJson("/api/admin/teaching/imported-curricula/{$foreignImportedCurriculum->id}")
        ->assertForbidden();
});
