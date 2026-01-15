<?php

/**
 * Admin Licence Form Request Tests
 *
 * Tests for licence management requests.
 */

use App\Http\Requests\Admin\LicenceIndexRequest;
use App\Http\Requests\Admin\LicenceStoreRequest;
use App\Http\Requests\Admin\LicenceUpdateRequest;
use App\Http\Requests\Admin\LicenceDeleteLicencesRequest;
use App\Models\Licence;
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
    // Licence model doesn't have a factory
    $this->licence = Licence::create([
        'name' => 'test_licence',
        'description' => 'Test Licence Description',
    ]);
});

function validateLicenceRequest(string $requestClass, array $data): \Illuminate\Validation\Validator
{
    $request = new $requestClass();
    return Validator::make($data, $request->rules());
}

// ============================================================================
// LicenceIndexRequest
// ============================================================================

describe('LicenceIndexRequest', function () {
    it('requires authentication', function () {
        $request = new LicenceIndexRequest();
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new LicenceIndexRequest();
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// LicenceStoreRequest
// ============================================================================

describe('LicenceStoreRequest', function () {
    it('requires authentication', function () {
        $request = new LicenceStoreRequest();
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new LicenceStoreRequest();
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid data', function () {
        $validator = validateLicenceRequest(LicenceStoreRequest::class, [
            'name' => 'premium_licence',
            'long_name' => 'Premium School Licence',
            'is_selectable' => true,
            'price_per_year' => 1200,
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('passes with minimal required data', function () {
        $validator = validateLicenceRequest(LicenceStoreRequest::class, [
            'name' => 'basic_licence',
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when name is missing', function () {
        $validator = validateLicenceRequest(LicenceStoreRequest::class, [
            'long_name' => 'Long Name',
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('name'))->toBeTrue();
    });

    it('fails when name exceeds max length', function () {
        $validator = validateLicenceRequest(LicenceStoreRequest::class, [
            'name' => str_repeat('a', 256),
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('allows nullable long_name', function () {
        $validator = validateLicenceRequest(LicenceStoreRequest::class, [
            'name' => 'new_licence',
            'long_name' => null,
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('allows nullable price_per_year', function () {
        $validator = validateLicenceRequest(LicenceStoreRequest::class, [
            'name' => 'free_licence',
            'price_per_year' => null,
        ]);

        expect($validator->passes())->toBeTrue();
    });
});

// ============================================================================
// LicenceUpdateRequest
// ============================================================================

describe('LicenceUpdateRequest', function () {
    it('requires authentication', function () {
        $request = new LicenceUpdateRequest();
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new LicenceUpdateRequest();
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// LicenceDeleteLicencesRequest
// ============================================================================

describe('LicenceDeleteLicencesRequest', function () {
    it('requires authentication', function () {
        $request = new LicenceDeleteLicencesRequest();
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new LicenceDeleteLicencesRequest();
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid licence ids as root array', function () {
        // LicenceDeleteLicencesRequest validates root array elements with '*'
        $validator = validateLicenceRequest(LicenceDeleteLicencesRequest::class, [
            $this->licence->id,
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when ids contains non-existent licence', function () {
        $validator = validateLicenceRequest(LicenceDeleteLicencesRequest::class, [
            99999,
        ]);

        expect($validator->fails())->toBeTrue();
    });
});
