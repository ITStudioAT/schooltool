<?php

/**
 * Models Tests
 *
 * Comprehensive tests for all model classes covering:
 * - Factory creation
 * - Fillable attributes
 * - Relationships
 * - Casts
 * - Scopes
 * - Custom methods
 * - Validation behavior
 */

use App\Models\Licence;
use App\Models\QueueTest;
use App\Models\Register;
use App\Models\RegisterDate;
use App\Models\RegisterDateBooking;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\Teacher;
use App\Models\TeachingCourse;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Create required roles for tests
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'register_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
});

describe('User Model', function () {
    it('can be created via factory', function () {
        $user = User::factory()->create();

        expect($user)->toBeInstanceOf(User::class)
            ->and($user->id)->toBeGreaterThan(0);
    });

    it('has correct fillable attributes', function () {
        $fillable = (new User)->getFillable();

        expect($fillable)->toContain('school_id', 'email', 'password', 'first_name', 'last_name', 'phone');
    });

    it('hides password and remember_token in array', function () {
        $user = User::factory()->create(['password' => 'secret123']);

        $array = $user->toArray();

        expect($array)->not->toHaveKey('password')
            ->and($array)->not->toHaveKey('remember_token');
    });

    it('casts email_verified_at to datetime', function () {
        $user = User::factory()->create(['email_verified_at' => now()]);

        expect($user->email_verified_at)->toBeInstanceOf(Carbon::class);
    });

    it('belongs to a school', function () {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);

        expect($user->selectedSchool)->toBeInstanceOf(School::class)
            ->and($user->selectedSchool->id)->toBe($school->id);
    });

    it('belongs to a schoolyear', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        $user = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
        ]);

        expect($user->selectedSchoolyear)->toBeInstanceOf(Schoolyear::class)
            ->and($user->selectedSchoolyear->id)->toBe($schoolyear->id);
    });

    it('has many register date bookings', function () {
        $user = User::factory()->create();

        expect($user->registerDateBookings())->toBeInstanceOf(HasMany::class);
    });

    it('can generate uuid', function () {
        $user = User::factory()->create();
        $uuid = $user->generateUuid();

        $user->refresh();

        expect($uuid)->toBeString()
            ->and(Str::isUuid($uuid))->toBeTrue()
            ->and($user->uuid)->toBe($uuid)
            ->and($user->uuid_at)->not->toBeNull();
    });

    it('can check valid uuid', function () {
        $user = User::factory()->create();
        $uuid = $user->generateUuid();

        $user->refresh();

        expect($user->checkUuid($uuid))->toBeTrue();
    });

    it('rejects invalid uuid', function () {
        $user = User::factory()->create();
        $user->generateUuid();

        expect($user->checkUuid('invalid-uuid'))->toBeFalse();
    });

    it('rejects expired uuid', function () {
        $user = User::factory()->create();
        $uuid = $user->generateUuid();
        $user->uuid_at = now()->subHours(3);
        $user->save();

        expect($user->checkUuid($uuid))->toBeFalse();
    });

    it('can mark email as verified', function () {
        $user = User::factory()->create(['email_verified_at' => null]);
        $user->generateUuid();

        $user->emailVerified();

        expect($user->email_verified_at)->not->toBeNull()
            ->and($user->uuid)->toBeNull()
            ->and($user->uuid_at)->toBeNull();
    });

    it('can check for dependencies', function () {
        $user = User::factory()->create();

        expect($user->hasDependencies())->toBeFalse();
    });

    it('can use teachers scope', function () {
        $school = School::factory()->create();
        $teacher = User::factory()->create(['school_id' => $school->id]);
        $teacher->assignRole('teacher');
        $normalUser = User::factory()->create(['school_id' => $school->id]);

        $teachers = User::teachers()->get();

        expect($teachers)->toHaveCount(1)
            ->and($teachers->first()->id)->toBe($teacher->id);
    });

    it('can use bySchoolAndRole scope', function () {
        $school = School::factory()->create();
        $admin = User::factory()->create(['school_id' => $school->id]);
        $admin->assignRole('admin');
        $user = User::factory()->create(['school_id' => $school->id]);

        $admins = User::bySchoolAndRole($school->id, 'admin')->get();

        expect($admins)->toHaveCount(1)
            ->and($admins->first()->id)->toBe($admin->id);
    });
});

