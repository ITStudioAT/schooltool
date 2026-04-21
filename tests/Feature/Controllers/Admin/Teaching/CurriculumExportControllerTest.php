<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCurriculum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    collect([
        'admin',
        'teaching_admin',
        'teacher',
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

    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'teaching_visible_admin' => true,
        'teaching_visible_user' => true,
    ]);

    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'from' => '2026-09-01',
        'until' => '2027-07-31',
    ]);

    $this->teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'teacher@curriculum-export.test',
    ]);
    $this->teacher->assignRole('teacher');

    $this->otherTeacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'other@curriculum-export.test',
    ]);
    $this->otherTeacher->assignRole('teacher');
});

test('teacher can download curriculum json export with a stable curriculum key and without materials', function () {
    $curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Deutsch 5A',
        'description' => 'Jahresplanung',
        'semester_count' => 2,
        'free_weeks' => ['2026-09-15'],
        'topics' => [
            [
                'id' => 'topic-lesen',
                'title' => 'Lesen',
                'assignment_type' => 'month',
                'month_key' => '2026-09',
                'month_keys' => ['2026-09'],
                'week_keys' => [],
                'materials' => [
                    ['id' => 9, 'title' => 'Arbeitsblatt'],
                ],
                'units' => [
                    [
                        'id' => 'unit-text',
                        'title' => 'Texte verstehen',
                        'is_exam' => true,
                        'assignment_type' => 'weeks',
                        'month_key' => null,
                        'month_keys' => [],
                        'week_keys' => ['2026-09-15'],
                        'checked_week_keys' => ['2026-09-15'],
                        'materials' => [
                            ['id' => 10, 'title' => 'Mappe'],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $curriculum->forceFill(['export_key' => null])->saveQuietly();

    $response = $this->actingAs($this->teacher, 'sanctum')
        ->get("/api/admin/teaching/curricula/{$curriculum->id}/export/json");

    $response->assertOk()
        ->assertDownload('Curriculum_Deutsch_5A.json')
        ->assertHeader('Content-Type', 'application/json; charset=UTF-8');

    $payload = json_decode($response->streamedContent(), true, 512, JSON_THROW_ON_ERROR);
    $exportKey = $curriculum->fresh()->export_key;

    expect($exportKey)->not->toBeNull()
        ->and($payload['export_type'])->toBe('teaching_curriculum')
        ->and($payload['schema_version'])->toBe(1)
        ->and($payload['curriculum_key'])->toBe($exportKey)
        ->and($payload['curriculum']['title'])->toBe('Deutsch 5A')
        ->and($payload['curriculum']['description'])->toBe('Jahresplanung')
        ->and($payload['curriculum']['free_weeks'])->toBe(['2026-09-15'])
        ->and($payload['curriculum']['topics'][0]['id'])->toBe('topic-lesen')
        ->and($payload['curriculum']['topics'][0]['assignment_type'])->toBe('month')
        ->and($payload['curriculum']['topics'][0])->not->toHaveKey('materials')
        ->and($payload['curriculum']['topics'][0]['units'][0]['id'])->toBe('unit-text')
        ->and($payload['curriculum']['topics'][0]['units'][0]['checked_week_keys'])->toBe(['2026-09-15'])
        ->and($payload['curriculum']['topics'][0]['units'][0])->not->toHaveKey('materials');

    $secondResponse = $this->actingAs($this->teacher, 'sanctum')
        ->get("/api/admin/teaching/curricula/{$curriculum->id}/export/json");

    $secondPayload = json_decode($secondResponse->streamedContent(), true, 512, JSON_THROW_ON_ERROR);

    expect($secondPayload['curriculum_key'])->toBe($exportKey);
});

test('teacher cannot download another teachers curriculum json export', function () {
    $curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Deutsch 5A',
        'description' => null,
        'semester_count' => 2,
        'free_weeks' => [],
        'topics' => [],
    ]);

    $this->actingAs($this->otherTeacher, 'sanctum')
        ->get("/api/admin/teaching/curricula/{$curriculum->id}/export/json")
        ->assertForbidden();
});
