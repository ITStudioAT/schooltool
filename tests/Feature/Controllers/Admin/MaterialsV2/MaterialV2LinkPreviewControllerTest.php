<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\MaterialsV2\MaterialV2LinkPreviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'materials_admin', 'guard_name' => 'web']);

    $school = School::factory()->create();
    $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
    SchoolTool::factory()->create([
        'school_id' => $school->id,
        'materials_visible_admin' => true,
        'materials_visible_user' => true,
    ]);

    $this->user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
    ]);
    $this->user->assignRole('materials_admin');

    $licence = Licence::query()->create([
        'name' => 'Materialientool',
        'long_name' => 'Materialientool',
        'is_selectable' => true,
    ]);
    $school->licences()->attach($licence->id, [
        'valid_until' => now()->addYear()->toDateString(),
    ]);
});

it('requires authentication', function () {
    $this->postJson('/api/admin/materials-v2/link-preview', [
        'url' => 'https://example.org',
    ])->assertUnauthorized();
});

it('validates the target address', function () {
    $this->actingAs($this->user, 'sanctum');

    $this->postJson('/api/admin/materials-v2/link-preview', [
        'url' => 'javascript:alert(1)',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('url');
});

it('returns the inspected link metadata', function () {
    $this->mock(MaterialV2LinkPreviewService::class)
        ->shouldReceive('inspect')
        ->once()
        ->with('https://example.org')
        ->andReturn([
            'reachable' => true,
            'status' => 'success',
            'url' => 'https://example.org',
            'title' => 'Beispielseite',
            'message' => 'Zieladresse erreichbar. Der Seitentitel wurde übernommen.',
            'http_status' => 200,
        ]);

    $this->actingAs($this->user, 'sanctum');

    $this->postJson('/api/admin/materials-v2/link-preview', [
        'url' => 'https://example.org',
    ])
        ->assertSuccessful()
        ->assertExactJson([
            'data' => [
                'reachable' => true,
                'status' => 'success',
                'url' => 'https://example.org',
                'title' => 'Beispielseite',
                'message' => 'Zieladresse erreichbar. Der Seitentitel wurde übernommen.',
                'http_status' => 200,
            ],
        ]);
});