describe('School Model', function () {
    it('can be created via factory', function () {
        $school = School::factory()->create();

        expect($school)->toBeInstanceOf(School::class)
            ->and($school->id)->toBeGreaterThan(0);
    });

    it('has correct fillable attributes', function () {
        $fillable = (new School)->getFillable();

        expect($fillable)->toContain('long_name', 'short_name', 'email', 'logo', 'is_selectable');
    });

    it('has many schoolyears', function () {
        $school = School::factory()->create();

        expect($school->schoolyears())->toBeInstanceOf(HasMany::class);
    });

    it('has many registers', function () {
        $school = School::factory()->create();

        expect($school->registers())->toBeInstanceOf(HasMany::class);
    });

    it('has many users', function () {
        $school = School::factory()->create();

        expect($school->users())->toBeInstanceOf(HasMany::class);
    });

    it('has many licences', function () {
        $school = School::factory()->create();

        expect($school->licences())->toBeInstanceOf(BelongsToMany::class);
    });

    it('has one active schoolyear', function () {
        $school = School::factory()->create();

        expect($school->activeSchoolyear())->toBeInstanceOf(HasOne::class);
    });

    it('can use selectables scope', function () {
        $selectable = School::factory()->create(['is_selectable' => 1]);
        $nonSelectable = School::factory()->create(['is_selectable' => 0]);

        $schools = School::selectables()->get();

        expect($schools->contains($selectable))->toBeTrue()
            ->and($schools->contains($nonSelectable))->toBeFalse();
    });
});

describe('Teacher Model', function () {
    it('can be created', function () {
        $school = School::factory()->create();
        $teacher = Teacher::create([
            'school_id' => $school->id,
            'email' => 'teacher@test.de',
            'first_name' => 'Hans',
            'last_name' => 'Mueller',
            'short' => 'MUE',
        ]);

        expect($teacher)->toBeInstanceOf(Teacher::class)
            ->and($teacher->id)->toBeGreaterThan(0);
    });

    it('has correct fillable attributes', function () {
        $fillable = (new Teacher)->getFillable();

        expect($fillable)->toContain('school_id', 'email', 'first_name', 'last_name', 'short', 'token');
    });

    it('casts token_expires_at to datetime', function () {
        $school = School::factory()->create();
        $teacher = Teacher::create([
            'school_id' => $school->id,
            'email' => 'teacher@test.de',
            'first_name' => 'Hans',
            'last_name' => 'Mueller',
            'short' => 'MUE',
            'token_expires_at' => now(),
        ]);

        expect($teacher->token_expires_at)->toBeInstanceOf(Carbon::class);
    });

    it('belongs to school', function () {
        $school = School::factory()->create();
        $teacher = Teacher::create([
            'school_id' => $school->id,
            'email' => 'teacher@test.de',
            'first_name' => 'Hans',
            'last_name' => 'Mueller',
            'short' => 'MUE',
        ]);

        expect($teacher->school())->toBeInstanceOf(BelongsTo::class)
            ->and($teacher->school->id)->toBe($school->id);
    });

    it('can set token with expiration', function () {
        $school = School::factory()->create();
        $teacher = Teacher::create([
            'school_id' => $school->id,
            'email' => 'teacher@test.de',
            'first_name' => 'Hans',
            'last_name' => 'Mueller',
            'short' => 'MUE',
        ]);

        $token = $teacher->setToken(60);

        expect($token)->toBeString()
            ->and($token)->toHaveLength(6)
            ->and($teacher->token)->toBe($token)
            ->and($teacher->token_expires_at)->not->toBeNull();
    });
});

describe('Register Model', function () {
    it('can be created via factory', function () {
        $register = Register::factory()->create();

        expect($register)->toBeInstanceOf(Register::class)
            ->and($register->id)->toBeGreaterThan(0);
    });

    it('has correct fillable attributes', function () {
        $fillable = (new Register)->getFillable();

        expect($fillable)->toContain('school_id', 'schoolyear_id', 'name', 'max_registrations', 'is_active');
    });

    it('belongs to school', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        $register = Register::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
        ]);

        expect($register->school())->toBeInstanceOf(BelongsTo::class)
            ->and($register->school->id)->toBe($school->id);
    });

    it('belongs to schoolyear', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        $register = Register::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
        ]);

        expect($register->schoolyear())->toBeInstanceOf(BelongsTo::class)
            ->and($register->schoolyear->id)->toBe($schoolyear->id);
    });

    it('has many dates', function () {
        $register = Register::factory()->create();

        expect($register->dates())->toBeInstanceOf(HasMany::class);
    });

    it('has many users', function () {
        $register = Register::factory()->create();

        expect($register->users())->toBeInstanceOf(BelongsToMany::class);
    });
});

