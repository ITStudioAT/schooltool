<?php

/**
 * Admin Schoolyear Form Request Tests
 *
 * Tests for schoolyear management requests.
 */

use App\Http\Requests\Admin\SchoolyearIndexRequest;
use App\Http\Requests\Admin\SchoolyearStoreRequest;
use App\Http\Requests\Admin\SchoolyearUpdateRequest;
use App\Http\Requests\Admin\SetActiveSchoolyearRequest;
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
});

function validateSchoolyearRequest(string $requestClass, array $data): Illuminate\Validation\Validator
{
    $request = new $requestClass;

    return Validator::make($data, $request->rules());
}

// ============================================================================
// SchoolyearIndexRequest
// ============================================================================

describe('SchoolyearIndexRequest', function () {
    it('requires authentication', function () {
        $request = new SchoolyearIndexRequest;
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new SchoolyearIndexRequest;
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// SchoolyearStoreRequest
// ============================================================================

describe('SchoolyearStoreRequest', function () {
    it('requires authentication', function () {
        $request = new SchoolyearStoreRequest;
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new SchoolyearStoreRequest;
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid data', function () {
        $validator = validateSchoolyearRequest(SchoolyearStoreRequest::class, [
            'name' => '2025/2026',
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when name is missing', function () {
        $validator = validateSchoolyearRequest(SchoolyearStoreRequest::class, []);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('name'))->toBeTrue();
    });

    it('fails when name exceeds max length', function () {
        $validator = validateSchoolyearRequest(SchoolyearStoreRequest::class, [
            'name' => str_repeat('a', 256),
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// SchoolyearUpdateRequest
// ============================================================================

describe('SchoolyearUpdateRequest', function () {
    it('requires authentication', function () {
        $request = new SchoolyearUpdateRequest;
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new SchoolyearUpdateRequest;
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid name', function () {
        $this->actingAs($this->user);

        $validator = validateSchoolyearRequest(SchoolyearUpdateRequest::class, [
            'id' => $this->schoolyear->id,
            'name' => '2026/2027',
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when name is missing', function () {
        $validator = validateSchoolyearRequest(SchoolyearUpdateRequest::class, [
            'id' => $this->schoolyear->id,
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// SetActiveSchoolyearRequest
// ============================================================================

describe('SetActiveSchoolyearRequest', function () {
    it('requires authentication', function () {
        $request = new SetActiveSchoolyearRequest;
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new SetActiveSchoolyearRequest;
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid schoolyear_id', function () {
        $this->actingAs($this->user);

        $validator = validateSchoolyearRequest(SetActiveSchoolyearRequest::class, [
            'schoolyear_id' => $this->schoolyear->id,
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when schoolyear_id is missing', function () {
        $validator = validateSchoolyearRequest(SetActiveSchoolyearRequest::class, []);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('schoolyear_id'))->toBeTrue();
    });

    it('fails when schoolyear_id does not exist', function () {
        $validator = validateSchoolyearRequest(SetActiveSchoolyearRequest::class, [
            'schoolyear_id' => 99999,
        ]);

        expect($validator->fails())->toBeTrue();
    });
});
