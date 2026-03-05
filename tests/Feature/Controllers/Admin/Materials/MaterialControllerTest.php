<?php

use App\Models\Licence;
use App\Models\MaterialCard;
use App\Models\MaterialCardAttachment;
use App\Models\MaterialCardClassification;
use App\Models\MaterialInboxImport;
use App\Models\MaterialShareRule;
use App\Models\MaterialShareTarget;
use App\Models\MaterialSubject;
use App\Models\MaterialTopic;
use App\Models\MaterialTopicInboxImport;
use App\Models\MaterialType;
use App\Models\MaterialUnit;
use App\Models\MaterialUnitInboxImport;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function materialShareTablesAvailable(): bool
{
    return Schema::hasTable('material_share_rules')
        && Schema::hasTable('material_share_targets');
}

beforeEach(function () {
    collect([
        'super_admin',
        'admin',
        'teaching_admin',
        'materials_admin',
        'materials_moderator',
        'teacher',
        'user',
    ])->each(fn (string $role) => Role::firstOrCreate([
        'name' => $role,
        'guard_name' => 'web',
    ]));

    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);

    $makeUser = function (string $email, string $role): User {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'email' => $email,
        ]);
        $user->assignRole($role);

        return $user;
    };

    $this->superAdmin = $makeUser('super-admin@materials.test', 'super_admin');
    $this->admin = $makeUser('admin@materials.test', 'admin');
    $this->teachingAdmin = $makeUser('teaching-admin@materials.test', 'teaching_admin');
    $this->materialsAdmin = $makeUser('materials-admin@materials.test', 'materials_admin');
    $this->materialsModerator = $makeUser('materials-moderator@materials.test', 'materials_moderator');
    $this->teacherRoleUser = $makeUser('teacher@materials.test', 'teacher');
    $this->teacher = $this->materialsModerator;
    $this->regularUser = $makeUser('user@materials.test', 'user');

    $materialsLicence = Licence::firstOrCreate(
        ['name' => 'Materialientool'],
        ['long_name' => 'Materialientool']
    );

    SchoolLicence::create([
        'school_id' => $this->school->id,
        'licence_id' => $materialsLicence->id,
        'valid_until' => now()->addYear(),
    ]);
});

function createLinkedImportedCard(User $targetUser, School $school, Schoolyear $schoolyear, string $permission): MaterialCard
{
    if (! materialShareTablesAvailable()) {
        throw new \PHPUnit\Framework\SkippedTestError('Material sharing tables are not available in this reset state.');
    }

    $sourceUser = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'email' => 'source-'.uniqid().'@materials.test',
    ]);

    $sourceCard = MaterialCard::factory()->create([
        'school_id' => $school->id,
        'user_id' => $sourceUser->id,
        'title' => 'Geteiltes Original',
        'status' => MaterialCard::STATUS_INBOX,
        'keywords' => [],
    ]);

    $targetCard = MaterialCard::factory()->create([
        'school_id' => $school->id,
        'user_id' => $targetUser->id,
        'title' => 'Verlinkte Kopie',
        'status' => MaterialCard::STATUS_INBOX,
        'keywords' => [],
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $school->id,
        'created_by_user_id' => $sourceUser->id,
        'scope_type' => MaterialShareRule::SCOPE_MATERIAL,
        'scope_id' => $sourceCard->id,
        'is_active' => true,
    ]);

    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $targetUser->id,
        'permission' => $permission,
    ]);

    $importData = [
        'target_user_id' => (int) $targetUser->id,
        'target_material_card_id' => (int) $targetCard->id,
        'source_rule_id' => (int) $rule->id,
        'source_school_id' => (int) $school->id,
        'source_material_id' => (int) $sourceCard->id,
        'imported_at' => now(),
    ];
    if (Schema::hasColumn('material_inbox_imports', 'import_mode')) {
        $importData['import_mode'] = MaterialInboxImport::MODE_LINK;
    }

    MaterialInboxImport::query()->create($importData);

    return $targetCard->fresh();
}

test('requires authentication', function () {
    $this->getJson('/api/admin/materials/config')
        ->assertStatus(401);
});

test('allows admin role', function () {
    $this->actingAs($this->admin, 'sanctum');

    $this->getJson('/api/admin/materials/config')
        ->assertStatus(200)
        ->assertJsonFragment([
            'module' => 'materials',
            'school_id' => $this->admin->school_id,
        ]);
});

test('denies teaching_admin role', function () {
    $this->actingAs($this->teachingAdmin, 'sanctum');

    $this->getJson('/api/admin/materials/config')
        ->assertStatus(403);
});

test('allows materials_admin role', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    $this->getJson('/api/admin/materials/config')
        ->assertStatus(200)
        ->assertJsonFragment([
            'module' => 'materials',
            'school_id' => $this->materialsAdmin->school_id,
        ]);
});

test('allows materials_moderator role', function () {
    $this->actingAs($this->materialsModerator, 'sanctum');

    $this->getJson('/api/admin/materials/config')
        ->assertStatus(200)
        ->assertJsonFragment([
            'module' => 'materials',
            'school_id' => $this->materialsModerator->school_id,
        ]);
});

test('denies teacher role', function () {
    $this->actingAs($this->teacherRoleUser, 'sanctum');

    $this->getJson('/api/admin/materials/config')
        ->assertStatus(403);
});

test('allows super_admin role via middleware trait handling', function () {
    $this->actingAs($this->superAdmin, 'sanctum');

    $this->getJson('/api/admin/materials/config')
        ->assertStatus(200)
        ->assertJsonFragment([
            'module' => 'materials',
            'school_id' => $this->superAdmin->school_id,
        ]);
});

test('denies regular user role', function () {
    $this->actingAs($this->regularUser, 'sanctum');

    $this->getJson('/api/admin/materials/config')
        ->assertStatus(403);
});

test('config returns status options', function () {
    $this->actingAs($this->materialsModerator, 'sanctum');

    $this->getJson('/api/admin/materials/config')
        ->assertStatus(200)
        ->assertJsonStructure([
            'module',
            'school_id',
            'status_values' => [
                ['value', 'label', 'color'],
            ],
        ]);
});

test('config exposes status management only for admin', function () {
    $this->actingAs($this->materialsModerator, 'sanctum');
    $this->getJson('/api/admin/materials/config')
        ->assertStatus(200)
        ->assertJsonPath('can_manage_status_values', false);

    $this->actingAs($this->admin, 'sanctum');
    $this->getJson('/api/admin/materials/config')
        ->assertStatus(200)
        ->assertJsonPath('can_manage_status_values', true);
});

test('config exposes file settings and file setting management only for admin', function () {
    $this->actingAs($this->materialsModerator, 'sanctum');
    $this->getJson('/api/admin/materials/config')
        ->assertStatus(200)
        ->assertJsonPath('can_manage_file_settings', false)
        ->assertJsonPath('file_settings.max_upload_size_kb', 20480);

    $this->actingAs($this->admin, 'sanctum');
    $this->getJson('/api/admin/materials/config')
        ->assertStatus(200)
        ->assertJsonPath('can_manage_file_settings', true)
        ->assertJsonPath('file_settings.max_upload_size_kb', 20480);
});

test('config exposes user pagination settings for materials overview', function () {
    $this->actingAs($this->materialsModerator, 'sanctum');

    $this->getJson('/api/admin/materials/config')
        ->assertStatus(200)
        ->assertJsonPath('can_manage_user_settings', true)
        ->assertJsonPath('user_settings.materials_pagination_number', (int) config('schooltool.pagination'));
});

test('config returns default material type options from schooltool config', function () {
    Config::set('schooltool.materials_default_types', ['Arbeitsblatt', 'Test']);

    $this->actingAs($this->materialsModerator, 'sanctum');

    $this->getJson('/api/admin/materials/config')
        ->assertStatus(200)
        ->assertJsonPath('default_type_values.0.value', 'Arbeitsblatt')
        ->assertJsonPath('default_type_values.0.label', 'Arbeitsblatt')
        ->assertJsonPath('default_type_values.1.value', 'Test')
        ->assertJsonPath('default_type_values.1.label', 'Test');
});

test('teacher can create and rename subject topic and unit in own taxonomy', function () {
    $this->actingAs($this->teacher, 'sanctum');

    $subjectResponse = $this->postJson('/api/admin/materials/subjects', [
        'data' => [
            'name' => 'Mathematik',
        ],
    ])->assertStatus(200);

    $subjectId = (int) $subjectResponse->json('data.id');

    $topicResponse = $this->postJson('/api/admin/materials/topics', [
        'data' => [
            'subject_id' => $subjectId,
            'name' => 'Algebra',
        ],
    ])->assertStatus(200);

    $topicId = (int) $topicResponse->json('data.id');

    $unitResponse = $this->postJson('/api/admin/materials/units', [
        'data' => [
            'topic_id' => $topicId,
            'name' => 'Lineare Gleichungen',
        ],
    ])->assertStatus(200);

    $unitId = (int) $unitResponse->json('data.id');

    $this->putJson('/api/admin/materials/subjects/'.$subjectId, [
        'data' => [
            'name' => 'Mathe',
        ],
    ])->assertStatus(200)
        ->assertJsonPath('data.name', 'Mathe');

    $this->putJson('/api/admin/materials/topics/'.$topicId, [
        'data' => [
            'name' => 'Gleichungen',
        ],
    ])->assertStatus(200)
        ->assertJsonPath('data.name', 'Gleichungen');

    $this->putJson('/api/admin/materials/units/'.$unitId, [
        'data' => [
            'name' => 'Lineare Systeme',
        ],
    ])->assertStatus(200)
        ->assertJsonPath('data.name', 'Lineare Systeme');

    $this->assertDatabaseHas('material_subjects', [
        'id' => $subjectId,
        'user_id' => $this->teacher->id,
        'name' => 'Mathe',
    ]);
    $this->assertDatabaseHas('material_topics', [
        'id' => $topicId,
        'subject_id' => $subjectId,
        'name' => 'Gleichungen',
    ]);
    $this->assertDatabaseHas('material_units', [
        'id' => $unitId,
        'topic_id' => $topicId,
        'name' => 'Lineare Systeme',
    ]);
});

