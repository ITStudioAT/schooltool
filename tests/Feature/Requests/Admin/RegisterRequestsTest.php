<?php

/**
 * Admin Register Form Request Tests
 *
 * Tests for register (event) management requests.
 */

use App\Http\Requests\Admin\RegisterIndexRequest;
use App\Http\Requests\Admin\RegisterStoreRequest;
use App\Http\Requests\Admin\RegisterUpdateRequest;
use App\Http\Requests\Admin\RegisterToggleRequest;
use App\Http\Requests\Admin\RegisterPrintRequest;
use App\Http\Requests\Admin\SetActiveRegisterRequest;
use App\Http\Requests\Admin\RegisterUserIndexRequest;
use App\Http\Requests\Admin\RegisterUserDeleteRegisterUsersRequest;
use App\Models\Register;
use App\Models\School;
use App\Models\Schoolyear;
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
    $this->register = Register::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
});

function validateRegisterRequest(string $requestClass, array $data): \Illuminate\Validation\Validator
{
    $request = new $requestClass();
    return Validator::make($data, $request->rules());
}

// ============================================================================
// RegisterIndexRequest
// ============================================================================

describe('RegisterIndexRequest', function () {
    it('requires authentication', function () {
        $request = new RegisterIndexRequest();
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new RegisterIndexRequest();
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// RegisterStoreRequest
// ============================================================================

describe('RegisterStoreRequest', function () {
    it('requires authentication', function () {
        $request = new RegisterStoreRequest();
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new RegisterStoreRequest();
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid data', function () {
        $validator = validateRegisterRequest(RegisterStoreRequest::class, [
            'name' => 'Elternsprechtag 2025',
            'description_on_website' => 'Beschreibung des Events',
            'max_registrations' => 3,
            'is_active' => true,
            'show_phone' => true,
            'must_phone' => false,
            'show_student_last_name' => true,
            'must_student_last_name' => true,
            'show_student_first_name' => true,
            'must_student_first_name' => false,
            'show_student_birthdate' => false,
            'must_student_birthdate' => false,
            'show_note' => true,
            'must_note' => false,
            'show_booked' => true,
            'show_end_time' => true,
            'show_supervisor' => true,
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('passes with minimal required data', function () {
        $validator = validateRegisterRequest(RegisterStoreRequest::class, [
            'name' => 'Test Register',
            'max_registrations' => 1,
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when name is missing', function () {
        $validator = validateRegisterRequest(RegisterStoreRequest::class, [
            'max_registrations' => 1,
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('name'))->toBeTrue();
    });

    it('fails when max_registrations is missing', function () {
        $validator = validateRegisterRequest(RegisterStoreRequest::class, [
            'name' => 'Test',
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('max_registrations'))->toBeTrue();
    });

    it('fails when max_registrations is negative', function () {
        $validator = validateRegisterRequest(RegisterStoreRequest::class, [
            'name' => 'Test',
            'max_registrations' => -1,
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('fails when name exceeds max length', function () {
        $validator = validateRegisterRequest(RegisterStoreRequest::class, [
            'name' => str_repeat('a', 256),
            'max_registrations' => 1,
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('fails when description exceeds max length', function () {
        $validator = validateRegisterRequest(RegisterStoreRequest::class, [
            'name' => 'Test',
            'max_registrations' => 1,
            'description_on_website' => str_repeat('a', 1025),
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// RegisterUpdateRequest
// ============================================================================

describe('RegisterUpdateRequest', function () {
    it('requires authentication', function () {
        $request = new RegisterUpdateRequest();
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new RegisterUpdateRequest();
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// RegisterToggleRequest
// ============================================================================

describe('RegisterToggleRequest', function () {
    it('requires authentication', function () {
        $request = new RegisterToggleRequest();
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new RegisterToggleRequest();
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid register_id', function () {
        $validator = validateRegisterRequest(RegisterToggleRequest::class, [
            'register_id' => $this->register->id,
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when register_id is missing', function () {
        $validator = validateRegisterRequest(RegisterToggleRequest::class, []);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('register_id'))->toBeTrue();
    });

    it('fails when register_id does not exist', function () {
        $validator = validateRegisterRequest(RegisterToggleRequest::class, [
            'register_id' => 99999,
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// RegisterPrintRequest
// ============================================================================

describe('RegisterPrintRequest', function () {
    it('requires authentication', function () {
        $request = new RegisterPrintRequest();
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new RegisterPrintRequest();
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid register_id', function () {
        $validator = validateRegisterRequest(RegisterPrintRequest::class, [
            'register_id' => $this->register->id,
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when register_id is missing', function () {
        $validator = validateRegisterRequest(RegisterPrintRequest::class, []);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('register_id'))->toBeTrue();
    });
});

// ============================================================================
// SetActiveRegisterRequest
// ============================================================================

describe('SetActiveRegisterRequest', function () {
    it('requires authentication', function () {
        $request = new SetActiveRegisterRequest();
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new SetActiveRegisterRequest();
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid register_id', function () {
        $validator = validateRegisterRequest(SetActiveRegisterRequest::class, [
            'register_id' => $this->register->id,
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when register_id is missing', function () {
        $validator = validateRegisterRequest(SetActiveRegisterRequest::class, []);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('register_id'))->toBeTrue();
    });

    it('fails when register_id does not exist', function () {
        $validator = validateRegisterRequest(SetActiveRegisterRequest::class, [
            'register_id' => 99999,
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// RegisterUserIndexRequest
// ============================================================================

describe('RegisterUserIndexRequest', function () {
    it('requires authentication', function () {
        $request = new RegisterUserIndexRequest();
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new RegisterUserIndexRequest();
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid register_id', function () {
        $validator = validateRegisterRequest(RegisterUserIndexRequest::class, [
            'register_id' => $this->register->id,
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when register_id is missing', function () {
        $validator = validateRegisterRequest(RegisterUserIndexRequest::class, []);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('register_id'))->toBeTrue();
    });
});

// ============================================================================
// RegisterUserDeleteRegisterUsersRequest
// ============================================================================

describe('RegisterUserDeleteRegisterUsersRequest', function () {
    it('requires authentication', function () {
        $request = new RegisterUserDeleteRegisterUsersRequest();
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new RegisterUserDeleteRegisterUsersRequest();
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid register_id', function () {
        $validator = validateRegisterRequest(RegisterUserDeleteRegisterUsersRequest::class, [
            'register_id' => $this->register->id,
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when register_id is missing', function () {
        $validator = validateRegisterRequest(RegisterUserDeleteRegisterUsersRequest::class, []);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('register_id'))->toBeTrue();
    });
});
