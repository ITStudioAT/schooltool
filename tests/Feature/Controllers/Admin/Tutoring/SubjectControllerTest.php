<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\TutoringOffer;
use App\Models\TutoringSubject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create roles
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'tutoring_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

    // Create test school
    $this->school = School::factory()->create([
        'short_name' => 'TestSchool',
        'long_name' => 'Test School Name',
    ]);

    // Create another school for isolation tests
    $this->otherSchool = School::factory()->create([
        'short_name' => 'OtherSchool',
        'long_name' => 'Other School',
    ]);

    $tutoringLicence = Licence::firstOrCreate(
        ['name' => 'Nachhilfetool'],
        ['long_name' => 'Nachhilfetool', 'is_selectable' => true]
    );
    $this->school->licences()->attach($tutoringLicence->id, [
        'valid_until' => now()->addYear()->toDateString(),
    ]);

    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'tutoring_visible_admin' => true,
        'tutoring_visible_user' => true,
    ]);

    // Create admin user
    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Admin',
        'last_name' => 'User',
        'email' => 'admin@school.com',
    ]);
    $this->admin->assignRole('admin');

    // Create tutoring_admin user
    $this->tutoringAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Tutoring',
        'last_name' => 'Admin',
        'email' => 'tutoringadmin@school.com',
    ]);
    $this->tutoringAdmin->assignRole('tutoring_admin');

    // Create regular user
    $this->regularUser = User::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Regular',
        'last_name' => 'User',
        'email' => 'regular@school.com',
    ]);
    $this->regularUser->assignRole('user');

    // Create test subjects
    $this->subject1 = TutoringSubject::create([
        'school_id' => $this->school->id,
        'short_name' => 'Math',
        'long_name' => 'Mathematics',
        'must_be_accepted' => false,
        'email_mentors' => null,
    ]);

    $this->subject2 = TutoringSubject::create([
        'school_id' => $this->school->id,
        'short_name' => 'Phys',
        'long_name' => 'Physics',
        'must_be_accepted' => true,
        'email_mentors' => ['mentor@school.com'],
    ]);

    $this->subject3 = TutoringSubject::create([
        'school_id' => $this->school->id,
        'short_name' => 'Chem',
        'long_name' => 'Chemistry',
        'must_be_accepted' => false,
        'email_mentors' => ['chem1@school.com', 'chem2@school.com'],
    ]);

    // Create subject in other school
    $this->otherSchoolSubject = TutoringSubject::create([
        'school_id' => $this->otherSchool->id,
        'short_name' => 'Bio',
        'long_name' => 'Biology',
        'must_be_accepted' => false,
        'email_mentors' => null,
    ]);
});