test('teacher can create duplicate unit names when allow_duplicate is true', function () {
    $this->actingAs($this->teacher, 'sanctum');

    $subjectResponse = $this->postJson('/api/admin/materials/subjects', [
        'data' => ['name' => 'Mathematik'],
    ])->assertStatus(200);
    $subjectId = (int) $subjectResponse->json('data.id');

    $topicResponse = $this->postJson('/api/admin/materials/topics', [
        'data' => [
            'subject_id' => $subjectId,
            'name' => 'Algebra',
        ],
    ])->assertStatus(200);
    $topicId = (int) $topicResponse->json('data.id');

    $firstUnitResponse = $this->postJson('/api/admin/materials/units', [
        'data' => [
            'topic_id' => $topicId,
            'name' => 'Lineare Gleichungen',
            'allow_duplicate' => true,
        ],
    ])->assertStatus(200);
    $firstUnitId = (int) $firstUnitResponse->json('data.id');
    expect($firstUnitId)->toBeGreaterThan(0);

    $secondUnitResponse = $this->postJson('/api/admin/materials/units', [
        'data' => [
            'topic_id' => $topicId,
            'name' => 'Lineare Gleichungen',
            'allow_duplicate' => true,
        ],
    ])->assertStatus(200);
    $secondUnitId = (int) $secondUnitResponse->json('data.id');
    expect($secondUnitId)->toBeGreaterThan(0);
    expect($secondUnitId)->not->toBe($firstUnitId);

    $duplicateCount = \App\Models\MaterialUnit::query()
        ->where('topic_id', $topicId)
        ->where('name', 'Lineare Gleichungen')
        ->count();
    expect($duplicateCount)->toBe(2);
});

test('teacher can post topic with allow_duplicate and reuses existing topic name', function () {
    $this->actingAs($this->teacher, 'sanctum');

    $subjectResponse = $this->postJson('/api/admin/materials/subjects', [
        'data' => ['name' => 'Mathematik'],
    ])->assertStatus(200);
    $subjectId = (int) $subjectResponse->json('data.id');

    $firstTopicResponse = $this->postJson('/api/admin/materials/topics', [
        'data' => [
            'subject_id' => $subjectId,
            'name' => 'Algebra',
            'allow_duplicate' => true,
        ],
    ])->assertStatus(200);
    $firstTopicId = (int) $firstTopicResponse->json('data.id');
    expect($firstTopicId)->toBeGreaterThan(0);

    $secondTopicResponse = $this->postJson('/api/admin/materials/topics', [
        'data' => [
            'subject_id' => $subjectId,
            'name' => 'Algebra',
            'allow_duplicate' => true,
        ],
    ])->assertStatus(200);
    $secondTopicId = (int) $secondTopicResponse->json('data.id');
    expect($secondTopicId)->toBeGreaterThan(0);
    expect($secondTopicId)->toBe($firstTopicId);

    $duplicateCount = \App\Models\MaterialTopic::query()
        ->where('subject_id', $subjectId)
        ->where('name', 'Algebra')
        ->count();
    expect($duplicateCount)->toBe(1);
});

test('teacher cannot rename subject from another user taxonomy', function () {
    $otherTeacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'other-teacher@materials.test',
    ]);
    $otherTeacher->assignRole('materials_moderator');

    $foreignSubject = MaterialSubject::query()->create([
        'user_id' => $otherTeacher->id,
        'name' => 'Biologie',
    ]);

    $this->actingAs($this->teacher, 'sanctum');

    $this->putJson('/api/admin/materials/subjects/'.$foreignSubject->id, [
        'data' => [
            'name' => 'Bio',
        ],
    ])->assertStatus(403);
});

test('teacher can delete unused subject topic and unit', function () {
    $this->actingAs($this->teacher, 'sanctum');

    $subjectResponse = $this->postJson('/api/admin/materials/subjects', [
        'data' => ['name' => 'Informatik'],
    ])->assertStatus(200);
    $subjectId = (int) $subjectResponse->json('data.id');

    $topicResponse = $this->postJson('/api/admin/materials/topics', [
        'data' => [
            'subject_id' => $subjectId,
            'name' => 'Programmierung',
        ],
    ])->assertStatus(200);
    $topicId = (int) $topicResponse->json('data.id');

    $unitResponse = $this->postJson('/api/admin/materials/units', [
        'data' => [
            'topic_id' => $topicId,
            'name' => 'Variablen',
        ],
    ])->assertStatus(200);
    $unitId = (int) $unitResponse->json('data.id');

    $this->deleteJson('/api/admin/materials/units/'.$unitId)
        ->assertStatus(204);
    $this->assertDatabaseMissing('material_units', ['id' => $unitId]);

    $this->deleteJson('/api/admin/materials/topics/'.$topicId)
        ->assertStatus(204);
    $this->assertDatabaseMissing('material_topics', ['id' => $topicId]);

    $this->deleteJson('/api/admin/materials/subjects/'.$subjectId)
        ->assertStatus(204);
    $this->assertDatabaseMissing('material_subjects', ['id' => $subjectId]);
});

test('teacher can delete subject when only subject level is used', function () {
    $subject = MaterialSubject::query()->create([
        'user_id' => $this->teacher->id,
        'name' => 'Physik',
    ]);

    $card = MaterialCard::query()->create([
        'school_id' => $this->teacher->school_id,
        'user_id' => $this->teacher->id,
        'title' => 'Testkarte',
        'status' => 'inbox',
    ]);

    MaterialCardClassification::query()->create([
        'material_card_id' => $card->id,
        'subject_id' => $subject->id,
        'topic_id' => null,
        'unit_id' => null,
    ]);

    $this->actingAs($this->teacher, 'sanctum');
    $this->deleteJson('/api/admin/materials/subjects/'.$subject->id)
        ->assertStatus(204);

    $this->assertDatabaseMissing('material_subjects', ['id' => $subject->id]);
});

test('teacher can delete topic when only topic level is used', function () {
    $subject = MaterialSubject::query()->create([
        'user_id' => $this->teacher->id,
        'name' => 'Chemie',
    ]);
    $topic = $subject->topics()->create(['name' => 'Atombau']);
    $topic->units()->create(['name' => 'Elektronen']);

    $card = MaterialCard::query()->create([
        'school_id' => $this->teacher->school_id,
        'user_id' => $this->teacher->id,
        'title' => 'Testkarte',
        'status' => 'inbox',
    ]);

    MaterialCardClassification::query()->create([
        'material_card_id' => $card->id,
        'subject_id' => $subject->id,
        'topic_id' => $topic->id,
        'unit_id' => null,
    ]);

    $this->actingAs($this->teacher, 'sanctum');
    $this->deleteJson('/api/admin/materials/topics/'.$topic->id)
        ->assertStatus(204);

    $this->assertDatabaseMissing('material_topics', ['id' => $topic->id]);
});

test('teacher cannot delete subject when a topic below is used', function () {
    $subject = MaterialSubject::query()->create([
        'user_id' => $this->teacher->id,
        'name' => 'Biologie',
    ]);
    $topic = $subject->topics()->create(['name' => 'Zelle']);

    $card = MaterialCard::query()->create([
        'school_id' => $this->teacher->school_id,
        'user_id' => $this->teacher->id,
        'title' => 'Testkarte',
        'status' => 'inbox',
    ]);

    MaterialCardClassification::query()->create([
        'material_card_id' => $card->id,
        'subject_id' => $subject->id,
        'topic_id' => $topic->id,
        'unit_id' => null,
    ]);

    $this->actingAs($this->teacher, 'sanctum');
    $this->deleteJson('/api/admin/materials/subjects/'.$subject->id)
        ->assertStatus(422);

    $this->assertDatabaseHas('material_subjects', ['id' => $subject->id]);
});

test('teacher cannot delete topic when a unit below is used', function () {
    $subject = MaterialSubject::query()->create([
        'user_id' => $this->teacher->id,
        'name' => 'Englisch',
    ]);
    $topic = $subject->topics()->create(['name' => 'Vocabulary']);
    $unit = $topic->units()->create(['name' => 'Daily Routines']);

    $card = MaterialCard::query()->create([
        'school_id' => $this->teacher->school_id,
        'user_id' => $this->teacher->id,
        'title' => 'Testkarte',
        'status' => 'inbox',
    ]);

    MaterialCardClassification::query()->create([
        'material_card_id' => $card->id,
        'subject_id' => $subject->id,
        'topic_id' => $topic->id,
        'unit_id' => $unit->id,
    ]);

    $this->actingAs($this->teacher, 'sanctum');
    $this->deleteJson('/api/admin/materials/topics/'.$topic->id)
        ->assertStatus(422);

    $this->assertDatabaseHas('material_topics', ['id' => $topic->id]);
});

test('teacher cannot delete unit when used by materials', function () {
    $subject = MaterialSubject::query()->create([
        'user_id' => $this->teacher->id,
        'name' => 'Deutsch',
    ]);
    $topic = $subject->topics()->create(['name' => 'Grammatik']);
    $unit = $topic->units()->create(['name' => 'Satzglieder']);

    $card = MaterialCard::query()->create([
        'school_id' => $this->teacher->school_id,
        'user_id' => $this->teacher->id,
        'title' => 'Testkarte',
        'status' => 'inbox',
    ]);

    MaterialCardClassification::query()->create([
        'material_card_id' => $card->id,
        'subject_id' => $subject->id,
        'topic_id' => $topic->id,
        'unit_id' => $unit->id,
    ]);

    $this->actingAs($this->teacher, 'sanctum');
    $this->deleteJson('/api/admin/materials/units/'.$unit->id)
        ->assertStatus(422);

    $this->assertDatabaseHas('material_units', ['id' => $unit->id]);
});

test('teacher can delete subject when only soft-deleted topic usage exists', function () {
    $subject = MaterialSubject::query()->create([
        'user_id' => $this->teacher->id,
        'name' => 'Geografie',
    ]);
    $topic = $subject->topics()->create(['name' => 'Klima']);

    $card = MaterialCard::query()->create([
        'school_id' => $this->teacher->school_id,
        'user_id' => $this->teacher->id,
        'title' => 'Gelöschte Karte',
        'status' => 'inbox',
    ]);

    MaterialCardClassification::query()->create([
        'material_card_id' => $card->id,
        'subject_id' => $subject->id,
        'topic_id' => $topic->id,
        'unit_id' => null,
    ]);

    $card->delete();

    $this->actingAs($this->teacher, 'sanctum');
    $this->deleteJson('/api/admin/materials/subjects/'.$subject->id)
        ->assertStatus(204);

    $this->assertDatabaseMissing('material_subjects', ['id' => $subject->id]);
});

test('teacher can delete topic when only soft-deleted unit usage exists', function () {
    $subject = MaterialSubject::query()->create([
        'user_id' => $this->teacher->id,
        'name' => 'Kunst',
    ]);
    $topic = $subject->topics()->create(['name' => 'Malerei']);
    $unit = $topic->units()->create(['name' => 'Aquarell']);

    $card = MaterialCard::query()->create([
        'school_id' => $this->teacher->school_id,
        'user_id' => $this->teacher->id,
        'title' => 'Gelöschte Karte',
        'status' => 'inbox',
    ]);

    MaterialCardClassification::query()->create([
        'material_card_id' => $card->id,
        'subject_id' => $subject->id,
        'topic_id' => $topic->id,
        'unit_id' => $unit->id,
    ]);

    $card->delete();

    $this->actingAs($this->teacher, 'sanctum');
    $this->deleteJson('/api/admin/materials/topics/'.$topic->id)
        ->assertStatus(204);

    $this->assertDatabaseMissing('material_topics', ['id' => $topic->id]);
});

