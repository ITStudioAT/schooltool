<?php

/**
 * Admin Teacher Form Request Tests
 *
 * Tests for teacher management requests.
 */

use App\Http\Requests\Admin\AdminNewTeacherStepCodeRequest;
use App\Http\Requests\Admin\AdminNewTeacherStepEmailRequest;
use App\Http\Requests\Admin\AdminNewTeacherStepSchoolRequest;
use App\Http\Requests\Admin\TeacherDeleteTeachersRequest;
use App\Http\Requests\Admin\TeacherIndexRequest;
use App\Http\Requests\Admin\TeacherListStoreRequest;
use App\Http\Requests\Admin\TeacherListUpdateRequest;
use App\Http\Requests\Admin\TeachersListDeleteTeachers;
use App\Http\Requests\Admin\TeacherStoreRequest;
use App\Http\Requests\Admin\TeacherUpdateRequest;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    $this->user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
});

function validateTeacherRequest(string $requestClass, array $data): Illuminate\Validation\Validator
{
    $request = new $requestClass;

    return Validator::make($data, $request->rules());
}

// ============================================================================
// TeacherIndexRequest
// ============================================================================

describe('TeacherIndexRequest', function () {
    it('requires authentication', function () {
        $request = new TeacherIndexRequest;
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new TeacherIndexRequest;
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// TeacherStoreRequest
// ============================================================================

describe('TeacherStoreRequest', function () {
    it('requires authentication', function () {
        $request = new TeacherStoreRequest;
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new TeacherStoreRequest;
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid data', function () {
        $validator = validateTeacherRequest(TeacherStoreRequest::class, [
            'last_name' => 'Mueller',
            'first_name' => 'Hans',
            'short' => 'MUE',
            'email' => 'mueller@school.com',
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('passes with minimal required data', function () {
        $validator = validateTeacherRequest(TeacherStoreRequest::class, [
            'last_name' => 'Smith',
            'email' => 'smith@school.com',
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when last_name is missing', function () {
        $validator = validateTeacherRequest(TeacherStoreRequest::class, [
            'email' => 'teacher@school.com',
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('last_name'))->toBeTrue();
    });

    it('fails when email is missing', function () {
        $validator = validateTeacherRequest(TeacherStoreRequest::class, [
            'last_name' => 'Doe',
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('email'))->toBeTrue();
    });
});
// ============================================================================
// TeacherUpdateRequest
// ============================================================================

describe('TeacherUpdateRequest', function () {
    it('requires authentication', function () {
        $request = new TeacherUpdateRequest;
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new TeacherUpdateRequest;
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// TeacherDeleteTeachersRequest
// ============================================================================

describe('TeacherDeleteTeachersRequest', function () {
    it('requires authentication', function () {
        $request = new TeacherDeleteTeachersRequest;
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new TeacherDeleteTeachersRequest;
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid user ids in data array', function () {
        // TeacherDeleteTeachersRequest validates 'data.*' for user IDs
        $validator = validateTeacherRequest(TeacherDeleteTeachersRequest::class, [
            'data' => [$this->user->id],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when data contains non-existent user', function () {
        $validator = validateTeacherRequest(TeacherDeleteTeachersRequest::class, [
            'data' => [99999],
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// TeacherListStoreRequest
// ============================================================================

describe('TeacherListStoreRequest', function () {
    it('requires authentication', function () {
        $request = new TeacherListStoreRequest;
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new TeacherListStoreRequest;
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid data', function () {
        $validator = validateTeacherRequest(TeacherListStoreRequest::class, [
            'last_name' => 'Mueller',
            'email' => 'mueller@school.com',
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when last_name is missing', function () {
        $validator = validateTeacherRequest(TeacherListStoreRequest::class, []);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('last_name'))->toBeTrue();
    });

    it('fails when last_name exceeds max length', function () {
        $validator = validateTeacherRequest(TeacherListStoreRequest::class, [
            'last_name' => str_repeat('a', 256),
            'email' => 'mueller@school.com',
        ]);

        expect($validator->fails())->toBeTrue();
    });
});
// ============================================================================
// TeacherListUpdateRequest
// ============================================================================

describe('TeacherListUpdateRequest', function () {
    it('requires authentication', function () {
        $request = new TeacherListUpdateRequest;
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new TeacherListUpdateRequest;
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid data', function () {
        $teacher = Teacher::create([
            'school_id' => $this->school->id,
            'email' => 'teacher@school.com',
            'first_name' => 'Anna',
            'last_name' => 'Teach',
            'short' => 'T',
        ]);

        $validator = validateTeacherRequest(TeacherListUpdateRequest::class, [
            'id' => $teacher->id,
            'last_name' => 'Teacher',
            'email' => 'updated@school.com',
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when last_name is missing', function () {
        $teacher = Teacher::create([
            'school_id' => $this->school->id,
            'email' => 'teacher2@school.com',
            'first_name' => 'Ben',
            'last_name' => 'Teach',
            'short' => 'T2',
        ]);

        $validator = validateTeacherRequest(TeacherListUpdateRequest::class, [
            'id' => $teacher->id,
            'email' => 'updated2@school.com',
        ]);

        expect($validator->fails())->toBeTrue();
    });
});
// ============================================================================
// TeachersListDeleteTeachers
// ============================================================================

describe('TeachersListDeleteTeachers', function () {
    it('requires authentication', function () {
        $request = new TeachersListDeleteTeachers;
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new TeachersListDeleteTeachers;
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// AdminNewTeacherStepCodeRequest
// ============================================================================

describe('AdminNewTeacherStepCodeRequest', function () {
    it('authorizes all requests', function () {
        $request = new AdminNewTeacherStepCodeRequest;
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// AdminNewTeacherStepEmailRequest
// ============================================================================

describe('AdminNewTeacherStepEmailRequest', function () {
    it('authorizes all requests', function () {
        $request = new AdminNewTeacherStepEmailRequest;
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// AdminNewTeacherStepSchoolRequest
// ============================================================================

describe('AdminNewTeacherStepSchoolRequest', function () {
    it('authorizes all requests', function () {
        $request = new AdminNewTeacherStepSchoolRequest;
        expect($request->authorize())->toBeTrue();
    });
});