describe('RegisterDate Model', function () {
    it('can be created via factory', function () {
        $date = RegisterDate::factory()->create();

        expect($date)->toBeInstanceOf(RegisterDate::class)
            ->and($date->id)->toBeGreaterThan(0);
    });

    it('has many bookings', function () {
        $date = RegisterDate::factory()->create();

        expect($date->bookings())->toBeInstanceOf(HasMany::class);
    });
});

describe('RegisterDateBooking Model', function () {
    it('can be created via factory', function () {
        $booking = RegisterDateBooking::factory()->create();

        expect($booking)->toBeInstanceOf(RegisterDateBooking::class)
            ->and($booking->id)->toBeGreaterThan(0);
    });

    it('belongs to user', function () {
        $user = User::factory()->create();
        $booking = RegisterDateBooking::factory()->create(['user_id' => $user->id]);

        expect($booking->user())->toBeInstanceOf(BelongsTo::class)
            ->and($booking->user->id)->toBe($user->id);
    });

    it('belongs to register date', function () {
        $date = RegisterDate::factory()->create();
        $booking = RegisterDateBooking::factory()->create(['register_date_id' => $date->id]);

        expect($booking->registerDate())->toBeInstanceOf(BelongsTo::class)
            ->and($booking->registerDate->id)->toBe($date->id);
    });
});

describe('Schoolyear Model', function () {
    it('can be created via factory', function () {
        $schoolyear = Schoolyear::factory()->create();

        expect($schoolyear)->toBeInstanceOf(Schoolyear::class)
            ->and($schoolyear->id)->toBeGreaterThan(0);
    });

    it('has correct fillable attributes', function () {
        $fillable = (new Schoolyear)->getFillable();

        expect($fillable)->toContain('school_id', 'name', 'from', 'until', 'sem_2_start');
    });

    it('can check for dependencies', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

        expect($schoolyear->hasDependencies())->toBeFalse();

        // One user alone is not considered a blocking dependency
        User::factory()->create(['schoolyear_id' => $schoolyear->id, 'school_id' => $school->id]);
        expect($schoolyear->hasDependencies())->toBeFalse();

        // More than one user with this schoolyear is considered a dependency
        User::factory()->create(['schoolyear_id' => $schoolyear->id, 'school_id' => $school->id]);

        expect($schoolyear->hasDependencies())->toBeTrue();
    });
});

describe('Licence Model', function () {
    it('can be created', function () {
        $licence = Licence::create([
            'name' => 'Test Licence',
            'long_name' => 'Test Licence Name',
            'is_selectable' => 1,
        ]);

        expect($licence)->toBeInstanceOf(Licence::class)
            ->and($licence->id)->toBeGreaterThan(0);
    });

    it('has correct fillable attributes', function () {
        $fillable = (new Licence)->getFillable();

        expect($fillable)->toContain('name', 'long_name', 'is_selectable');
    });

    it('has many schools', function () {
        $licence = Licence::create(['name' => 'Test', 'long_name' => 'Test Licence', 'is_selectable' => 1]);

        expect($licence->schools())->toBeInstanceOf(BelongsToMany::class);
    });
});

describe('SchoolLicence Model', function () {
    it('can be created', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'Test', 'long_name' => 'Test Licence', 'is_selectable' => 1]);

        $schoolLicence = SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => now()->addYear(),
        ]);

        expect($schoolLicence)->toBeInstanceOf(SchoolLicence::class)
            ->and($schoolLicence->id)->toBeGreaterThan(0);
    });

    it('has correct fillable attributes', function () {
        $fillable = (new SchoolLicence)->getFillable();

        expect($fillable)->toContain('school_id', 'licence_id', 'valid_until');
    });

    it('belongs to school', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'Test', 'long_name' => 'Test Licence', 'is_selectable' => 1]);
        $schoolLicence = SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
        ]);

        expect($schoolLicence->school())->toBeInstanceOf(BelongsTo::class)
            ->and($schoolLicence->school->id)->toBe($school->id);
    });

    it('belongs to licence', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'Test', 'long_name' => 'Test Licence', 'is_selectable' => 1]);
        $schoolLicence = SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
        ]);

        expect($schoolLicence->licence())->toBeInstanceOf(BelongsTo::class)
            ->and($schoolLicence->licence->id)->toBe($licence->id);
    });
});