describe('index', function () {
    it('returns 401 when user is not authenticated', function () {
        $response = $this->getJson('/api/admin/tutoring/subjects');

        $response->assertStatus(401);
    });

    it('returns 403 when user does not have admin or tutoring_admin role', function () {
        $response = $this->actingAs($this->regularUser)->getJson('/api/admin/tutoring/subjects');

        $response->assertStatus(403);
    });

    it('returns subjects for admin', function () {
        $response = $this->actingAs($this->admin)->getJson('/api/admin/tutoring/subjects');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => ['id', 'short_name', 'long_name', 'must_be_accepted', 'email_mentors'],
            ])
            ->assertJsonCount(3);
    });

    it('returns subjects for tutoring_admin', function () {
        $response = $this->actingAs($this->tutoringAdmin)->getJson('/api/admin/tutoring/subjects');

        $response->assertStatus(200)
            ->assertJsonCount(3);
    });

    it('only returns subjects from same school', function () {
        $response = $this->actingAs($this->admin)->getJson('/api/admin/tutoring/subjects');

        $response->assertStatus(200);

        $subjectIds = collect($response->json())->pluck('id')->all();
        expect($subjectIds)->toContain($this->subject1->id)
            ->and($subjectIds)->toContain($this->subject2->id)
            ->and($subjectIds)->toContain($this->subject3->id)
            ->and($subjectIds)->not->toContain($this->otherSchoolSubject->id);
    });

    it('orders subjects by short_name', function () {
        $response = $this->actingAs($this->admin)->getJson('/api/admin/tutoring/subjects');

        $response->assertStatus(200);

        $subjects = $response->json();
        expect($subjects[0]['short_name'])->toBe('Chem')
            ->and($subjects[1]['short_name'])->toBe('Math')
            ->and($subjects[2]['short_name'])->toBe('Phys');
    });

    it('returns correct subject data structure', function () {
        $response = $this->actingAs($this->admin)->getJson('/api/admin/tutoring/subjects');

        $response->assertStatus(200);

        $mathSubject = collect($response->json())->firstWhere('short_name', 'Math');
        expect($mathSubject['id'])->toBe($this->subject1->id)
            ->and($mathSubject['short_name'])->toBe('Math')
            ->and($mathSubject['long_name'])->toBe('Mathematics')
            ->and($mathSubject['must_be_accepted'])->toBe(false)
            ->and($mathSubject['email_mentors'])->toBeNull();
    });

    it('returns subject with email_mentors array', function () {
        $response = $this->actingAs($this->admin)->getJson('/api/admin/tutoring/subjects');

        $response->assertStatus(200);

        $physSubject = collect($response->json())->firstWhere('short_name', 'Phys');
        expect($physSubject['email_mentors'])->toBe(['mentor@school.com']);
    });
});

