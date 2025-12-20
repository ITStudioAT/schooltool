<?php

use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\TeacherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new TeacherService();

    // Create required role
    Role::create(['name' => 'teacher', 'guard_name' => 'web']);

    // Create test school and schoolyear
    $this->school = School::factory()->create([
        'short_name' => 'TestSchool',
        'long_name' => 'Test School Name',
    ]);

    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);
});

describe('create', function () {
    it('creates a teacher with valid data', function () {
        $data = [
            'short' => 'KRO',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'max@example.com',
        ];

        $teacher = $this->service->create($this->school->id, $data);

        expect($teacher)->toBeInstanceOf(User::class)
            ->and($teacher->school_id)->toBe($this->school->id)
            ->and($teacher->short)->toBe('KRO')
            ->and($teacher->first_name)->toBe('Max')
            ->and($teacher->last_name)->toBe('Mustermann')
            ->and($teacher->email)->toBe('max@example.com')
            ->and($teacher->email_verified_at)->not->toBeNull()
            ->and($teacher->confirmed_at)->not->toBeNull()
            ->and($teacher->password)->not->toBeNull();
    });

    it('assigns teacher role to created user', function () {
        $data = [
            'short' => 'KRO',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'max@example.com',
        ];

        $teacher = $this->service->create($this->school->id, $data);

        expect($teacher->hasRole('teacher'))->toBeTrue();
    });

    it('sets email_verified_at and confirmed_at timestamps', function () {
        $beforeCreation = now();

        $data = [
            'short' => 'KRO',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'max@example.com',
        ];

        $teacher = $this->service->create($this->school->id, $data);

        $afterCreation = now();

        expect($teacher->email_verified_at)->not->toBeNull()
            ->and($teacher->email_verified_at->isAfter($beforeCreation->subSecond()))->toBeTrue()
            ->and($teacher->email_verified_at->isBefore($afterCreation->addSecond()))->toBeTrue()
            ->and($teacher->confirmed_at)->not->toBeNull();
    });

    it('throws 409 exception when short is already taken', function () {
        User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'short' => 'KRO',
            'email' => 'different@example.com',
        ]);

        $data = [
            'short' => 'KRO',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'max@example.com',
        ];

        $this->service->create($this->school->id, $data);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Das Kurzzeichen wird bereits verwendet');

    it('throws 409 exception when email is already taken', function () {
        User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'short' => 'KRO',
            'email' => 'max@example.com',
        ]);

        $data = [
            'short' => 'MUS',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'max@example.com',
        ];

        $this->service->create($this->school->id, $data);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Die E-Mail-Adresse wird bereits verwendet');

    it('allows same short in different schools', function () {
        $otherSchool = School::factory()->create();

        User::factory()->create([
            'school_id' => $otherSchool->id,
            'short' => 'KRO',
            'email' => 'other@example.com',
        ]);

        $data = [
            'short' => 'KRO',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'max@example.com',
        ];

        $teacher = $this->service->create($this->school->id, $data);

        expect($teacher->short)->toBe('KRO')
            ->and($teacher->school_id)->toBe($this->school->id);
    });

    it('hashes the password', function () {
        $data = [
            'short' => 'KRO',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'max@example.com',
        ];

        $teacher = $this->service->create($this->school->id, $data);

        expect($teacher->password)->not->toBeNull()
            ->and(strlen($teacher->password))->toBeGreaterThan(20); // Hashed password is long
    });
});