describe('SchoolTool Model', function () {
    it('can be created via factory', function () {
        $schoolTool = SchoolTool::factory()->create();

        expect($schoolTool)->toBeInstanceOf(SchoolTool::class)
            ->and($schoolTool->id)->toBeGreaterThan(0);
    });

    it('has correct fillable attributes', function () {
        $fillable = (new SchoolTool)->getFillable();

        expect($fillable)->toContain('school_id', 'register_visible_admin')
            ->and($fillable)->not->toContain('tutoring_max_offers_per_student', 'tutoring_confirmer_email');
    });

    it('casts health_at to datetime', function () {
        $schoolTool = SchoolTool::factory()->create(['health_at' => now()]);

        expect($schoolTool->health_at)->toBeInstanceOf(Carbon::class);
    });
});

test('retired TutoringOffer model is not available', function () {
    expect(class_exists('App\\Models\\TutoringOffer'))->toBeFalse();
});

test('retired TutoringOfferRequest model is not available', function () {
    expect(class_exists('App\\Models\\TutoringOfferRequest'))->toBeFalse();
});

test('retired TutoringSubject model is not available', function () {
    expect(class_exists('App\\Models\\TutoringSubject'))->toBeFalse();
});

describe('QueueTest Model', function () {
    it('can be created with required fields', function () {
        $user = User::factory()->create();
        $queueTest = QueueTest::create([
            'user_id' => $user->id,
            'status' => 'pending',
            'dispatched_at' => now(),
        ]);

        expect($queueTest)->toBeInstanceOf(QueueTest::class)
            ->and($queueTest->id)->toBeString();
    });

    it('auto-generates uuid as primary key', function () {
        $user = User::factory()->create();
        $queueTest = QueueTest::create([
            'user_id' => $user->id,
            'status' => 'pending',
            'dispatched_at' => now(),
        ]);

        expect(Str::isUuid($queueTest->id))->toBeTrue();
    });

    it('belongs to user', function () {
        $user = User::factory()->create();
        $queueTest = QueueTest::create([
            'user_id' => $user->id,
            'status' => 'pending',
            'dispatched_at' => now(),
        ]);

        expect($queueTest->user())->toBeInstanceOf(BelongsTo::class)
            ->and($queueTest->user->id)->toBe($user->id);
    });

    it('prunes queue diagnostics older than one day', function () {
        $user = User::factory()->create();
        $expiredQueueTest = QueueTest::create([
            'user_id' => $user->id,
            'status' => 'completed',
            'dispatched_at' => now()->subDays(2),
        ]);
        $expiredQueueTest->forceFill(['created_at' => now()->subDays(2)])->save();

        $recentQueueTest = QueueTest::create([
            'user_id' => $user->id,
            'status' => 'completed',
            'dispatched_at' => now(),
        ]);

        $prunableIds = (new QueueTest)->prunable()->pluck('id');

        expect($prunableIds)->toContain($expiredQueueTest->id)
            ->and($prunableIds)->not->toContain($recentQueueTest->id);
    });
});

describe('Role Model', function () {
    it('can be created', function () {
        $role = Role::firstOrCreate(['name' => 'test_role', 'guard_name' => 'web']);

        expect($role)->toBeInstanceOf(Role::class)
            ->and($role->id)->toBeGreaterThan(0);
    });

    it('has correct guard name', function () {
        $role = Role::firstOrCreate(['name' => 'test_role', 'guard_name' => 'web']);

        expect($role->guard_name)->toBe('web');
    });

    it('can be assigned to users', function () {
        $user = User::factory()->create();
        $role = Role::where('name', 'admin')->first();

        $user->assignRole($role);

        expect($user->hasRole('admin'))->toBeTrue();
    });
});

describe('Model Relationships Integration', function () {
    it('maintains proper school to user relationship', function () {
        $school = School::factory()->create();
        $user1 = User::factory()->create(['school_id' => $school->id]);
        $user2 = User::factory()->create(['school_id' => $school->id]);

        $users = $school->users;

        expect($users)->toHaveCount(2)
            ->and($users->pluck('id')->toArray())->toContain($user1->id, $user2->id);
    });

    it('maintains proper register to dates relationship', function () {
        $register = Register::factory()->create();
        $date1 = RegisterDate::factory()->create(['register_id' => $register->id]);
        $date2 = RegisterDate::factory()->create(['register_id' => $register->id]);

        $dates = $register->dates;

        expect($dates)->toHaveCount(2)
            ->and($dates->pluck('id')->toArray())->toContain($date1->id, $date2->id);
    });

    it('keeps shared school and user relations without retired tutoring relations', function () {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);

        expect($user->selectedSchool->is($school))->toBeTrue()
            ->and(method_exists($user, 'tutoringOffers'))->toBeFalse();
    });

    it('maintains school to licence many-to-many relationship', function () {
        $school = School::factory()->create();
        $licence1 = Licence::create(['name' => 'Licence1', 'long_name' => 'Test Licence 1', 'is_selectable' => 1]);
        $licence2 = Licence::create(['name' => 'Licence2', 'long_name' => 'Test Licence 2', 'is_selectable' => 1]);

        $school->licences()->attach($licence1->id, ['valid_until' => now()->addYear()]);
        $school->licences()->attach($licence2->id, ['valid_until' => now()->addYear()]);

        expect($school->licences)->toHaveCount(2)
            ->and($school->licences->pluck('id')->toArray())->toContain($licence1->id, $licence2->id);
    });
});

