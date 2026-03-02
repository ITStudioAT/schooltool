<?php

use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingSchema;
use App\Models\User;
use App\Services\TeachingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);

    $this->user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);

    $this->service = new TeachingService;
});

test('ensureDefaultSchema creates a standard schema when user has none', function () {
    expect(TeachingSchema::query()->count())->toBe(0);

    $this->service->ensureDefaultSchema($this->user, $this->schoolyear->id);

    $this->assertDatabaseCount('teaching_schemas', 1);
    $this->assertDatabaseHas('teaching_schemas', [
        'user_id' => $this->user->id,
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'name' => 'Standard',
    ]);

    $schema = TeachingSchema::query()->first();
    expect($schema)->not->toBeNull()
        ->and($schema->works)->toBeArray()
        ->and($schema->grading)->toBeArray();
});

test('ensureDefaultSchema is idempotent and does not create duplicate rows', function () {
    $this->service->ensureDefaultSchema($this->user, $this->schoolyear->id);
    $firstSchemaId = TeachingSchema::query()->value('schema_id');

    $this->service->ensureDefaultSchema($this->user, $this->schoolyear->id);

    $this->assertDatabaseCount('teaching_schemas', 1);
    expect(TeachingSchema::query()->value('schema_id'))->toBe($firstSchemaId);
});
