<?php

use App\Models\Aba;
use App\Models\AbaAttachment;
use App\Models\Licence;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);

    $this->otherSchool = School::factory()->create();
    $this->otherSchoolyear = Schoolyear::factory()->create(['school_id' => $this->otherSchool->id]);

    Role::firstOrCreate(['name' => 'aba_teacher', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->abaLicence = Licence::firstOrCreate(
        ['name' => 'ABA'],
        ['long_name' => 'ABA', 'is_selectable' => true]
    );

    $this->school->licences()->syncWithoutDetaching([
        $this->abaLicence->id => ['valid_until' => now()->addYear()->toDateString()],
    ]);

    $this->user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'confirmed_at' => now(),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);
    $this->user->assignRole('aba_teacher');
});

// ── INDEX ───────────────────────────────────────────────────────────────────

it('lists abas for the authenticated user in the current schoolyear', function () {
    Aba::factory()->count(3)->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->user->id,
    ]);

    // ABA from a different schoolyear – must NOT appear
    Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->otherSchoolyear->id,
        'user_id' => $this->user->id,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/admin/abas')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('does not return abas from other users', function () {
    $otherUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'confirmed_at' => now(),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);
    $otherUser->assignRole('aba_teacher');

    Aba::factory()->count(2)->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $otherUser->id,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/admin/abas')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('denies index to users without aba_teacher role', function () {
    $admin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'confirmed_at' => now(),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);
    $admin->assignRole('admin');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/abas')
        ->assertForbidden();
});

// ── STORE ───────────────────────────────────────────────────────────────────

it('stores a new aba with correct school/user/schoolyear assignment', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/admin/abas', [
            'data' => [
                'title' => 'Meine ABA',
                'student_name' => 'Max Mustermann',
                'evaluated_on' => null,
            ],
        ])
        ->assertStatus(201)
        ->assertJsonFragment([
            'title' => 'Meine ABA',
            'student_name' => 'Max Mustermann',
            'created_on' => now()->toDateString(),
            'evaluated_on' => null,
        ]);

    $this->assertDatabaseHas('abas', [
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->user->id,
        'title' => 'Meine ABA',
        'student_name' => 'Max Mustermann',
    ]);
});

it('stores a new aba with evaluated_on date', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/admin/abas', [
            'data' => [
                'title' => 'Abgeschlossene ABA',
                'student_name' => 'Anna Muster',
                'evaluated_on' => '2026-01-20',
            ],
        ])
        ->assertStatus(201)
        ->assertJsonFragment(['evaluated_on' => '2026-01-20']);
});

it('validates required fields on store', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/admin/abas', ['data' => []])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['data.title', 'data.student_name']);
});

it('allows multiple abas per user in the same schoolyear', function () {
    foreach (range(1, 5) as $i) {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/abas', [
                'data' => [
                    'title' => "ABA Nr. {$i}",
                    'student_name' => "Schüler {$i}",
                    'created_on' => '2026-01-01',
                ],
            ])
            ->assertStatus(201);
    }

    expect(Aba::where('user_id', $this->user->id)->count())->toBe(5);
});

// ── SHOW ────────────────────────────────────────────────────────────────────

it('shows an aba with its attachments', function () {
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->user->id,
    ]);

    AbaAttachment::factory()->count(2)->create(['aba_id' => $aba->id]);

    $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/admin/abas/{$aba->id}")
        ->assertOk()
        ->assertJsonFragment(['id' => $aba->id])
        ->assertJsonCount(2, 'attachments');
});

it('denies show for an aba from a different user', function () {
    $foreignAba = Aba::factory()->create([
        'school_id' => $this->otherSchool->id,
        'schoolyear_id' => $this->otherSchoolyear->id,
        'user_id' => User::factory()->create(['school_id' => $this->otherSchool->id])->id,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/admin/abas/{$foreignAba->id}")
        ->assertForbidden();
});

// ── UPDATE ──────────────────────────────────────────────────────────────────

it('updates an existing aba', function () {
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->user->id,
        'title' => 'Alte ABA',
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/admin/abas/{$aba->id}", [
            'data' => ['title' => 'Neue ABA', 'evaluated_on' => '2026-03-01'],
        ])
        ->assertOk()
        ->assertJsonFragment(['title' => 'Neue ABA', 'evaluated_on' => '2026-03-01']);

    $this->assertDatabaseHas('abas', ['id' => $aba->id, 'title' => 'Neue ABA']);
});

it('denies update for an aba from a different user', function () {
    $foreignAba = Aba::factory()->create([
        'school_id' => $this->otherSchool->id,
        'schoolyear_id' => $this->otherSchoolyear->id,
        'user_id' => User::factory()->create(['school_id' => $this->otherSchool->id])->id,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/admin/abas/{$foreignAba->id}", [
            'data' => ['title' => 'Hack'],
        ])
        ->assertForbidden();
});

// ── DESTROY ─────────────────────────────────────────────────────────────────

it('soft-deletes an aba', function () {
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->user->id,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/admin/abas/{$aba->id}")
        ->assertNoContent();

    $this->assertSoftDeleted('abas', ['id' => $aba->id]);
});

it('denies destroy for an aba from a different user', function () {
    $foreignAba = Aba::factory()->create([
        'school_id' => $this->otherSchool->id,
        'schoolyear_id' => $this->otherSchoolyear->id,
        'user_id' => User::factory()->create(['school_id' => $this->otherSchool->id])->id,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/admin/abas/{$foreignAba->id}")
        ->assertForbidden();
});

// ── RELATIONSHIPS ────────────────────────────────────────────────────────────

it('allows multiple attachments per aba', function () {
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->user->id,
    ]);

    AbaAttachment::factory()->count(3)->create(['aba_id' => $aba->id]);

    expect($aba->attachments()->count())->toBe(3);
});

it('confirms aba belongs to school, schoolyear, and user', function () {
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->user->id,
    ]);

    $aba->load(['school', 'schoolyear', 'user']);

    expect($aba->school->id)->toBe($this->school->id)
        ->and($aba->schoolyear->id)->toBe($this->schoolyear->id)
        ->and($aba->user->id)->toBe($this->user->id);
});