describe('TeachingCourse Model', function () {
    it('can be created via factory', function () {
        $course = TeachingCourse::factory()->create();

        expect($course)->toBeInstanceOf(TeachingCourse::class)
            ->and($course->id)->toBeGreaterThan(0);
    });

    it('has correct fillable attributes', function () {
        $fillable = (new TeachingCourse)->getFillable();

        expect($fillable)->toContain('school_id', 'schoolyear_id', 'user_id', 'title', 'classes');
    });

    it('casts classes to array', function () {
        $course = TeachingCourse::factory()->create([
            'classes' => ['1A', '2B', '3C'],
        ]);

        expect($course->classes)->toBeArray()
            ->and($course->classes)->toContain('1A', '2B', '3C');
    });

    it('belongs to school', function () {
        $school = School::factory()->create();
        $course = TeachingCourse::factory()->create(['school_id' => $school->id]);

        expect($course->school())->toBeInstanceOf(BelongsTo::class)
            ->and($course->school->id)->toBe($school->id);
    });

    it('belongs to schoolyear', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        $course = TeachingCourse::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
        ]);

        expect($course->schoolyear())->toBeInstanceOf(BelongsTo::class)
            ->and($course->schoolyear->id)->toBe($schoolyear->id);
    });

    it('belongs to user (teacher)', function () {
        $school = School::factory()->create();
        $teacher = User::factory()->create(['school_id' => $school->id]);
        $course = TeachingCourse::factory()->create([
            'school_id' => $school->id,
            'user_id' => $teacher->id,
        ]);

        expect($course->user())->toBeInstanceOf(BelongsTo::class)
            ->and($course->user->id)->toBe($teacher->id);
    });

    it('can be created without teacher (user_id null)', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        $course = TeachingCourse::factory()->withoutTeacher()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
        ]);

        expect($course->user_id)->toBeNull()
            ->and($course->user)->toBeNull();
    });

    it('can store empty classes array', function () {
        $course = TeachingCourse::factory()->create([
            'classes' => [],
        ]);

        expect($course->classes)->toBeArray()
            ->and($course->classes)->toBeEmpty();
    });

    it('can store null classes', function () {
        $course = TeachingCourse::factory()->create([
            'classes' => null,
        ]);

        expect($course->classes)->toBeNull();
    });

    it('factory forSchool method works correctly', function () {
        $school = School::factory()->create();
        $course = TeachingCourse::factory()->forSchool($school)->create();

        expect($course->school_id)->toBe($school->id);
    });

    it('factory forSchoolyear method works correctly', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        $course = TeachingCourse::factory()->forSchoolyear($schoolyear)->create([
            'school_id' => $school->id,
        ]);

        expect($course->schoolyear_id)->toBe($schoolyear->id);
    });

    it('factory forTeacher method works correctly', function () {
        $school = School::factory()->create();
        $teacher = User::factory()->create(['school_id' => $school->id]);
        $course = TeachingCourse::factory()->forTeacher($teacher)->create([
            'school_id' => $school->id,
        ]);

        expect($course->user_id)->toBe($teacher->id);
    });

    it('factory withClasses method works correctly', function () {
        $classes = ['5A', '5B', '6A'];
        $course = TeachingCourse::factory()->withClasses($classes)->create();

        expect($course->classes)->toBe($classes);
    });
});

describe('Model Cascading and Dependencies', function () {
    it('user can check for booking dependencies', function () {
        $user = User::factory()->create();
        $date = RegisterDate::factory()->create();
        RegisterDateBooking::factory()->create([
            'user_id' => $user->id,
            'register_date_id' => $date->id,
        ]);

        expect($user->hasDependencies())->toBeTrue();
    });

    it('checks shared user dependencies after tutoring tables have been removed', function () {
        $user = User::factory()->create();

        expect($user->hasDependencies())->toBeFalse()
            ->and(Schema::hasTable('tutoring_offers'))->toBeFalse();
    });

    it('user without dependencies returns false', function () {
        $user = User::factory()->create();

        expect($user->hasDependencies())->toBeFalse();
    });
});