describe('update', function () {
    it('returns 401 when user is not authenticated', function () {
        $response = $this->putJson("/api/admin/tutoring/subjects/{$this->subject1->id}", [
            'data' => [
                'id' => $this->subject1->id,
                'short_name' => 'Math',
                'long_name' => 'Mathematics',
                'must_be_accepted' => false,
            ],
        ]);

        $response->assertStatus(401);
    });

    it('returns 403 when user does not have admin or tutoring_admin role', function () {
        $response = $this->actingAs($this->regularUser)->putJson("/api/admin/tutoring/subjects/{$this->subject1->id}", [
            'data' => [
                'id' => $this->subject1->id,
                'short_name' => 'Math',
                'long_name' => 'Mathematics',
                'must_be_accepted' => false,
            ],
        ]);

        $response->assertStatus(403);
    });

    it('updates subject for admin', function () {
        $data = [
            'data' => [
                'id' => $this->subject1->id,
                'short_name' => 'NewMath',
                'long_name' => 'New Mathematics',
                'must_be_accepted' => true,
                'email_mentors' => ['newmentor@school.com'],
            ],
        ];

        $response = $this->actingAs($this->admin)->putJson("/api/admin/tutoring/subjects/{$this->subject1->id}", $data);

        $response->assertStatus(200)
            ->assertJsonPath('short_name', 'NewMath')
            ->assertJsonPath('long_name', 'New Mathematics')
            ->assertJsonPath('must_be_accepted', true)
            ->assertJsonPath('email_mentors', ['newmentor@school.com']);

        $this->subject1->refresh();
        expect($this->subject1->short_name)->toBe('NewMath')
            ->and($this->subject1->long_name)->toBe('New Mathematics')
            ->and($this->subject1->must_be_accepted)->toBe(true)
            ->and($this->subject1->email_mentors)->toBe(['newmentor@school.com']);
    });

    it('updates subject for tutoring_admin', function () {
        $data = [
            'data' => [
                'id' => $this->subject2->id,
                'short_name' => 'Phys',
                'long_name' => 'Updated Physics',
                'must_be_accepted' => false,
            ],
        ];

        $response = $this->actingAs($this->tutoringAdmin)->putJson("/api/admin/tutoring/subjects/{$this->subject2->id}", $data);

        $response->assertStatus(200);

        $this->subject2->refresh();
        expect($this->subject2->long_name)->toBe('Updated Physics')
            ->and($this->subject2->must_be_accepted)->toBe(false);
    });

    it('filters out empty email strings from email_mentors', function () {
        $data = [
            'data' => [
                'id' => $this->subject1->id,
                'short_name' => 'Math',
                'long_name' => 'Mathematics',
                'must_be_accepted' => false,
                'email_mentors' => ['valid@school.com', '', '  ', 'another@school.com'],
            ],
        ];

        $response = $this->actingAs($this->admin)->putJson("/api/admin/tutoring/subjects/{$this->subject1->id}", $data);

        $response->assertStatus(200);

        $this->subject1->refresh();
        expect($this->subject1->email_mentors)->toBe(['valid@school.com', 'another@school.com']);
    });

    it('sets email_mentors to null when only empty strings provided', function () {
        $data = [
            'data' => [
                'id' => $this->subject1->id,
                'short_name' => 'Math',
                'long_name' => 'Mathematics',
                'must_be_accepted' => false,
                'email_mentors' => ['', '  ', '   '],
            ],
        ];

        $response = $this->actingAs($this->admin)->putJson("/api/admin/tutoring/subjects/{$this->subject1->id}", $data);

        $response->assertStatus(200);

        $this->subject1->refresh();
        expect($this->subject1->email_mentors)->toBeNull();
    });

    it('validates required fields', function () {
        $response = $this->actingAs($this->admin)->putJson("/api/admin/tutoring/subjects/{$this->subject1->id}", [
            'data' => [
                'id' => $this->subject1->id,
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data.short_name', 'data.long_name']);
    });

    it('validates short_name max length', function () {
        $data = [
            'data' => [
                'id' => $this->subject1->id,
                'short_name' => 'ThisIsTooLong', // More than 10 characters
                'long_name' => 'Mathematics',
                'must_be_accepted' => false,
            ],
        ];

        $response = $this->actingAs($this->admin)->putJson("/api/admin/tutoring/subjects/{$this->subject1->id}", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data.short_name']);
    });

    it('validates email format in email_mentors', function () {
        $data = [
            'data' => [
                'id' => $this->subject1->id,
                'short_name' => 'Math',
                'long_name' => 'Mathematics',
                'must_be_accepted' => false,
                'email_mentors' => ['valid@school.com', 'invalid-email'],
            ],
        ];

        $response = $this->actingAs($this->admin)->putJson("/api/admin/tutoring/subjects/{$this->subject1->id}", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data.email_mentors.1']);
    });

    it('validates id exists', function () {
        $data = [
            'data' => [
                'id' => 99999,
                'short_name' => 'Test',
                'long_name' => 'Test Subject',
                'must_be_accepted' => false,
            ],
        ];

        $response = $this->actingAs($this->admin)->putJson("/api/admin/tutoring/subjects/{$this->subject1->id}", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data.id']);
    });

    it('cannot update a subject from another school', function () {
        $this->actingAs($this->tutoringAdmin)
            ->putJson("/api/admin/tutoring/subjects/{$this->otherSchoolSubject->id}", [
                'data' => [
                    'id' => $this->otherSchoolSubject->id,
                    'short_name' => 'Hack',
                    'long_name' => 'Compromised',
                    'must_be_accepted' => false,
                ],
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['data.id']);

        expect($this->otherSchoolSubject->fresh()->long_name)->toBe('Biology');
    });
});

describe('destroy', function () {
    it('returns 401 when user is not authenticated', function () {
        $response = $this->deleteJson("/api/admin/tutoring/subjects/{$this->subject1->id}");

        $response->assertStatus(401);
    });

    it('returns 403 when user does not have admin or tutoring_admin role', function () {
        $response = $this->actingAs($this->regularUser)->deleteJson("/api/admin/tutoring/subjects/{$this->subject1->id}");

        $response->assertStatus(403);
    });

    it('deletes subject without dependencies for admin', function () {
        $response = $this->actingAs($this->admin)->deleteJson("/api/admin/tutoring/subjects/{$this->subject1->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('tutoring_subjects', [
            'id' => $this->subject1->id,
        ]);
    });

    it('deletes subject without dependencies for tutoring_admin', function () {
        $response = $this->actingAs($this->tutoringAdmin)->deleteJson("/api/admin/tutoring/subjects/{$this->subject2->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('tutoring_subjects', [
            'id' => $this->subject2->id,
        ]);
    });

    it('returns 422 when subject has dependencies', function () {
        // Create an offer for the subject to create a dependency
        $user = User::factory()->create(['school_id' => $this->school->id]);
        TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $user->id,
            'subject_id' => $this->subject1->id,
            'title' => 'Test Offer',
            'description' => 'Test',
            'classes' => ['10' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 15,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->deleteJson("/api/admin/tutoring/subjects/{$this->subject1->id}");

        $response->assertStatus(422);

        // Subject should still exist
        $this->assertDatabaseHas('tutoring_subjects', [
            'id' => $this->subject1->id,
        ]);
    });

    it('cannot delete a subject from another school', function () {
        $this->actingAs($this->admin)
            ->deleteJson("/api/admin/tutoring/subjects/{$this->otherSchoolSubject->id}")
            ->assertNotFound();

        $this->assertModelExists($this->otherSchoolSubject);
    });
});

describe('createSubjects', function () {
    it('returns 401 when user is not authenticated', function () {
        $response = $this->postJson('/api/admin/tutoring/create_subjects', [
            'data' => [
                [
                    'short_name' => 'Eng',
                    'long_name' => 'English',
                    'must_be_accepted' => false,
                ],
            ],
        ]);

        $response->assertStatus(401);
    });

    it('returns 403 when user does not have admin or tutoring_admin role', function () {
        $response = $this->actingAs($this->regularUser)->postJson('/api/admin/tutoring/create_subjects', [
            'data' => [
                [
                    'short_name' => 'Eng',
                    'long_name' => 'English',
                    'must_be_accepted' => false,
                ],
            ],
        ]);

        $response->assertStatus(403);
    });

    it('creates multiple subjects for admin', function () {
        $data = [
            'data' => [
                [
                    'short_name' => 'Eng',
                    'long_name' => 'English',
                    'must_be_accepted' => false,
                    'email_mentors' => null,
                ],
                [
                    'short_name' => 'Ger',
                    'long_name' => 'German',
                    'must_be_accepted' => true,
                    'email_mentors' => ['german@school.com'],
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/admin/tutoring/create_subjects', $data);

        $response->assertStatus(204);

        $this->assertDatabaseHas('tutoring_subjects', [
            'school_id' => $this->school->id,
            'short_name' => 'Eng',
            'long_name' => 'English',
        ]);

        $this->assertDatabaseHas('tutoring_subjects', [
            'school_id' => $this->school->id,
            'short_name' => 'Ger',
            'long_name' => 'German',
        ]);
    });

    it('creates subjects for tutoring_admin', function () {
        $data = [
            'data' => [
                [
                    'short_name' => 'Art',
                    'long_name' => 'Arts',
                    'must_be_accepted' => false,
                ],
            ],
        ];

        $response = $this->actingAs($this->tutoringAdmin)->postJson('/api/admin/tutoring/create_subjects', $data);

        $response->assertStatus(204);

        $this->assertDatabaseHas('tutoring_subjects', [
            'school_id' => $this->school->id,
            'short_name' => 'Art',
            'long_name' => 'Arts',
        ]);
    });

    it('validates data is required array', function () {
        $response = $this->actingAs($this->admin)->postJson('/api/admin/tutoring/create_subjects', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data']);
    });

    it('validates email format in email_mentors for bulk create', function () {
        $data = [
            'data' => [
                [
                    'short_name' => 'Test',
                    'long_name' => 'Test Subject',
                    'must_be_accepted' => true,
                    'email_mentors' => ['invalid-email'],
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/admin/tutoring/create_subjects', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data.0.email_mentors.0']);
    });
});