test('teacher can delete unit when only soft-deleted unit usage exists', function () {
    $subject = MaterialSubject::query()->create([
        'user_id' => $this->teacher->id,
        'name' => 'Musik',
    ]);
    $topic = $subject->topics()->create(['name' => 'Rhythmus']);
    $unit = $topic->units()->create(['name' => 'Taktarten']);

    $card = MaterialCard::query()->create([
        'school_id' => $this->teacher->school_id,
        'user_id' => $this->teacher->id,
        'title' => 'Gelöschte Karte',
        'status' => 'inbox',
    ]);

    MaterialCardClassification::query()->create([
        'material_card_id' => $card->id,
        'subject_id' => $subject->id,
        'topic_id' => $topic->id,
        'unit_id' => $unit->id,
    ]);

    $card->delete();

    $this->actingAs($this->teacher, 'sanctum');
    $this->deleteJson('/api/admin/materials/units/'.$unit->id)
        ->assertStatus(204);

    $this->assertDatabaseMissing('material_units', ['id' => $unit->id]);
});

test('config marks used taxonomy items as not deletable', function () {
    $subject = MaterialSubject::query()->create([
        'user_id' => $this->teacher->id,
        'name' => 'Lehrpläne',
    ]);
    $topic = $subject->topics()->create(['name' => 'Informatik']);
    $unit = $topic->units()->create(['name' => 'Tagesschule']);

    $card = MaterialCard::query()->create([
        'school_id' => $this->teacher->school_id,
        'user_id' => $this->teacher->id,
        'title' => 'Testkarte',
        'status' => 'inbox',
    ]);

    MaterialCardClassification::query()->create([
        'material_card_id' => $card->id,
        'subject_id' => $subject->id,
        'topic_id' => $topic->id,
        'unit_id' => $unit->id,
    ]);

    $this->actingAs($this->teacher, 'sanctum');

    $response = $this->getJson('/api/admin/materials/config')
        ->assertStatus(200);

    $tree = collect($response->json('classification_tree', []));
    $subjectNode = $tree->firstWhere('id', $subject->id);

    expect($subjectNode)->not->toBeNull()
        ->and((bool) ($subjectNode['can_delete'] ?? true))->toBeFalse();

    $topicNode = collect($subjectNode['topics'] ?? [])->firstWhere('id', $topic->id);
    expect($topicNode)->not->toBeNull()
        ->and((bool) ($topicNode['can_delete'] ?? true))->toBeFalse();

    $unitNode = collect($topicNode['units'] ?? [])->firstWhere('id', $unit->id);
    expect($unitNode)->not->toBeNull()
        ->and((bool) ($unitNode['can_delete'] ?? true))->toBeFalse();
});

test('config ignores soft-deleted cards when evaluating taxonomy usage', function () {
    $subject = MaterialSubject::query()->create([
        'user_id' => $this->teacher->id,
        'name' => 'Physik',
    ]);
    $topic = $subject->topics()->create(['name' => 'Optik']);
    $unit = $topic->units()->create(['name' => 'Linsen']);

    $card = MaterialCard::query()->create([
        'school_id' => $this->teacher->school_id,
        'user_id' => $this->teacher->id,
        'title' => 'Gelöschte Karte',
        'status' => 'inbox',
    ]);

    MaterialCardClassification::query()->create([
        'material_card_id' => $card->id,
        'subject_id' => $subject->id,
        'topic_id' => $topic->id,
        'unit_id' => $unit->id,
    ]);

    $card->delete();

    $this->actingAs($this->teacher, 'sanctum');

    $response = $this->getJson('/api/admin/materials/config')
        ->assertStatus(200);

    $tree = collect($response->json('classification_tree', []));
    $subjectNode = $tree->firstWhere('id', $subject->id);
    expect($subjectNode)->not->toBeNull()
        ->and((bool) ($subjectNode['can_delete'] ?? false))->toBeTrue();

    $topicNode = collect($subjectNode['topics'] ?? [])->firstWhere('id', $topic->id);
    expect($topicNode)->not->toBeNull()
        ->and((bool) ($topicNode['can_delete'] ?? false))->toBeTrue();

    $unitNode = collect($topicNode['units'] ?? [])->firstWhere('id', $unit->id);
    expect($unitNode)->not->toBeNull()
        ->and((bool) ($unitNode['can_delete'] ?? false))->toBeTrue();
});

test('config keeps topic deletable without unit usage while subject follows lower-level usage', function () {
    $subject = MaterialSubject::query()->create([
        'user_id' => $this->teacher->id,
        'name' => 'Geschichte',
    ]);
    $topic = $subject->topics()->create(['name' => 'Mittelalter']);
    $unit = $topic->units()->create(['name' => 'Kreuzzüge']);

    $subjectLevelCard = MaterialCard::query()->create([
        'school_id' => $this->teacher->school_id,
        'user_id' => $this->teacher->id,
        'title' => 'Subjekt-Ebene',
        'status' => 'inbox',
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $subjectLevelCard->id,
        'subject_id' => $subject->id,
        'topic_id' => null,
        'unit_id' => null,
    ]);

    $topicLevelCard = MaterialCard::query()->create([
        'school_id' => $this->teacher->school_id,
        'user_id' => $this->teacher->id,
        'title' => 'Thema-Ebene',
        'status' => 'inbox',
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $topicLevelCard->id,
        'subject_id' => $subject->id,
        'topic_id' => $topic->id,
        'unit_id' => null,
    ]);

    $this->actingAs($this->teacher, 'sanctum');
    $response = $this->getJson('/api/admin/materials/config')->assertStatus(200);

    $tree = collect($response->json('classification_tree', []));
    $subjectNode = $tree->firstWhere('id', $subject->id);
    expect($subjectNode)->not->toBeNull()
        ->and((bool) ($subjectNode['can_delete'] ?? false))->toBeFalse(); // topic level is lower than subject

    $topicNode = collect($subjectNode['topics'] ?? [])->firstWhere('id', $topic->id);
    expect($topicNode)->not->toBeNull()
        ->and((bool) ($topicNode['can_delete'] ?? false))->toBeTrue();

    $unitNode = collect($topicNode['units'] ?? [])->firstWhere('id', $unit->id);
    expect($unitNode)->not->toBeNull()
        ->and((bool) ($unitNode['can_delete'] ?? false))->toBeTrue();
});

test('teacher can create material card and gets keywords', function () {
    $this->actingAs($this->teacher, 'sanctum');

    $response = $this->postJson('/api/admin/materials/cards', [
        'data' => [
            'title' => 'Geometrie Arbeitsblatt Dreiecke',
            'source_text' => 'Dreieck Fläche Winkel',
            'notes' => 'Wiederholung Flächenberechnung',
            'subject' => 'Mathematik',
        ],
    ]);

    $response->assertStatus(200)
        ->assertJsonFragment([
            'title' => 'Geometrie Arbeitsblatt Dreiecke',
            'status' => 'inbox',
            'subject' => null,
        ]);

    $cardId = $response->json('id');
    $card = MaterialCard::findOrFail($cardId);

    expect($card->user_id)->toBe($this->teacher->id)
        ->and($card->school_id)->toBe($this->teacher->school_id)
        ->and($card->keywords)->toBeArray()
        ->and(count($card->keywords))->toBeGreaterThan(0);
});

test('quick store creates inbox card', function () {
    $this->actingAs($this->teacher, 'sanctum');

    $response = $this->postJson('/api/admin/materials/cards/quick_store', [
        'data' => [
            'title' => 'Merker Link',
            'source_url' => 'https://example.com/material',
        ],
    ]);

    $response->assertStatus(200)
        ->assertJsonFragment([
            'title' => 'Merker Link',
            'status' => 'inbox',
        ]);
});

test('teacher can only use own existing material types', function () {
    MaterialType::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'name' => 'Arbeitsblatt',
    ]);

    $this->actingAs($this->teacher, 'sanctum');

    $this->postJson('/api/admin/materials/cards', [
        'data' => [
            'title' => 'Typprüfung',
            'type' => 'Neuer Typ Lehrer',
        ],
    ])->assertStatus(422);

    $this->postJson('/api/admin/materials/cards', [
        'data' => [
            'title' => 'Typprüfung erlaubt',
            'type' => 'Arbeitsblatt',
        ],
    ])->assertStatus(200)
        ->assertJsonFragment([
            'type' => 'Arbeitsblatt',
        ]);
});

test('teacher may manage own material types and use them', function () {
    $this->actingAs($this->teacher, 'sanctum');

    $this->postJson('/api/admin/materials/types', [
        'data' => [
            'name' => 'Mein Eigener Typ',
        ],
    ])->assertStatus(200)
        ->assertJsonFragment([
            'value' => 'Mein Eigener Typ',
        ]);

    $this->postJson('/api/admin/materials/cards', [
        'data' => [
            'title' => 'Typ durch Lehrer',
            'type' => 'Mein Eigener Typ',
        ],
    ])->assertStatus(200)
        ->assertJsonFragment([
            'type' => 'Mein Eigener Typ',
        ]);
});

test('material types are separated per user', function () {
    $this->actingAs($this->teacher, 'sanctum');

    $this->postJson('/api/admin/materials/types', [
        'data' => [
            'name' => 'Nur Lehrer A',
        ],
    ])->assertStatus(200);

    $otherTeacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'other-teacher-types@materials.test',
    ]);
    $otherTeacher->assignRole('materials_moderator');

    $this->actingAs($otherTeacher, 'sanctum');

    $this->postJson('/api/admin/materials/cards', [
        'data' => [
            'title' => 'Fremder Typ',
            'type' => 'Nur Lehrer A',
        ],
    ])->assertStatus(422);
});

test('admin may manage school status values and teachers can use them', function () {
    $this->actingAs($this->admin, 'sanctum');

    $response = $this->postJson('/api/admin/materials/statuses', [
        'data' => [
            'label' => 'Zur Freigabe',
            'color' => '#123abc',
        ],
    ])->assertStatus(200)
        ->assertJsonPath('data.label', 'Zur Freigabe')
        ->assertJsonPath('data.color', '#123abc');

    $statusId = (int) $response->json('data.id');
    $statusValue = (string) $response->json('data.value');

    $this->putJson('/api/admin/materials/statuses/'.$statusId, [
        'data' => [
            'label' => 'Zur Freigabe intern',
            'color' => '#44aa66',
        ],
    ])->assertStatus(200)
        ->assertJsonPath('data.label', 'Zur Freigabe intern')
        ->assertJsonPath('data.color', '#44aa66');

    $this->actingAs($this->teacher, 'sanctum');

    $this->postJson('/api/admin/materials/cards', [
        'data' => [
            'title' => 'Statusprüfung',
            'status' => $statusValue,
        ],
    ])->assertStatus(200)
        ->assertJsonFragment([
            'status' => $statusValue,
        ]);
});

