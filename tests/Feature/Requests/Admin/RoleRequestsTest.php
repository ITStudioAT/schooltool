<?php

/**
 * Admin Role Form Request Tests
 *
 * Tests for role management requests.
 */

use App\Http\Requests\Admin\IndexRoleRequest;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    $this->user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);

    // Create a test role
    $this->role = Role::firstOrCreate(['name' => 'test_role', 'guard_name' => 'web']);
});

function validateRoleRequest(string $requestClass, array $data): \Illuminate\Validation\Validator
{
    $request = new $requestClass();
    return Validator::make($data, $request->rules());
}

// ============================================================================
// IndexRoleRequest
// ============================================================================

describe('IndexRoleRequest', function () {
    it('authorizes all requests', function () {
        $request = new IndexRoleRequest();
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// StoreRoleRequest
// ============================================================================

describe('StoreRoleRequest', function () {
    it('authorizes all requests', function () {
        $request = new StoreRoleRequest();
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid data', function () {
        $validator = validateRoleRequest(StoreRoleRequest::class, [
            'name' => 'new_role',
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when name is missing', function () {
        $validator = validateRoleRequest(StoreRoleRequest::class, []);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('name'))->toBeTrue();
    });

    it('fails when name exceeds max length', function () {
        $validator = validateRoleRequest(StoreRoleRequest::class, [
            'name' => str_repeat('a', 256),
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('fails when name is not unique', function () {
        $validator = validateRoleRequest(StoreRoleRequest::class, [
            'name' => 'test_role', // Already exists
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('name'))->toBeTrue();
    });
});

// ============================================================================
// UpdateRoleRequest
// ============================================================================

describe('UpdateRoleRequest', function () {
    it('authorizes all requests', function () {
        $request = new UpdateRoleRequest();
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid name', function () {
        $validator = validateRoleRequest(UpdateRoleRequest::class, [
            'id' => $this->role->id,
            'name' => 'updated_role_name',
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when name is missing', function () {
        $validator = validateRoleRequest(UpdateRoleRequest::class, []);

        expect($validator->fails())->toBeTrue();
    });
});
