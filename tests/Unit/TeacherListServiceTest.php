<?php

use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\Teacher;
use App\Models\User;
use App\Services\TeacherListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new TeacherListService();

    // Fake notifications
    Notification::fake();

    // Create test school
    $this->school = School::factory()->create([
        'short_name' => 'TestSchool',
        'long_name' => 'Test School Name',
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

        expect($teacher)->toBeInstanceOf(Teacher::class)
            ->and($teacher->school_id)->toBe($this->school->id)
            ->and($teacher->short)->toBe('KRO')
            ->and($teacher->first_name)->toBe('Max')
            ->and($teacher->last_name)->toBe('Mustermann')
            ->and($teacher->email)->toBe('max@example.com')
            ->and($teacher->exists)->toBeTrue();
    });

    it('aborts when short is already used in same school', function () {
        Teacher::create([
            'school_id' => $this->school->id,
            'short' => 'KRO',
            'last_name' => 'Existing',
            'email' => 'existing@example.com',
        ]);

        $data = [
            'short' => 'KRO',
            'first_name' => 'New',
            'last_name' => 'Teacher',
            'email' => 'new@example.com',
        ];

        $this->service->create($this->school->id, $data);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Das Kurzzeichen wird bereits verwendet');

    it('aborts when email is already used in same school', function () {
        Teacher::create([
            'school_id' => $this->school->id,
            'short' => 'ABC',
            'last_name' => 'Existing',
            'email' => 'test@example.com',
        ]);

        $data = [
            'short' => 'DEF',
            'first_name' => 'New',
            'last_name' => 'Teacher',
            'email' => 'test@example.com',
        ];

        $this->service->create($this->school->id, $data);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Die E-Mail-Adresse wird bereits verwendet');

    it('allows same short in different schools', function () {
        $school2 = School::factory()->create();

        Teacher::create([
            'school_id' => $this->school->id,
            'short' => 'KRO',
            'last_name' => 'Teacher1',
            'email' => 'teacher1@example.com',
        ]);

        $data = [
            'short' => 'KRO',
            'first_name' => 'Teacher2',
            'last_name' => 'Teacher2',
            'email' => 'teacher2@example.com',
        ];

        $teacher = $this->service->create($school2->id, $data);

        expect($teacher->school_id)->toBe($school2->id)
            ->and($teacher->short)->toBe('KRO');
    });

    it('allows same email in different schools', function () {
        $school2 = School::factory()->create();

        Teacher::create([
            'school_id' => $this->school->id,
            'short' => 'ABC',
            'last_name' => 'Teacher1',
            'email' => 'same@example.com',
        ]);

        $data = [
            'short' => 'DEF',
            'first_name' => 'Teacher2',
            'last_name' => 'Teacher2',
            'email' => 'same@example.com',
        ];

        $teacher = $this->service->create($school2->id, $data);

        expect($teacher->school_id)->toBe($school2->id)
            ->and($teacher->email)->toBe('same@example.com');
    });

    it('sets school_id from parameter not from data', function () {
        $data = [
            'short' => 'KRO',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'max@example.com',
            'school_id' => 9999, // This should be ignored
        ];

        $teacher = $this->service->create($this->school->id, $data);

        expect($teacher->school_id)->toBe($this->school->id)
            ->and($teacher->school_id)->not->toBe(9999);
    });
});

describe('update', function () {
    it('updates teacher data successfully', function () {
        $teacher = Teacher::create([
            'school_id' => $this->school->id,
            'short' => 'OLD',
            'first_name' => 'Old',
            'last_name' => 'Name',
            'email' => 'old@example.com',
        ]);

        $data = [
            'id' => $teacher->id,
            'short' => 'NEW',
            'first_name' => 'New',
            'last_name' => 'Name',
            'email' => 'new@example.com',
        ];

        $updated = $this->service->update($this->school->id, $data);

        expect($updated->short)->toBe('NEW')
            ->and($updated->first_name)->toBe('New')
            ->and($updated->last_name)->toBe('Name')
            ->and($updated->email)->toBe('new@example.com');
    });

    it('throws exception when teacher does not exist', function () {
        $data = [
            'id' => 99999,
            'short' => 'NEW',
            'first_name' => 'New',
            'last_name' => 'Name',
            'email' => 'new@example.com',
        ];

        $this->service->update($this->school->id, $data);
    })->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    it('aborts when school_id does not match', function () {
        $otherSchool = School::factory()->create();

        $teacher = Teacher::create([
            'school_id' => $otherSchool->id,
            'short' => 'ABC',
            'last_name' => 'Teacher',
            'email' => 'teacher@example.com',
        ]);

        $data = [
            'id' => $teacher->id,
            'short' => 'DEF',
            'first_name' => 'Test',
            'last_name' => 'Teacher',
            'email' => 'teacher@example.com',
        ];

        $this->service->update($this->school->id, $data);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Diese Änderung kann nicht durchgeführt werden');

    it('aborts when updating short to existing one in same school', function () {
        $teacher1 = Teacher::create([
            'school_id' => $this->school->id,
            'short' => 'ABC',
            'last_name' => 'Teacher1',
            'email' => 'teacher1@example.com',
        ]);

        $teacher2 = Teacher::create([
            'school_id' => $this->school->id,
            'short' => 'DEF',
            'last_name' => 'Teacher2',
            'email' => 'teacher2@example.com',
        ]);

        $data = [
            'id' => $teacher2->id,
            'short' => 'ABC', // Already used by teacher1
            'first_name' => 'Test',
            'last_name' => 'Teacher2',
            'email' => 'teacher2@example.com',
        ];

        $this->service->update($this->school->id, $data);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Das Kurzzeichen des Lehrers existiert bereits');

    it('aborts when updating email to existing one in same school', function () {
        $teacher1 = Teacher::create([
            'school_id' => $this->school->id,
            'short' => 'ABC',
            'last_name' => 'Teacher1',
            'email' => 'existing@example.com',
        ]);

        $teacher2 = Teacher::create([
            'school_id' => $this->school->id,
            'short' => 'DEF',
            'last_name' => 'Teacher2',
            'email' => 'teacher2@example.com',
        ]);

        $data = [
            'id' => $teacher2->id,
            'short' => 'DEF',
            'first_name' => 'Test',
            'last_name' => 'Teacher2',
            'email' => 'existing@example.com', // Already used by teacher1
        ];

        $this->service->update($this->school->id, $data);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Die E-Mail des Lehrers existiert bereits');

    it('allows updating to same short when not changed', function () {
        $teacher = Teacher::create([
            'school_id' => $this->school->id,
            'short' => 'ABC',
            'last_name' => 'Teacher',
            'email' => 'teacher@example.com',
        ]);

        $data = [
            'id' => $teacher->id,
            'short' => 'ABC', // Same as before
            'first_name' => 'New',
            'last_name' => 'Name',
            'email' => 'teacher@example.com',
        ];

        $updated = $this->service->update($this->school->id, $data);

        expect($updated->short)->toBe('ABC')
            ->and($updated->first_name)->toBe('New');
    });

    it('allows updating to same email when not changed', function () {
        $teacher = Teacher::create([
            'school_id' => $this->school->id,
            'short' => 'ABC',
            'last_name' => 'Teacher',
            'email' => 'teacher@example.com',
        ]);

        $data = [
            'id' => $teacher->id,
            'short' => 'DEF',
            'first_name' => 'New',
            'last_name' => 'Name',
            'email' => 'teacher@example.com', // Same as before
        ];

        $updated = $this->service->update($this->school->id, $data);

        expect($updated->email)->toBe('teacher@example.com')
            ->and($updated->short)->toBe('DEF');
    });
});

describe('deleteTeachers', function () {
    it('deletes multiple teachers by ids', function () {
        $teacher1 = Teacher::create([
            'school_id' => $this->school->id,
            'short' => 'T1',
            'last_name' => 'Teacher1',
            'email' => 'teacher1@example.com',
        ]);

        $teacher2 = Teacher::create([
            'school_id' => $this->school->id,
            'short' => 'T2',
            'last_name' => 'Teacher2',
            'email' => 'teacher2@example.com',
        ]);

        $teacher3 = Teacher::create([
            'school_id' => $this->school->id,
            'short' => 'T3',
            'last_name' => 'Teacher3',
            'email' => 'teacher3@example.com',
        ]);

        $this->service->deleteTeachers($this->school->id, [$teacher1->id, $teacher2->id]);

        expect(Teacher::find($teacher1->id))->toBeNull()
            ->and(Teacher::find($teacher2->id))->toBeNull()
            ->and(Teacher::find($teacher3->id))->not->toBeNull();
    });

    it('does not delete teacher from different school', function () {
        $otherSchool = School::factory()->create();

        $teacher1 = Teacher::create([
            'school_id' => $this->school->id,
            'short' => 'T1',
            'last_name' => 'Teacher1',
            'email' => 'teacher1@example.com',
        ]);

        $teacher2 = Teacher::create([
            'school_id' => $otherSchool->id,
            'short' => 'T2',
            'last_name' => 'Teacher2',
            'email' => 'teacher2@example.com',
        ]);

        // Try to delete teacher2 using this->school->id
        $this->service->deleteTeachers($this->school->id, [$teacher2->id]);

        // teacher2 should still exist because school_id doesn't match
        expect(Teacher::find($teacher2->id))->not->toBeNull();
    });

    it('handles empty array', function () {
        $teacher = Teacher::create([
            'school_id' => $this->school->id,
            'short' => 'T1',
            'last_name' => 'Teacher1',
            'email' => 'teacher1@example.com',
        ]);

        $this->service->deleteTeachers($this->school->id, []);

        expect(Teacher::find($teacher->id))->not->toBeNull();
    });
});

describe('getAllTeachersNotInUsers', function () {
    it('returns step NEW_TEACHER_NO_TEACHER when no teachers exist', function () {
        $result = $this->service->getAllTeachersNotInUsers('noone@example.com');

        expect($result['step'])->toBe('NEW_TEACHER_NO_TEACHER')
            ->and($result['email'])->toBe('noone@example.com')
            ->and($result['school'])->toBeNull()
            ->and($result['school_id'])->toBeNull()
            ->and($result['schools'])->toBeNull();
    });

    it('returns step NEW_TEACHER_INPUT_CODE when one teacher exists', function () {
        Teacher::create([
            'school_id' => $this->school->id,
            'short' => 'T1',
            'last_name' => 'Teacher',
            'email' => 'teacher@example.com',
        ]);

        $result = $this->service->getAllTeachersNotInUsers('teacher@example.com');

        expect($result['step'])->toBe('NEW_TEACHER_INPUT_CODE')
            ->and($result['email'])->toBe('teacher@example.com')
            ->and($result['school'])->not->toBeNull()
            ->and($result['school']->id)->toBe($this->school->id)
            ->and($result['school_id'])->toBe($this->school->id)
            ->and($result['schools'])->toBeNull();
    });

    it('returns step NEW_TEACHER_SELECT_SCHOOL when multiple teachers in different schools exist', function () {
        $school2 = School::factory()->create();

        Teacher::create([
            'school_id' => $this->school->id,
            'short' => 'T1',
            'last_name' => 'Teacher1',
            'email' => 'teacher@example.com',
        ]);

        Teacher::create([
            'school_id' => $school2->id,
            'short' => 'T2',
            'last_name' => 'Teacher2',
            'email' => 'teacher@example.com',
        ]);

        $result = $this->service->getAllTeachersNotInUsers('teacher@example.com');

        expect($result['step'])->toBe('NEW_TEACHER_SELECT_SCHOOL')
            ->and($result['email'])->toBe('teacher@example.com')
            ->and($result['school'])->toBeNull()
            ->and($result['school_id'])->toBeNull()
            ->and($result['schools'])->not->toBeNull()
            ->and($result['schools'])->toHaveCount(2);
    });

    it('filters out teachers that already have user accounts', function () {
        $school2 = School::factory()->create();

        Teacher::create([
            'school_id' => $this->school->id,
            'short' => 'T1',
            'last_name' => 'Teacher1',
            'email' => 'teacher@example.com',
        ]);

        Teacher::create([
            'school_id' => $school2->id,
            'short' => 'T2',
            'last_name' => 'Teacher2',
            'email' => 'teacher@example.com',
        ]);

        // Create user for first teacher
        User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'teacher@example.com',
        ]);

        $result = $this->service->getAllTeachersNotInUsers('teacher@example.com');

        // Should only find teacher from school2 since school1 teacher has a user
        expect($result['step'])->toBe('NEW_TEACHER_INPUT_CODE')
            ->and($result['school']->id)->toBe($school2->id);
    });

    it('returns NO_TEACHER when all teachers have user accounts', function () {
        Teacher::create([
            'school_id' => $this->school->id,
            'short' => 'T1',
            'last_name' => 'Teacher',
            'email' => 'teacher@example.com',
        ]);

        User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'teacher@example.com',
        ]);

        $result = $this->service->getAllTeachersNotInUsers('teacher@example.com');

        expect($result['step'])->toBe('NEW_TEACHER_NO_TEACHER');
    });
});

describe('sendCode', function () {
    it('sends code to teacher email', function () {
        $teacher = Teacher::create([
            'school_id' => $this->school->id,
            'short' => 'T1',
            'last_name' => 'Teacher',
            'email' => 'teacher@example.com',
        ]);

        $this->service->sendCode($this->school->id, 'teacher@example.com');

        $teacher->refresh();

        expect($teacher->token)->not->toBeNull()
            ->and($teacher->token)->toHaveLength(6)
            ->and($teacher->token_expires_at)->not->toBeNull();

        Notification::assertSentTo(
            [Notification::route('mail', 'teacher@example.com')],
            \App\Notifications\StandardEmail::class
        );
    });

    it('generates 6-digit token', function () {
        $teacher = Teacher::create([
            'school_id' => $this->school->id,
            'short' => 'T1',
            'last_name' => 'Teacher',
            'email' => 'teacher@example.com',
        ]);

        $this->service->sendCode($this->school->id, 'teacher@example.com');

        $teacher->refresh();

        expect($teacher->token)->toMatch('/^\d{6}$/');
    });

    it('sets token expiration time', function () {
        config(['schooltool.token_expire_time' => 120]);

        $teacher = Teacher::create([
            'school_id' => $this->school->id,
            'short' => 'T1',
            'last_name' => 'Teacher',
            'email' => 'teacher@example.com',
        ]);

        $beforeSend = now();
        $this->service->sendCode($this->school->id, 'teacher@example.com');
        $afterSend = now();

        $teacher->refresh();

        expect($teacher->token_expires_at)->toBeGreaterThan($beforeSend->addMinutes(119))
            ->and($teacher->token_expires_at)->toBeLessThan($afterSend->addMinutes(121));
    });
});

describe('checkToken', function () {
    it('returns truthy value when token matches and is valid', function () {
        $teacher = Teacher::create([
            'school_id' => $this->school->id,
            'short' => 'T1',
            'last_name' => 'Teacher',
            'email' => 'teacher@example.com',
            'token' => '123456',
            'token_expires_at' => now()->addMinutes(10),
        ]);

        $result = $this->service->checkToken($teacher, '123456');

        // Method returns string due to type hint, but boolean value
        expect($result)->toBeTruthy();
    });

    it('returns falsy value when token does not match', function () {
        $teacher = Teacher::create([
            'school_id' => $this->school->id,
            'short' => 'T1',
            'last_name' => 'Teacher',
            'email' => 'teacher@example.com',
            'token' => '123456',
            'token_expires_at' => now()->addMinutes(10),
        ]);

        $result = $this->service->checkToken($teacher, '999999');

        // Method returns string due to type hint, but boolean value
        expect($result)->toBeFalsy();
    });

    it('returns falsy value when token is expired', function () {
        $teacher = Teacher::create([
            'school_id' => $this->school->id,
            'short' => 'T1',
            'last_name' => 'Teacher',
            'email' => 'teacher@example.com',
            'token' => '123456',
            'token_expires_at' => now()->subMinutes(10),
        ]);

        $result = $this->service->checkToken($teacher, '123456');

        // Method returns string due to type hint, but boolean value
        expect($result)->toBeFalsy();
    });
});

describe('createUserFromTeacher', function () {
    it('creates user from teacher data', function () {
        $teacher = Teacher::create([
            'school_id' => $this->school->id,
            'short' => 'KRO',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'teacher@example.com',
        ]);

        $data = [
            'school_id' => $this->school->id,
            'email' => 'teacher@example.com',
        ];

        $user = $this->service->createUserFromTeacher($data);

        expect($user)->toBeInstanceOf(User::class)
            ->and($user->school_id)->toBe($this->school->id)
            ->and($user->short)->toBe('KRO')
            ->and($user->first_name)->toBe('Max')
            ->and($user->last_name)->toBe('Mustermann')
            ->and($user->email)->toBe('teacher@example.com')
            ->and($user->email_verified_at)->not->toBeNull()
            ->and($user->confirmed_at)->not->toBeNull()
            ->and($user->login_at)->not->toBeNull()
            ->and($user->password)->not->toBeNull();
    });

    it('sets login_ip from request', function () {
        $teacher = Teacher::create([
            'school_id' => $this->school->id,
            'short' => 'KRO',
            'last_name' => 'Mustermann',
            'email' => 'teacher@example.com',
        ]);

        $data = [
            'school_id' => $this->school->id,
            'email' => 'teacher@example.com',
        ];

        request()->server->set('REMOTE_ADDR', '192.168.1.100');

        $user = $this->service->createUserFromTeacher($data);

        expect($user->login_ip)->not->toBeNull();
    });

    it('generates password for new user', function () {
        $teacher = Teacher::create([
            'school_id' => $this->school->id,
            'short' => 'KRO',
            'last_name' => 'Mustermann',
            'email' => 'teacher@example.com',
        ]);

        $data = [
            'school_id' => $this->school->id,
            'email' => 'teacher@example.com',
        ];

        $user = $this->service->createUserFromTeacher($data);

        expect($user->password)->not->toBeNull()
            ->and($user->password)->not->toBeEmpty()
            ->and(Hash::needsRehash($user->password))->toBeFalse();
    });

    it('sets schoolyear_id from school tools active_schoolyear_id', function () {
        $schoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
        ]);

        SchoolTool::factory()->create([
            'school_id' => $this->school->id,
            'active_schoolyear_id' => $schoolyear->id,
        ]);

        Teacher::create([
            'school_id' => $this->school->id,
            'short' => 'KRO',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'teacher@example.com',
        ]);

        $data = [
            'school_id' => $this->school->id,
            'email' => 'teacher@example.com',
        ];

        $user = $this->service->createUserFromTeacher($data);

        expect($user->schoolyear_id)->toBe($schoolyear->id);
    });
});