test('teacher cannot manage school status values', function () {
    $this->actingAs($this->teacher, 'sanctum');

    $this->postJson('/api/admin/materials/statuses', [
        'data' => [
            'label' => 'Freigegeben',
        ],
    ])->assertStatus(403);
});

test('admin can update school max upload size and upload is validated against it', function () {
    Storage::fake('local');

    $this->actingAs($this->admin, 'sanctum');

    $this->putJson('/api/admin/materials/file-settings', [
        'data' => [
            'max_upload_size_kb' => 100,
        ],
    ])->assertStatus(200)
        ->assertJsonPath('data.max_upload_size_kb', 100);

    $schoolTool = SchoolTool::query()->where('school_id', $this->school->id)->first();
    expect((int) ($schoolTool?->material_max_file_upload_size ?? 0))->toBe(100);

    $card = MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'title' => 'Uploadgrenze Test',
        'keywords' => [],
    ]);

    $this->actingAs($this->teacher, 'sanctum');

    $this->post(
        '/api/admin/materials/cards/'.$card->id.'/attachments/file',
        ['file' => UploadedFile::fake()->create('zu-gross.pdf', 120, 'application/pdf')],
        ['Accept' => 'application/json']
    )->assertStatus(422);

    $this->post('/api/admin/materials/cards/'.$card->id.'/attachments/file', [
        'file' => UploadedFile::fake()->create('ok.pdf', 90, 'application/pdf'),
    ])->assertStatus(200);
});

test('teacher can upload file in chunks and attach it to material card', function () {
    $this->actingAs($this->teacher, 'sanctum');

    $card = MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'title' => 'Chunk Upload Karte',
        'keywords' => [],
    ]);

    $content = str_repeat('A', 4096);
    $length = (string) strlen($content);

    $startResponse = $this
        ->withHeaders([
            'Accept' => 'text/plain',
            'Upload-Length' => $length,
            'Upload-Name' => 'chunk-upload-test.pdf',
        ])
        ->post('/api/admin/materials/uploads/chunk');

    $startResponse->assertStatus(200);
    $uploadId = trim((string) $startResponse->getContent());
    expect($uploadId)->not->toBe('');

    $patchResponse = $this->call('PATCH', '/api/admin/materials/uploads/chunk?patch='.$uploadId, [], [], [], [
        'HTTP_ACCEPT' => 'text/plain',
        'HTTP_UPLOAD_LENGTH' => $length,
        'HTTP_UPLOAD_NAME' => 'chunk-upload-test.pdf',
    ], $content);

    $patchResponse->assertStatus(200);
    expect(trim((string) $patchResponse->getContent()))->toBe($uploadId);

    $attachResponse = $this->postJson('/api/admin/materials/cards/'.$card->id.'/attachments/file-temp', [
        'data' => [
            'upload_id' => $uploadId,
            'name' => 'Chunk Test Datei',
        ],
    ]);

    $attachResponse->assertStatus(200)
        ->assertJsonFragment([
            'name' => 'Chunk Test Datei',
        ]);

    $attachmentId = $attachResponse->json('id');
    $attachment = MaterialCardAttachment::findOrFail($attachmentId);
    Storage::disk('local')->assertExists($attachment->file_path);
});

test('chunk upload respects school max upload size from settings', function () {
    $this->actingAs($this->teacher, 'sanctum');

    SchoolTool::query()->updateOrCreate(
        ['school_id' => $this->school->id],
        [
            'tutoring_student_must_be_confirmed' => false,
            'tutoring_confirmer_email' => '',
            'material_max_file_upload_size' => 1, // 1 KB
        ]
    );

    $this
        ->withHeaders([
            'Accept' => 'application/json',
            'Upload-Length' => '2048',
            'Upload-Name' => 'zu-gross.pdf',
        ])
        ->post('/api/admin/materials/uploads/chunk')
        ->assertStatus(422);
});

test('teacher cannot update school max upload size', function () {
    $this->actingAs($this->teacher, 'sanctum');

    $this->putJson('/api/admin/materials/file-settings', [
        'data' => [
            'max_upload_size_kb' => 100,
        ],
    ])->assertStatus(403);
});

test('teacher can update own materials pagination setting', function () {
    $this->actingAs($this->teacher, 'sanctum');

    $this->putJson('/api/admin/materials/user-settings', [
        'data' => [
            'materials_pagination_number' => 12,
        ],
    ])->assertStatus(200)
        ->assertJsonPath('data.materials_pagination_number', 12);

    expect((int) $this->teacher->fresh()->materials_pagination_number)->toBe(12);
});

test('index returns only own cards', function () {
    $otherTeacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'other-teacher@materials.test',
    ]);
    $otherTeacher->assignRole('teacher');

    MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'title' => 'Eigene Karte',
        'status' => 'inbox',
        'keywords' => [],
    ]);
    MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $otherTeacher->id,
        'title' => 'Fremde Karte',
        'status' => 'inbox',
        'keywords' => [],
    ]);

    $this->actingAs($this->teacher, 'sanctum');

    $response = $this->getJson('/api/admin/materials/cards');

    $response->assertStatus(200);
    $titles = collect($response->json('data'))->pluck('title')->all();

    expect($titles)->toContain('Eigene Karte')
        ->and($titles)->not->toContain('Fremde Karte');
});

test('index can filter by subject topic and unit', function () {
    $this->actingAs($this->teacher, 'sanctum');

    $this->postJson('/api/admin/materials/cards', [
        'data' => [
            'title' => 'Mathematik Brüche',
            'classifications' => [
                ['subject' => 'Mathematik', 'topic' => 'Algebra', 'unit' => 'Brüche'],
            ],
        ],
    ])->assertStatus(200);

    $this->postJson('/api/admin/materials/cards', [
        'data' => [
            'title' => 'Mathematik Gleichungen',
            'classifications' => [
                ['subject' => 'Mathematik', 'topic' => 'Algebra', 'unit' => 'Gleichungen'],
            ],
        ],
    ])->assertStatus(200);

    $this->postJson('/api/admin/materials/cards', [
        'data' => [
            'title' => 'Deutsch Grammatik',
            'classifications' => [
                ['subject' => 'Deutsch', 'topic' => 'Grammatik', 'unit' => 'Zeitformen'],
            ],
        ],
    ])->assertStatus(200);

    $subjectResponse = $this->getJson('/api/admin/materials/cards?'.http_build_query([
        'subject' => 'Mathematik',
    ]));
    $subjectResponse->assertStatus(200);
    $subjectTitles = collect($subjectResponse->json('data'))->pluck('title')->all();
    expect($subjectTitles)->toContain('Mathematik Brüche')
        ->and($subjectTitles)->toContain('Mathematik Gleichungen')
        ->and($subjectTitles)->not->toContain('Deutsch Grammatik');

    $topicResponse = $this->getJson('/api/admin/materials/cards?'.http_build_query([
        'subject' => 'Mathematik',
        'topic' => 'Algebra',
    ]));
    $topicResponse->assertStatus(200);
    $topicTitles = collect($topicResponse->json('data'))->pluck('title')->all();
    expect($topicTitles)->toContain('Mathematik Brüche')
        ->and($topicTitles)->toContain('Mathematik Gleichungen')
        ->and($topicTitles)->not->toContain('Deutsch Grammatik');

    $unitResponse = $this->getJson('/api/admin/materials/cards?'.http_build_query([
        'subject' => 'Mathematik',
        'topic' => 'Algebra',
        'unit' => 'Brüche',
    ]));
    $unitResponse->assertStatus(200);
    $unitTitles = collect($unitResponse->json('data'))->pluck('title')->all();
    expect($unitTitles)->toContain('Mathematik Brüche')
        ->and($unitTitles)->not->toContain('Mathematik Gleichungen')
        ->and($unitTitles)->not->toContain('Deutsch Grammatik');
});

test('index uses user specific materials pagination number', function () {
    $this->teacher->update([
        'materials_pagination_number' => 2,
    ]);

    foreach (range(1, 5) as $index) {
        MaterialCard::factory()->create([
            'school_id' => $this->school->id,
            'user_id' => $this->teacher->id,
            'title' => 'Eigene Karte '.$index,
            'status' => 'inbox',
            'keywords' => [],
        ]);
    }

    $this->actingAs($this->teacher, 'sanctum');

    $pageOne = $this->getJson('/api/admin/materials/cards?page=1');
    $pageOne->assertStatus(200)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.last_page', 3);
    expect(count($pageOne->json('data')))->toBe(2);

    $pageThree = $this->getJson('/api/admin/materials/cards?page=3');
    $pageThree->assertStatus(200)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.current_page', 3)
        ->assertJsonPath('meta.last_page', 3);
    expect(count($pageThree->json('data')))->toBe(1);
});

test('owner protection blocks update from another teacher', function () {
    $owner = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'owner@materials.test',
    ]);
    $owner->assignRole('teacher');

    $card = MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $owner->id,
        'title' => 'Private Karte',
        'status' => 'inbox',
        'keywords' => [],
    ]);

    $this->actingAs($this->teacher, 'sanctum');

    $this->putJson('/api/admin/materials/cards/'.$card->id, [
        'data' => [
            'title' => 'Manipuliert',
            'status' => 'done',
        ],
    ])->assertStatus(403);
});

test('linked material with nur lesen blocks edit delete and attachment mutations', function () {
    if (! Schema::hasTable('material_inbox_imports') || ! Schema::hasColumn('material_inbox_imports', 'import_mode')) {
        $this->markTestSkipped('Linked inbox import mode is not available.');
    }

    $card = createLinkedImportedCard(
        targetUser: $this->teacher,
        school: $this->school,
        schoolyear: $this->schoolyear,
        permission: MaterialShareTarget::PERMISSION_READ_ONLY,
    );
    $importBeforeUnlink = MaterialInboxImport::query()
        ->where('target_user_id', (int) $this->teacher->id)
        ->where('target_material_card_id', (int) $card->id)
        ->where('import_mode', MaterialInboxImport::MODE_LINK)
        ->latest('id')
        ->first();
    expect($importBeforeUnlink)->not->toBeNull();
    $ruleId = (int) ($importBeforeUnlink?->source_rule_id ?? 0);
    expect($ruleId)->toBeGreaterThan(0);

    $attachment = MaterialCardAttachment::query()->create([
        'material_card_id' => $card->id,
        'attachment_type' => MaterialCardAttachment::TYPE_LINK,
        'name' => 'Bestehend',
        'url' => 'https://example.org/existing',
    ]);

    $this->actingAs($this->teacher, 'sanctum');

    $this->putJson('/api/admin/materials/cards/'.$card->id, [
        'data' => [
            'title' => 'Geändert',
            'status' => MaterialCard::STATUS_DONE,
        ],
    ])->assertStatus(403);

    $this->postJson('/api/admin/materials/cards/'.$card->id.'/attachments/link', [
        'data' => [
            'url' => 'https://example.org/new',
            'name' => 'Neu',
        ],
    ])->assertStatus(403);

    $this->deleteJson('/api/admin/materials/attachments/'.$attachment->id)
        ->assertStatus(403);

    $this->deleteJson('/api/admin/materials/cards/'.$card->id)
        ->assertStatus(403);
});