describe('update', function () {
    it('updates teacher with valid data', function () {
        $teacher = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'short' => 'KRO',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'max@example.com',
        ]);
        $teacher->assignRole('teacher');

        $data = [
            'id' => $teacher->id,
            'short' => 'MUS',
            'first_name' => 'Maria',
            'last_name' => 'Mueller',
            'email' => 'maria@example.com',
        ];

        $updated = $this->service->update($this->school->id, $data);

        expect($updated->short)->toBe('MUS')
            ->and($updated->first_name)->toBe('Maria')
            ->and($updated->last_name)->toBe('Mueller')
            ->and($updated->email)->toBe('maria@example.com');
    });

    it('throws 401 when school_id does not match', function () {
        $otherSchool = School::factory()->create();

        $teacher = User::factory()->create([
            'school_id' => $otherSchool->id,
            'short' => 'KRO',
            'email' => 'max@example.com',
        ]);

        $data = [
            'id' => $teacher->id,
            'short' => 'MUS',
            'first_name' => 'Maria',
            'last_name' => 'Mueller',
            'email' => 'maria@example.com',
        ];

        $this->service->update($this->school->id, $data);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Diese Änderung kann nicht durchgeführt werden.');

    it('throws 409 when new short is already taken by another teacher', function () {
        $teacher1 = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'short' => 'KRO',
            'email' => 'max@example.com',
        ]);

        User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'short' => 'MUS',
            'email' => 'existing@example.com',
        ]);

        $data = [
            'id' => $teacher1->id,
            'short' => 'MUS', // Already taken
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'max@example.com',
        ];

        $this->service->update($this->school->id, $data);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Das Kurzzeichen des Lehrers existiert bereits.');

    it('throws 409 when new email is already taken by another teacher', function () {
        $teacher1 = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'short' => 'KRO',
            'email' => 'max@example.com',
        ]);

        User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'short' => 'MUS',
            'email' => 'existing@example.com',
        ]);

        $data = [
            'id' => $teacher1->id,
            'short' => 'KRO',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'existing@example.com', // Already taken
        ];

        $this->service->update($this->school->id, $data);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Die E-Mail des Lehrers existiert bereits.');

    it('allows updating to same short', function () {
        $teacher = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'short' => 'KRO',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'max@example.com',
        ]);

        $data = [
            'id' => $teacher->id,
            'short' => 'KRO', // Same as before
            'first_name' => 'Maria',
            'last_name' => 'Mueller',
            'email' => 'max@example.com',
        ];

        $updated = $this->service->update($this->school->id, $data);

        expect($updated->short)->toBe('KRO')
            ->and($updated->first_name)->toBe('Maria');
    });

    it('throws ModelNotFoundException when teacher does not exist', function () {
        $data = [
            'id' => 99999,
            'short' => 'KRO',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'max@example.com',
        ];

        $this->service->update($this->school->id, $data);
    })->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

describe('deleteTeachers', function () {
    it('deletes multiple teachers', function () {
        $teacher1 = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'short' => 'KRO',
            'email' => 'teacher1@example.com',
        ]);
        $teacher1->assignRole('teacher');

        $teacher2 = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'short' => 'MUS',
            'email' => 'teacher2@example.com',
        ]);
        $teacher2->assignRole('teacher');

        $this->service->deleteTeachers($this->school->id, [$teacher1->id, $teacher2->id]);

        expect(User::find($teacher1->id))->toBeNull()
            ->and(User::find($teacher2->id))->toBeNull();
    });

    it('does not delete teacher from different school', function () {
        $otherSchool = School::factory()->create();

        $teacher = User::factory()->create([
            'school_id' => $otherSchool->id,
            'short' => 'KRO',
            'email' => 'teacher@example.com',
        ]);
        $teacher->assignRole('teacher');

        $this->service->deleteTeachers($this->school->id, [$teacher->id]);

        // Teacher should still exist
        expect(User::find($teacher->id))->not->toBeNull();
    });

    it('does not delete teacher with dependencies', function () {
        $teacher = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'short' => 'KRO',
            'email' => 'teacher@example.com',
        ]);
        $teacher->assignRole('teacher');

        // Create a dependency (register date booking)
        $register = \App\Models\Register::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);

        $registerDate = \App\Models\RegisterDate::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'register_id' => $register->id,
        ]);

        \App\Models\RegisterDateBooking::factory()->create([
            'register_date_id' => $registerDate->id,
            'user_id' => $teacher->id,
        ]);

        $this->service->deleteTeachers($this->school->id, [$teacher->id]);

        // Teacher should still exist due to dependencies
        expect(User::find($teacher->id))->not->toBeNull();
    });

    it('does not delete teacher with multiple roles', function () {
        $teacher = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'short' => 'KRO',
            'email' => 'teacher@example.com',
        ]);

        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $teacher->assignRole(['teacher', 'admin']);

        $this->service->deleteTeachers($this->school->id, [$teacher->id]);

        // Teacher should still exist due to multiple roles
        expect(User::find($teacher->id))->not->toBeNull();
    });

    it('removes teacher role when deleting', function () {
        $teacher = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'short' => 'KRO',
            'email' => 'teacher@example.com',
        ]);
        $teacher->assignRole('teacher');

        expect($teacher->hasRole('teacher'))->toBeTrue();

        $this->service->deleteTeachers($this->school->id, [$teacher->id]);

        // Teacher and role should be gone
        expect(User::find($teacher->id))->toBeNull();
    });
});