test('linked material can be unlinked from materials overview endpoint', function () {
    if (! Schema::hasTable('material_inbox_imports') || ! Schema::hasColumn('material_inbox_imports', 'import_mode')) {
        $this->markTestSkipped('Linked inbox import mode is not available.');
    }

    $card = createLinkedImportedCard(
        targetUser: $this->teacher,
        school: $this->school,
        schoolyear: $this->schoolyear,
        permission: MaterialShareTarget::PERMISSION_READ_ONLY,
    );
    $importBeforeUnlink = MaterialInboxImport::query()
        ->where('target_user_id', (int) $this->teacher->id)
        ->where('target_material_card_id', (int) $card->id)
        ->where('import_mode', MaterialInboxImport::MODE_LINK)
        ->latest('id')
        ->first();
    expect($importBeforeUnlink)->not->toBeNull();
    $ruleId = (int) ($importBeforeUnlink?->source_rule_id ?? 0);
    expect($ruleId)->toBeGreaterThan(0);

    $this->actingAs($this->teacher, 'sanctum');

    $this->postJson('/api/admin/materials/cards/'.$card->id.'/unlink')
        ->assertStatus(200)
        ->assertJsonPath('message', 'Link entfernt.')
        ->assertJsonPath('data.id', (int) $card->id)
        ->assertJsonPath('data.removed', true);

    $this->assertDatabaseMissing('material_inbox_imports', [
        'target_user_id' => (int) $this->teacher->id,
        'target_material_card_id' => (int) $card->id,
        'import_mode' => MaterialInboxImport::MODE_LINK,
    ]);

    $this->assertDatabaseMissing('material_cards', [
        'id' => (int) $card->id,
        'user_id' => (int) $this->teacher->id,
    ]);

    $this->getJson('/api/admin/materials/cards/'.$card->id)
        ->assertStatus(404);

    $cardsResponse = $this->getJson('/api/admin/materials/cards')
        ->assertStatus(200);

    $listedCardIds = collect($cardsResponse->json('data'))->pluck('id')->map(fn ($id) => (int) $id)->all();
    expect($listedCardIds)->not->toContain((int) $card->id);

    $inboxUsers = $this->getJson('/api/admin/materials/shares/inbox-users')
        ->assertStatus(200);
    $sharedItems = collect($inboxUsers->json('data'))
        ->flatMap(fn ($userRow) => is_array($userRow['shared_items'] ?? null) ? $userRow['shared_items'] : [])
        ->values();
    $matchingInboxEntry = $sharedItems
        ->first(fn ($entry) => (int) ($entry['rule_id'] ?? 0) === $ruleId);
    expect($matchingInboxEntry)->not->toBeNull();
    expect((bool) ($matchingInboxEntry['is_imported'] ?? true))->toBeFalse();
});

test('linked unit can be unlinked from materials overview endpoint and removes linked materials', function () {
    if (
        ! Schema::hasTable('material_inbox_imports')
        || ! Schema::hasColumn('material_inbox_imports', 'import_mode')
        || ! Schema::hasTable('material_unit_inbox_imports')
    ) {
        $this->markTestSkipped('Linked unit inbox import tables are not available.');
    }

    $cardA = createLinkedImportedCard(
        targetUser: $this->teacher,
        school: $this->school,
        schoolyear: $this->schoolyear,
        permission: MaterialShareTarget::PERMISSION_READ_ONLY,
    );
    $cardB = createLinkedImportedCard(
        targetUser: $this->teacher,
        school: $this->school,
        schoolyear: $this->schoolyear,
        permission: MaterialShareTarget::PERMISSION_READ_WRITE,
    );

    $importA = MaterialInboxImport::query()
        ->where('target_user_id', (int) $this->teacher->id)
        ->where('target_material_card_id', (int) $cardA->id)
        ->where('import_mode', MaterialInboxImport::MODE_LINK)
        ->latest('id')
        ->first();
    expect($importA)->not->toBeNull();
    $ruleId = (int) ($importA?->source_rule_id ?? 0);
    expect($ruleId)->toBeGreaterThan(0);

    $subject = MaterialSubject::query()->create([
        'user_id' => (int) $this->teacher->id,
        'name' => 'Lehrplaene',
    ]);
    $topic = MaterialTopic::query()->create([
        'subject_id' => (int) $subject->id,
        'name' => 'AHS - Tagesschule',
    ]);
    $unit = MaterialUnit::query()->create([
        'topic_id' => (int) $topic->id,
        'name' => 'Informatik',
    ]);

    MaterialCardClassification::query()->create([
        'material_card_id' => (int) $cardA->id,
        'subject_id' => (int) $subject->id,
        'topic_id' => (int) $topic->id,
        'unit_id' => (int) $unit->id,
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => (int) $cardB->id,
        'subject_id' => (int) $subject->id,
        'topic_id' => (int) $topic->id,
        'unit_id' => (int) $unit->id,
    ]);

    MaterialUnitInboxImport::query()->create([
        'target_user_id' => (int) $this->teacher->id,
        'target_unit_id' => (int) $unit->id,
        'source_rule_id' => $ruleId,
        'source_school_id' => (int) $this->school->id,
        'source_unit_id' => 9001,
        'imported_at' => now(),
    ]);

    $this->actingAs($this->teacher, 'sanctum');

    $response = $this->postJson('/api/admin/materials/units/'.$unit->id.'/unlink')
        ->assertStatus(200)
        ->assertJsonPath('message', 'Link entfernt.')
        ->assertJsonPath('data.id', (int) $unit->id)
        ->assertJsonPath('data.removed', true)
        ->assertJsonPath('data.removed_unit', true);

    $removedCardIds = collect($response->json('data.removed_card_ids'))
        ->map(fn ($value) => (int) $value)
        ->all();
    expect($removedCardIds)->toContain((int) $cardA->id);
    expect($removedCardIds)->toContain((int) $cardB->id);

    $this->assertDatabaseMissing('material_unit_inbox_imports', [
        'target_user_id' => (int) $this->teacher->id,
        'target_unit_id' => (int) $unit->id,
    ]);

    $this->assertDatabaseMissing('material_inbox_imports', [
        'target_user_id' => (int) $this->teacher->id,
        'target_material_card_id' => (int) $cardA->id,
        'import_mode' => MaterialInboxImport::MODE_LINK,
    ]);
    $this->assertDatabaseMissing('material_inbox_imports', [
        'target_user_id' => (int) $this->teacher->id,
        'target_material_card_id' => (int) $cardB->id,
        'import_mode' => MaterialInboxImport::MODE_LINK,
    ]);

    $this->assertDatabaseMissing('material_cards', [
        'id' => (int) $cardA->id,
        'user_id' => (int) $this->teacher->id,
    ]);
    $this->assertDatabaseMissing('material_cards', [
        'id' => (int) $cardB->id,
        'user_id' => (int) $this->teacher->id,
    ]);
    $this->assertDatabaseMissing('material_units', [
        'id' => (int) $unit->id,
    ]);

    $cardsResponse = $this->getJson('/api/admin/materials/cards')
        ->assertStatus(200);
    $listedCardIds = collect($cardsResponse->json('data'))->pluck('id')->map(fn ($id) => (int) $id)->all();
    expect($listedCardIds)->not->toContain((int) $cardA->id);
    expect($listedCardIds)->not->toContain((int) $cardB->id);

    $inboxUsers = $this->getJson('/api/admin/materials/shares/inbox-users')
        ->assertStatus(200);
    $sharedItems = collect($inboxUsers->json('data'))
        ->flatMap(fn ($userRow) => is_array($userRow['shared_items'] ?? null) ? $userRow['shared_items'] : [])
        ->values();
    $matchingInboxEntry = $sharedItems
        ->first(fn ($entry) => (int) ($entry['rule_id'] ?? 0) === $ruleId);
    expect($matchingInboxEntry)->not->toBeNull();
    expect((bool) ($matchingInboxEntry['is_imported'] ?? true))->toBeFalse();
});

test('linked topic can be unlinked from materials overview endpoint and removes linked materials', function () {
    if (
        ! Schema::hasTable('material_inbox_imports')
        || ! Schema::hasColumn('material_inbox_imports', 'import_mode')
        || ! Schema::hasTable('material_topic_inbox_imports')
    ) {
        $this->markTestSkipped('Linked topic inbox import tables are not available.');
    }

    $cardA = createLinkedImportedCard(
        targetUser: $this->teacher,
        school: $this->school,
        schoolyear: $this->schoolyear,
        permission: MaterialShareTarget::PERMISSION_READ_ONLY,
    );
    $cardB = createLinkedImportedCard(
        targetUser: $this->teacher,
        school: $this->school,
        schoolyear: $this->schoolyear,
        permission: MaterialShareTarget::PERMISSION_READ_WRITE,
    );

    $importA = MaterialInboxImport::query()
        ->where('target_user_id', (int) $this->teacher->id)
        ->where('target_material_card_id', (int) $cardA->id)
        ->where('import_mode', MaterialInboxImport::MODE_LINK)
        ->latest('id')
        ->first();
    expect($importA)->not->toBeNull();
    $ruleId = (int) ($importA?->source_rule_id ?? 0);
    expect($ruleId)->toBeGreaterThan(0);

    $subject = MaterialSubject::query()->create([
        'user_id' => (int) $this->teacher->id,
        'name' => 'Lehrplaene',
    ]);
    $topic = MaterialTopic::query()->create([
        'subject_id' => (int) $subject->id,
        'name' => 'AHS - Tagesschule',
    ]);
    $unitA = MaterialUnit::query()->create([
        'topic_id' => (int) $topic->id,
        'name' => 'Informatik A',
    ]);
    $unitB = MaterialUnit::query()->create([
        'topic_id' => (int) $topic->id,
        'name' => 'Informatik B',
    ]);

    MaterialCardClassification::query()->create([
        'material_card_id' => (int) $cardA->id,
        'subject_id' => (int) $subject->id,
        'topic_id' => (int) $topic->id,
        'unit_id' => (int) $unitA->id,
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => (int) $cardB->id,
        'subject_id' => (int) $subject->id,
        'topic_id' => (int) $topic->id,
        'unit_id' => (int) $unitB->id,
    ]);

    MaterialTopicInboxImport::query()->create([
        'target_user_id' => (int) $this->teacher->id,
        'target_topic_id' => (int) $topic->id,
        'source_rule_id' => $ruleId,
        'source_school_id' => (int) $this->school->id,
        'source_topic_id' => 9001,
        'imported_at' => now(),
    ]);

    $this->actingAs($this->teacher, 'sanctum');

    $response = $this->postJson('/api/admin/materials/topics/'.$topic->id.'/unlink')
        ->assertStatus(200)
        ->assertJsonPath('message', 'Link entfernt.')
        ->assertJsonPath('data.id', (int) $topic->id)
        ->assertJsonPath('data.removed', true)
        ->assertJsonPath('data.removed_topic', true);

    $removedCardIds = collect($response->json('data.removed_card_ids'))
        ->map(fn ($value) => (int) $value)
        ->all();
    expect($removedCardIds)->toContain((int) $cardA->id);
    expect($removedCardIds)->toContain((int) $cardB->id);

    $this->assertDatabaseMissing('material_topic_inbox_imports', [
        'target_user_id' => (int) $this->teacher->id,
        'target_topic_id' => (int) $topic->id,
    ]);

    $this->assertDatabaseMissing('material_inbox_imports', [
        'target_user_id' => (int) $this->teacher->id,
        'target_material_card_id' => (int) $cardA->id,
        'import_mode' => MaterialInboxImport::MODE_LINK,
    ]);
    $this->assertDatabaseMissing('material_inbox_imports', [
        'target_user_id' => (int) $this->teacher->id,
        'target_material_card_id' => (int) $cardB->id,
        'import_mode' => MaterialInboxImport::MODE_LINK,
    ]);

    $this->assertDatabaseMissing('material_cards', [
        'id' => (int) $cardA->id,
        'user_id' => (int) $this->teacher->id,
    ]);
    $this->assertDatabaseMissing('material_cards', [
        'id' => (int) $cardB->id,
        'user_id' => (int) $this->teacher->id,
    ]);
    $this->assertDatabaseMissing('material_topics', [
        'id' => (int) $topic->id,
    ]);

    $cardsResponse = $this->getJson('/api/admin/materials/cards')
        ->assertStatus(200);
    $listedCardIds = collect($cardsResponse->json('data'))->pluck('id')->map(fn ($id) => (int) $id)->all();
    expect($listedCardIds)->not->toContain((int) $cardA->id);
    expect($listedCardIds)->not->toContain((int) $cardB->id);

    $inboxUsers = $this->getJson('/api/admin/materials/shares/inbox-users')
        ->assertStatus(200);
    $sharedItems = collect($inboxUsers->json('data'))
        ->flatMap(fn ($userRow) => is_array($userRow['shared_items'] ?? null) ? $userRow['shared_items'] : [])
        ->values();
    $matchingInboxEntry = $sharedItems
        ->first(fn ($entry) => (int) ($entry['rule_id'] ?? 0) === $ruleId);
    expect($matchingInboxEntry)->not->toBeNull();
    expect((bool) ($matchingInboxEntry['is_imported'] ?? true))->toBeFalse();
});

test('linked topic name is synchronized from source for overview endpoints', function () {
    if (
        ! Schema::hasTable('material_inbox_imports')
        || ! Schema::hasColumn('material_inbox_imports', 'import_mode')
        || ! Schema::hasTable('material_topic_inbox_imports')
    ) {
        $this->markTestSkipped('Linked topic inbox import tables are not available.');
    }

    $linkedCard = createLinkedImportedCard(
        targetUser: $this->teacher,
        school: $this->school,
        schoolyear: $this->schoolyear,
        permission: MaterialShareTarget::PERMISSION_READ_ONLY,
    );

    $linkImport = MaterialInboxImport::query()
        ->where('target_user_id', (int) $this->teacher->id)
        ->where('target_material_card_id', (int) $linkedCard->id)
        ->where('import_mode', MaterialInboxImport::MODE_LINK)
        ->latest('id')
        ->first();
    expect($linkImport)->not->toBeNull();

    $sourceCard = MaterialCard::query()->find((int) ($linkImport?->source_material_id ?? 0));
    expect($sourceCard)->not->toBeNull();

    $sourceSubject = MaterialSubject::query()->create([
        'user_id' => (int) ($sourceCard?->user_id ?? 0),
        'name' => 'Quelle Fach',
    ]);
    $sourceTopic = MaterialTopic::query()->create([
        'subject_id' => (int) $sourceSubject->id,
        'name' => 'Quelle Thema Alt',
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => (int) ($sourceCard?->id ?? 0),
        'subject_id' => (int) $sourceSubject->id,
        'topic_id' => (int) $sourceTopic->id,
        'unit_id' => null,
    ]);

    $targetSubject = MaterialSubject::query()->create([
        'user_id' => (int) $this->teacher->id,
        'name' => 'Ziel Fach',
    ]);
    $targetTopic = MaterialTopic::query()->create([
        'subject_id' => (int) $targetSubject->id,
        'name' => 'Ziel Thema Alt',
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => (int) $linkedCard->id,
        'subject_id' => (int) $targetSubject->id,
        'topic_id' => (int) $targetTopic->id,
        'unit_id' => null,
    ]);

    MaterialTopicInboxImport::query()->create([
        'target_user_id' => (int) $this->teacher->id,
        'target_topic_id' => (int) $targetTopic->id,
        'source_rule_id' => (int) ($linkImport?->source_rule_id ?? 0),
        'source_school_id' => (int) $this->school->id,
        'source_topic_id' => (int) $sourceTopic->id,
        'imported_at' => now(),
    ]);

    $sourceTopic->update(['name' => 'Quelle Thema Neu']);

    $this->actingAs($this->teacher, 'sanctum');

    $cardsResponse = $this->getJson('/api/admin/materials/cards')
        ->assertStatus(200);

    $linkedCardRow = collect($cardsResponse->json('data'))
        ->first(fn ($row) => (int) ($row['id'] ?? 0) === (int) $linkedCard->id);
    expect($linkedCardRow)->not->toBeNull();
    $classificationTopicNames = collect($linkedCardRow['classifications'] ?? [])
        ->pluck('topic')
        ->map(fn ($value) => trim((string) $value))
        ->filter()
        ->values()
        ->all();
    expect($classificationTopicNames)->toContain('Quelle Thema Neu');

    $configResponse = $this->getJson('/api/admin/materials/config')
        ->assertStatus(200);

    $subjectNode = collect($configResponse->json('classification_tree', []))
        ->firstWhere('id', (int) $targetSubject->id);
    expect($subjectNode)->not->toBeNull();
    $topicNode = collect($subjectNode['topics'] ?? [])
        ->firstWhere('id', (int) $targetTopic->id);
    expect($topicNode)->not->toBeNull();
    expect((string) ($topicNode['name'] ?? ''))->toBe('Quelle Thema Neu');

    $this->assertDatabaseHas('material_topics', [
        'id' => (int) $targetTopic->id,
        'name' => 'Quelle Thema Neu',
    ]);
});

test('linked unit name is synchronized from source for overview endpoints', function () {
    if (
        ! Schema::hasTable('material_inbox_imports')
        || ! Schema::hasColumn('material_inbox_imports', 'import_mode')
        || ! Schema::hasTable('material_unit_inbox_imports')
    ) {
        $this->markTestSkipped('Linked unit inbox import tables are not available.');
    }

    $linkedCard = createLinkedImportedCard(
        targetUser: $this->teacher,
        school: $this->school,
        schoolyear: $this->schoolyear,
        permission: MaterialShareTarget::PERMISSION_READ_ONLY,
    );

    $linkImport = MaterialInboxImport::query()
        ->where('target_user_id', (int) $this->teacher->id)
        ->where('target_material_card_id', (int) $linkedCard->id)
        ->where('import_mode', MaterialInboxImport::MODE_LINK)
        ->latest('id')
        ->first();
    expect($linkImport)->not->toBeNull();

    $sourceCard = MaterialCard::query()->find((int) ($linkImport?->source_material_id ?? 0));
    expect($sourceCard)->not->toBeNull();

    $sourceSubject = MaterialSubject::query()->create([
        'user_id' => (int) ($sourceCard?->user_id ?? 0),
        'name' => 'Quelle Fach',
    ]);
    $sourceTopic = MaterialTopic::query()->create([
        'subject_id' => (int) $sourceSubject->id,
        'name' => 'Quelle Thema',
    ]);
    $sourceUnit = MaterialUnit::query()->create([
        'topic_id' => (int) $sourceTopic->id,
        'name' => 'Quelle Einheit Alt',
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => (int) ($sourceCard?->id ?? 0),
        'subject_id' => (int) $sourceSubject->id,
        'topic_id' => (int) $sourceTopic->id,
        'unit_id' => (int) $sourceUnit->id,
    ]);

    $targetSubject = MaterialSubject::query()->create([
        'user_id' => (int) $this->teacher->id,
        'name' => 'Ziel Fach',
    ]);
    $targetTopic = MaterialTopic::query()->create([
        'subject_id' => (int) $targetSubject->id,
        'name' => 'Ziel Thema',
    ]);
    $targetUnit = MaterialUnit::query()->create([
        'topic_id' => (int) $targetTopic->id,
        'name' => 'Ziel Einheit Alt',
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => (int) $linkedCard->id,
        'subject_id' => (int) $targetSubject->id,
        'topic_id' => (int) $targetTopic->id,
        'unit_id' => (int) $targetUnit->id,
    ]);

    MaterialUnitInboxImport::query()->create([
        'target_user_id' => (int) $this->teacher->id,
        'target_unit_id' => (int) $targetUnit->id,
        'source_rule_id' => (int) ($linkImport?->source_rule_id ?? 0),
        'source_school_id' => (int) $this->school->id,
        'source_unit_id' => (int) $sourceUnit->id,
        'imported_at' => now(),
    ]);

    $sourceUnit->update(['name' => 'Quelle Einheit Neu']);

    $this->actingAs($this->teacher, 'sanctum');

    $cardsResponse = $this->getJson('/api/admin/materials/cards')
        ->assertStatus(200);

    $linkedCardRow = collect($cardsResponse->json('data'))
        ->first(fn ($row) => (int) ($row['id'] ?? 0) === (int) $linkedCard->id);
    expect($linkedCardRow)->not->toBeNull();
    $classificationUnitNames = collect($linkedCardRow['classifications'] ?? [])
        ->pluck('unit')
        ->map(fn ($value) => trim((string) $value))
        ->filter()
        ->values()
        ->all();
    expect($classificationUnitNames)->toContain('Quelle Einheit Neu');

    $configResponse = $this->getJson('/api/admin/materials/config')
        ->assertStatus(200);

    $subjectNode = collect($configResponse->json('classification_tree', []))
        ->firstWhere('id', (int) $targetSubject->id);
    expect($subjectNode)->not->toBeNull();
    $topicNode = collect($subjectNode['topics'] ?? [])
        ->firstWhere('id', (int) $targetTopic->id);
    expect($topicNode)->not->toBeNull();
    $unitNode = collect($topicNode['units'] ?? [])
        ->firstWhere('id', (int) $targetUnit->id);
    expect($unitNode)->not->toBeNull();
    expect((string) ($unitNode['name'] ?? ''))->toBe('Quelle Einheit Neu');

    $this->assertDatabaseHas('material_units', [
        'id' => (int) $targetUnit->id,
        'name' => 'Quelle Einheit Neu',
    ]);
});

test('linked material with lesen schreiben allows edit and append but blocks delete operations', function () {
    if (! Schema::hasTable('material_inbox_imports') || ! Schema::hasColumn('material_inbox_imports', 'import_mode')) {
        $this->markTestSkipped('Linked inbox import mode is not available.');
    }

    $card = createLinkedImportedCard(
        targetUser: $this->teacher,
        school: $this->school,
        schoolyear: $this->schoolyear,
        permission: MaterialShareTarget::PERMISSION_READ_WRITE,
    );

    $attachment = MaterialCardAttachment::query()->create([
        'material_card_id' => $card->id,
        'attachment_type' => MaterialCardAttachment::TYPE_LINK,
        'name' => 'Bestehend',
        'url' => 'https://example.org/existing',
    ]);

    $this->actingAs($this->teacher, 'sanctum');

    $this->putJson('/api/admin/materials/cards/'.$card->id, [
        'data' => [
            'title' => 'Geändert',
            'status' => MaterialCard::STATUS_DONE,
        ],
    ])->assertStatus(200)
        ->assertJsonPath('title', 'Geändert');

    $indexAfterUpdate = $this->getJson('/api/admin/materials/cards')
        ->assertStatus(200);

    $updatedCardRow = collect($indexAfterUpdate->json('data'))
        ->first(fn ($row) => (int) ($row['id'] ?? 0) === (int) $card->id);
    expect((string) ($updatedCardRow['title'] ?? ''))->toBe('Geändert');

    $import = MaterialInboxImport::query()
        ->where('target_material_card_id', (int) $card->id)
        ->latest('id')
        ->first();
    expect($import)->not->toBeNull();

    $sourceCard = MaterialCard::query()->find((int) ($import?->source_material_id ?? 0));
    expect($sourceCard)->not->toBeNull();
    expect((string) ($sourceCard?->title ?? ''))->toBe('Geändert');

    $this->postJson('/api/admin/materials/cards/'.$card->id.'/attachments/link', [
        'data' => [
            'url' => 'https://example.org/new',
            'name' => 'Neu',
        ],
    ])->assertStatus(200);

    $this->deleteJson('/api/admin/materials/attachments/'.$attachment->id)
        ->assertStatus(403);

    $this->deleteJson('/api/admin/materials/cards/'.$card->id)
        ->assertStatus(403);
});

test('linked material with lesen schreiben keeps source attachments visible in destination on equal timestamps', function () {
    if (! Schema::hasTable('material_inbox_imports') || ! Schema::hasColumn('material_inbox_imports', 'import_mode')) {
        $this->markTestSkipped('Linked inbox import mode is not available.');
    }

    $card = createLinkedImportedCard(
        targetUser: $this->teacher,
        school: $this->school,
        schoolyear: $this->schoolyear,
        permission: MaterialShareTarget::PERMISSION_READ_WRITE,
    );

    $import = MaterialInboxImport::query()
        ->where('target_material_card_id', (int) $card->id)
        ->latest('id')
        ->first();
    expect($import)->not->toBeNull();

    $sourceCard = MaterialCard::query()->find((int) ($import?->source_material_id ?? 0));
    expect($sourceCard)->not->toBeNull();

    MaterialCardAttachment::query()->create([
        'material_card_id' => (int) ($sourceCard?->id ?? 0),
        'attachment_type' => MaterialCardAttachment::TYPE_LINK,
        'name' => 'Quelle',
        'url' => 'https://example.org/source',
    ]);

    MaterialCard::query()
        ->whereIn('id', [(int) ($sourceCard?->id ?? 0), (int) $card->id])
        ->update(['updated_at' => now()->startOfSecond()]);

    $this->actingAs($this->teacher, 'sanctum');

    $this->getJson('/api/admin/materials/cards/'.$card->id)
        ->assertStatus(200)
        ->assertJsonPath('attachments.0.name', 'Quelle')
        ->assertJsonPath('attachments.0.url', 'https://example.org/source');
});

test('linked material with vollzugriff allows attachment delete but still blocks material delete', function () {
    if (! Schema::hasTable('material_inbox_imports') || ! Schema::hasColumn('material_inbox_imports', 'import_mode')) {
        $this->markTestSkipped('Linked inbox import mode is not available.');
    }

    $card = createLinkedImportedCard(
        targetUser: $this->teacher,
        school: $this->school,
        schoolyear: $this->schoolyear,
        permission: MaterialShareTarget::PERMISSION_FULL_ACCESS,
    );

    $attachment = MaterialCardAttachment::query()->create([
        'material_card_id' => $card->id,
        'attachment_type' => MaterialCardAttachment::TYPE_LINK,
        'name' => 'Bestehend',
        'url' => 'https://example.org/existing',
    ]);

    $this->actingAs($this->teacher, 'sanctum');

    $this->putJson('/api/admin/materials/cards/'.$card->id, [
        'data' => [
            'title' => 'Geändert',
            'status' => MaterialCard::STATUS_DONE,
        ],
    ])->assertStatus(200)
        ->assertJsonPath('title', 'Geändert');

    $this->postJson('/api/admin/materials/cards/'.$card->id.'/attachments/link', [
        'data' => [
            'url' => 'https://example.org/new',
            'name' => 'Neu',
        ],
    ])->assertStatus(200);

    $this->deleteJson('/api/admin/materials/attachments/'.$attachment->id)
        ->assertNoContent();
    $this->assertDatabaseMissing('material_card_attachments', [
        'id' => $attachment->id,
    ]);

    $this->deleteJson('/api/admin/materials/cards/'.$card->id)
        ->assertStatus(403);
});

test('adding link attachment stores attachment and refreshes keywords', function () {
    $card = MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'title' => 'Chemie Einführung',
        'keywords' => [],
    ]);

    $this->actingAs($this->teacher, 'sanctum');

    $this->postJson('/api/admin/materials/cards/'.$card->id.'/attachments/link', [
        'data' => [
            'url' => 'https://example.org/chemie/stoechiometrie',
            'name' => 'Stöchiometrie Link',
        ],
    ])->assertStatus(200);

    $card->refresh();

    expect(MaterialCardAttachment::where('material_card_id', $card->id)->count())->toBe(1)
        ->and($card->keywords)->toBeArray()
        ->and(count($card->keywords))->toBeGreaterThan(0);
});

test('adding file attachment stores file and allows download', function () {
    Storage::fake('local');

    $card = MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'title' => 'Geschichte Mittelalter',
        'keywords' => [],
    ]);

    $this->actingAs($this->teacher, 'sanctum');

    $uploadResponse = $this->post('/api/admin/materials/cards/'.$card->id.'/attachments/file', [
        'file' => UploadedFile::fake()->create('mittelalter-arbeitsblatt.pdf', 200, 'application/pdf'),
    ]);

    $uploadResponse->assertStatus(200);
    $attachmentId = $uploadResponse->json('id');
    $uploadResponse->assertJsonPath('preview_url', '/api/admin/materials/attachments/'.$attachmentId.'/preview');
    $attachment = MaterialCardAttachment::findOrFail($attachmentId);

    Storage::disk('local')->assertExists($attachment->file_path);

    $this->get('/api/admin/materials/attachments/'.$attachment->id.'/download')
        ->assertStatus(200);

    $previewResponse = $this->get('/api/admin/materials/attachments/'.$attachment->id.'/preview');
    $previewResponse->assertStatus(200);
    expect(strtolower((string) $previewResponse->headers->get('content-type')))->toContain('application/pdf');
});

test('adding file attachment on s3 keeps canonical relative materials path structure', function () {
    Config::set('filesystems.default', 's3');
    Storage::fake('s3');

    $card = MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'title' => 'S3 Pfadstruktur Test',
        'keywords' => [],
    ]);

    $this->actingAs($this->teacher, 'sanctum');

    $uploadResponse = $this->post('/api/admin/materials/cards/'.$card->id.'/attachments/file', [
        'file' => UploadedFile::fake()->create('pfadstruktur.pdf', 120, 'application/pdf'),
    ]);

    $uploadResponse->assertStatus(200);
    $attachment = MaterialCardAttachment::findOrFail((int) $uploadResponse->json('id'));
    $path = (string) ($attachment->file_path ?? '');

    expect($path)->toStartWith('materials/schools/'.$this->school->id.'/users/'.$this->teacher->id.'/cards/'.$card->id.'/')
        ->and(preg_match('#^materials/schools/\d+/users/\d+/cards/\d+/\d{4}/\d{2}/#', $path))->toBe(1);

    Storage::disk('s3')->assertExists($path);
});

test('owner attachment download and preview do not use s3 when default disk is local', function () {
    Config::set('filesystems.default', 'local');
    Storage::fake('local');
    Storage::fake('s3');

    $card = MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'title' => 'S3 Fallback Quelle',
        'keywords' => [],
    ]);

    $path = 'materials/source/s3-fallback.pdf';
    Storage::disk('s3')->put($path, 's3-fallback-content');

    $attachment = MaterialCardAttachment::query()->create([
        'material_card_id' => $card->id,
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'name' => 's3-fallback.pdf',
        'file_path' => $path,
        'mime_type' => 'application/pdf',
        'size_bytes' => 1024,
    ]);

    $this->actingAs($this->teacher, 'sanctum');

    $this->get('/api/admin/materials/attachments/'.$attachment->id.'/download')
        ->assertStatus(404);

    $previewResponse = $this->get('/api/admin/materials/attachments/'.$attachment->id.'/preview');
    $previewResponse->assertStatus(404);
});

test('excel attachment preview is rendered as html', function () {
    Storage::fake('local');

    $card = MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'title' => 'Excel Vorschau',
        'keywords' => [],
    ]);

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'Kategorie');
    $sheet->setCellValue('B1', 'Wert');
    $sheet->setCellValue('A2', 'Punkte');
    $sheet->setCellValue('B2', 42);

    $tmpFile = tempnam(sys_get_temp_dir(), 'materials-xlsx-');
    expect($tmpFile)->toBeString();

    \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx')->save($tmpFile);
    $xlsxContent = file_get_contents($tmpFile);
    @unlink($tmpFile);
    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);

    expect($xlsxContent)->toBeString();

    $this->actingAs($this->teacher, 'sanctum');

    $uploadResponse = $this->post('/api/admin/materials/cards/'.$card->id.'/attachments/file', [
        'file' => UploadedFile::fake()->createWithContent('auswertung.xlsx', (string) $xlsxContent),
    ]);

    $attachment = MaterialCardAttachment::findOrFail($uploadResponse->json('id'));
    $response = $this->get('/api/admin/materials/attachments/'.$attachment->id.'/preview');

    $response->assertStatus(200)
        ->assertSee('Punkte')
        ->assertSee('Kategorie');

    expect(strtolower((string) $response->headers->get('content-type')))->toContain('text/html');
});

test('word attachment preview is rendered as html', function () {
    Storage::fake('local');

    $card = MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'title' => 'Word Vorschau',
        'keywords' => [],
    ]);

    $document = new \PhpOffice\PhpWord\PhpWord;
    $section = $document->addSection();
    $section->addText('Word Vorschau Inhalt');

    $tmpFile = tempnam(sys_get_temp_dir(), 'materials-docx-');
    expect($tmpFile)->toBeString();

    \PhpOffice\PhpWord\IOFactory::createWriter($document, 'Word2007')->save($tmpFile);
    $docxContent = file_get_contents($tmpFile);
    @unlink($tmpFile);

    expect($docxContent)->toBeString();

    $this->actingAs($this->teacher, 'sanctum');

    $uploadResponse = $this->post('/api/admin/materials/cards/'.$card->id.'/attachments/file', [
        'file' => UploadedFile::fake()->createWithContent('text.docx', (string) $docxContent),
    ]);

    $attachment = MaterialCardAttachment::findOrFail($uploadResponse->json('id'));
    $response = $this->get('/api/admin/materials/attachments/'.$attachment->id.'/preview');

    $response->assertStatus(200)
        ->assertSee('Word Vorschau Inhalt');

    expect(strtolower((string) $response->headers->get('content-type')))->toContain('text/html');
});

test('powerpoint attachment preview is rendered as html', function () {
    Storage::fake('local');

    $card = MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'title' => 'PowerPoint Vorschau',
        'keywords' => [],
    ]);

    $presentation = new \PhpOffice\PhpPresentation\PhpPresentation;
    $slide = $presentation->getActiveSlide();
    $shape = new \PhpOffice\PhpPresentation\Shape\RichText;
    $shape->setHeight(120)->setWidth(620)->setOffsetX(32)->setOffsetY(48);
    $shape->createTextRun('PowerPoint Vorschau Inhalt');
    $slide->addShape($shape);

    $tmpFile = tempnam(sys_get_temp_dir(), 'materials-pptx-');
    expect($tmpFile)->toBeString();

    \PhpOffice\PhpPresentation\IOFactory::createWriter($presentation, 'PowerPoint2007')->save($tmpFile);
    $pptxContent = file_get_contents($tmpFile);
    @unlink($tmpFile);

    expect($pptxContent)->toBeString();

    $this->actingAs($this->teacher, 'sanctum');

    $uploadResponse = $this->post('/api/admin/materials/cards/'.$card->id.'/attachments/file', [
        'file' => UploadedFile::fake()->createWithContent('folien.pptx', (string) $pptxContent),
    ]);

    $attachment = MaterialCardAttachment::findOrFail($uploadResponse->json('id'));
    $response = $this->get('/api/admin/materials/attachments/'.$attachment->id.'/preview');

    $response->assertStatus(200)
        ->assertSee('PowerPoint Vorschau Inhalt');

    expect(strtolower((string) $response->headers->get('content-type')))->toContain('text/html');
});

test('adding remote image attachment stores image file from url', function () {
    Storage::fake('local');

    Http::fake([
        'https://example.org/*' => Http::response('fake-image-bytes', 200, [
            'Content-Type' => 'image/jpeg',
        ]),
    ]);

    $card = MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'title' => 'Bildimport',
        'keywords' => [],
    ]);

    $this->actingAs($this->teacher, 'sanctum');

    $response = $this->postJson('/api/admin/materials/cards/'.$card->id.'/attachments/image-url', [
        'data' => [
            'url' => 'https://example.org/media/diagramm.jpg',
            'name' => 'Diagramm aus Web',
        ],
    ]);

    $response->assertStatus(200)
        ->assertJsonFragment([
            'attachment_type' => 'file',
            'name' => 'Diagramm aus Web',
            'mime_type' => 'image/jpeg',
            'source_url' => 'https://example.org/media/diagramm.jpg',
        ]);

    $attachmentId = $response->json('id');
    $attachment = MaterialCardAttachment::findOrFail($attachmentId);

    Storage::disk('local')->assertExists($attachment->file_path);
    expect($attachment->url)->toBeNull()
        ->and($attachment->source_url)->toBe('https://example.org/media/diagramm.jpg')
        ->and($attachment->downloaded_at)->not->toBeNull();
});

test('attachment rename updates stored attachment name', function () {
    Storage::fake('local');

    $card = MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'title' => 'Physik Experimente',
        'keywords' => [],
    ]);

    $this->actingAs($this->teacher, 'sanctum');

    $uploadResponse = $this->post('/api/admin/materials/cards/'.$card->id.'/attachments/file', [
        'file' => UploadedFile::fake()->create('experimente.pdf', 120, 'application/pdf'),
        'name' => 'Alte Bezeichnung',
    ]);

    $attachment = MaterialCardAttachment::findOrFail($uploadResponse->json('id'));

    $this->patchJson('/api/admin/materials/attachments/'.$attachment->id, [
        'data' => [
            'name' => 'Neue Bezeichnung',
        ],
    ])->assertStatus(200)
        ->assertJsonFragment([
            'id' => $attachment->id,
            'name' => 'Neue Bezeichnung',
        ]);

    expect($attachment->fresh()->name)->toBe('Neue Bezeichnung');
});

test('attachment delete removes file from storage', function () {
    Storage::fake('local');

    $card = MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'title' => 'Deutsch Grammatik',
        'keywords' => [],
    ]);

    $this->actingAs($this->teacher, 'sanctum');

    $uploadResponse = $this->post('/api/admin/materials/cards/'.$card->id.'/attachments/file', [
        'file' => UploadedFile::fake()->create('grammatik-uebung.pdf', 100, 'application/pdf'),
    ]);

    $attachment = MaterialCardAttachment::findOrFail($uploadResponse->json('id'));
    Storage::disk('local')->assertExists($attachment->file_path);

    $this->deleteJson('/api/admin/materials/attachments/'.$attachment->id)
        ->assertStatus(204);

    Storage::disk('local')->assertMissing($attachment->file_path);
    expect(MaterialCardAttachment::where('id', $attachment->id)->exists())->toBeFalse();
});

test('teacher can delete own card', function () {
    $card = MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'title' => 'Löschbar',
        'keywords' => [],
    ]);

    $this->actingAs($this->teacher, 'sanctum');

    $this->deleteJson('/api/admin/materials/cards/'.$card->id)
        ->assertStatus(204);

    expect(MaterialCard::where('id', $card->id)->exists())->toBeFalse();
});

test('teacher can permanently delete a previously deleted card', function () {
    Storage::fake('local');

    $subject = MaterialSubject::query()->create([
        'user_id' => $this->teacher->id,
        'name' => 'Mathematik',
    ]);
    $topic = $subject->topics()->create(['name' => 'Algebra']);
    $unit = $topic->units()->create(['name' => 'Gleichungen']);

    $card = MaterialCard::query()->create([
        'school_id' => $this->teacher->school_id,
        'user_id' => $this->teacher->id,
        'title' => 'Final löschen',
        'status' => 'inbox',
    ]);

    MaterialCardClassification::query()->create([
        'material_card_id' => $card->id,
        'subject_id' => $subject->id,
        'topic_id' => $topic->id,
        'unit_id' => $unit->id,
    ]);

    $filePath = 'materials/test/final-loeschen.pdf';
    Storage::disk('local')->put($filePath, 'pdf-content');
    MaterialCardAttachment::query()->create([
        'material_card_id' => $card->id,
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'name' => 'final-loeschen.pdf',
        'file_path' => $filePath,
        'mime_type' => 'application/pdf',
        'size_bytes' => 11,
    ]);

    $this->actingAs($this->teacher, 'sanctum');

    $this->deleteJson('/api/admin/materials/cards/'.$card->id)
        ->assertStatus(204);

    expect(MaterialCard::onlyTrashed()->where('id', $card->id)->exists())->toBeTrue();
    $this->assertDatabaseHas('material_card_deleted_classifications', [
        'material_card_id' => $card->id,
    ]);

    $this->deleteJson('/api/admin/materials/cards/deleted/'.$card->id)
        ->assertStatus(204);

    expect(MaterialCard::withTrashed()->where('id', $card->id)->exists())->toBeFalse();
    $this->assertDatabaseMissing('material_card_deleted_classifications', [
        'material_card_id' => $card->id,
    ]);
    Storage::disk('local')->assertMissing($filePath);
});

test('restore deleted card recreates taxonomy path when original path was deleted', function () {
    $subject = MaterialSubject::query()->create([
        'user_id' => $this->teacher->id,
        'name' => 'Biologie',
    ]);
    $topic = $subject->topics()->create(['name' => 'Zelle']);
    $unit = $topic->units()->create(['name' => 'Mikroskopie']);

    $card = MaterialCard::query()->create([
        'school_id' => $this->teacher->school_id,
        'user_id' => $this->teacher->id,
        'title' => 'Restore Test',
        'status' => 'inbox',
    ]);

    MaterialCardClassification::query()->create([
        'material_card_id' => $card->id,
        'subject_id' => $subject->id,
        'topic_id' => $topic->id,
        'unit_id' => $unit->id,
    ]);

    $this->actingAs($this->teacher, 'sanctum');

    $this->deleteJson('/api/admin/materials/cards/'.$card->id)
        ->assertStatus(204);

    $this->assertDatabaseHas('material_card_deleted_classifications', [
        'material_card_id' => $card->id,
        'subject_name' => 'Biologie',
        'topic_name' => 'Zelle',
        'unit_name' => 'Mikroskopie',
    ]);

    $this->deleteJson('/api/admin/materials/subjects/'.$subject->id)
        ->assertStatus(204);

    $this->postJson('/api/admin/materials/cards/restore-deleted/'.$card->id)
        ->assertStatus(200)
        ->assertJsonFragment([
            'subject' => 'Biologie',
            'topic' => 'Zelle',
            'unit' => 'Mikroskopie',
        ]);

    $newSubject = MaterialSubject::query()
        ->where('user_id', $this->teacher->id)
        ->where('name', 'Biologie')
        ->first();

    expect($newSubject)->not->toBeNull();

    $newTopic = $newSubject->topics()->where('name', 'Zelle')->first();
    expect($newTopic)->not->toBeNull();

    $newUnit = $newTopic->units()->where('name', 'Mikroskopie')->first();
    expect($newUnit)->not->toBeNull();

    $classification = MaterialCardClassification::query()
        ->where('material_card_id', $card->id)
        ->first();

    expect($classification)->not->toBeNull()
        ->and((int) $classification->subject_id)->toBe((int) $newSubject->id)
        ->and((int) $classification->topic_id)->toBe((int) $newTopic->id)
        ->and((int) $classification->unit_id)->toBe((int) $newUnit->id);

    $this->assertDatabaseMissing('material_card_deleted_classifications', [
        'material_card_id' => $card->id,
    ]);
});
