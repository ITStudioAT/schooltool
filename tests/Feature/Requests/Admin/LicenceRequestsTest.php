<?php

/**
 * Admin Licence Form Request Tests
 *
 * Tests for licence management requests.
 */

use App\Http\Requests\Admin\LicenceDeleteLicencesRequest;
use App\Http\Requests\Admin\LicenceIndexRequest;
use App\Http\Requests\Admin\LicenceSaveModelRequest;
use App\Http\Requests\Admin\LicenceStoreRequest;
use App\Http\Requests\Admin\LicenceUpdateRequest;
use App\Models\Licence;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
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
    // Licence model doesn't have a factory
    $this->licence = Licence::create([
        'name' => 'test_licence',
        'description' => 'Test Licence Description',
    ]);
});

function validateLicenceRequest(string $requestClass, array $data): Illuminate\Validation\Validator
{
    $request = new $requestClass;
    $request->merge($data);

    if (method_exists($request, 'prepareForValidation')) {
        $prepareForValidation = new ReflectionMethod($request, 'prepareForValidation');
        $prepareForValidation->setAccessible(true);
        $prepareForValidation->invoke($request);
    }

    return Validator::make($request->all(), $request->rules());
}

// ============================================================================
// LicenceIndexRequest
// ============================================================================

describe('LicenceIndexRequest', function () {
    it('requires authentication', function () {
        $request = new LicenceIndexRequest;
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new LicenceIndexRequest;
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// LicenceStoreRequest
// ============================================================================

describe('LicenceStoreRequest', function () {
    it('requires authentication', function () {
        $request = new LicenceStoreRequest;
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new LicenceStoreRequest;
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid data', function () {
        $validator = validateLicenceRequest(LicenceStoreRequest::class, [
            'name' => 'premium_licence',
            'long_name' => 'Premium School Licence',
            'is_selectable' => true,
            'price_per_year' => 1200,
            'start_day_month' => '01.09.',
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('passes with minimal required data', function () {
        $validator = validateLicenceRequest(LicenceStoreRequest::class, [
            'name' => 'basic_licence',
            'start_day_month' => '01.09.',
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
            'start_day_month' => '01.09.',
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('allows nullable price_per_year', function () {
        $validator = validateLicenceRequest(LicenceStoreRequest::class, [
            'name' => 'free_licence',
            'price_per_year' => null,
            'start_day_month' => '01.09.',
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when price_per_year is zero', function () {
        $validator = validateLicenceRequest(LicenceStoreRequest::class, [
            'name' => 'invalid_zero_licence',
            'price_per_year' => 0,
            'start_day_month' => '01.09.',
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('price_per_year'))->toBeTrue();
    });

    it('derives end day month from the previous calendar day', function () {
        $request = new LicenceStoreRequest;
        $request->merge([
            'name' => 'derived_end_day_month_licence',
            'start_day_month' => '01.08.',
        ]);

        $prepareForValidation = new ReflectionMethod($request, 'prepareForValidation');
        $prepareForValidation->setAccessible(true);
        $prepareForValidation->invoke($request);

        expect($request->input('start_day_month'))->toBe('08-01')
            ->and($request->input('end_day_month'))->toBe('07-31');
    });
});

// ============================================================================
// LicenceUpdateRequest
// ============================================================================

describe('LicenceUpdateRequest', function () {
    it('requires authentication', function () {
        $request = new LicenceUpdateRequest;
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new LicenceUpdateRequest;
        expect($request->authorize())->toBeTrue();
    });

    it('fails when price_per_year is zero', function () {
        $validator = validateLicenceRequest(LicenceUpdateRequest::class, [
            'id' => $this->licence->id,
            'name' => 'test_licence',
            'price_per_year' => 0,
            'start_day_month' => '01.09.',
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('price_per_year'))->toBeTrue();
    });

    it('fails when start day month is missing', function () {
        $validator = validateLicenceRequest(LicenceUpdateRequest::class, [
            'id' => $this->licence->id,
            'name' => 'test_licence',
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('start_day_month'))->toBeTrue();
    });
});

// ============================================================================
// LicenceSaveModelRequest
// ============================================================================

describe('LicenceSaveModelRequest', function () {
    it('accepts positive integer string price fields', function () {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $request = new LicenceSaveModelRequest;
        $validator = Validator::make([
            'licence_model' => [
                'school_licence_enabled' => true,
                'school_price_per_year' => '199',
                'school_included_storage_gb' => '10',
                'school_extra_storage_step_gb' => '100',
                'school_extra_storage_step_price' => '5',
                'admin_licence_enabled' => true,
                'admin_price_per_year' => '59',
                'admin_role_names' => ['admin'],
                'admin_included_storage_gb' => '10',
                'admin_extra_storage_step_gb' => '100',
                'admin_extra_storage_step_price' => '5',
                'user_licence_enabled' => true,
                'user_price_per_year' => '29',
                'user_role_names' => ['teacher'],
                'user_included_storage_gb' => '10',
                'user_extra_storage_step_gb' => '100',
                'user_extra_storage_step_price' => '5',
            ],
        ], $request->rules());

        expect($validator->passes())->toBeTrue();
    });

    it('rejects decimal structured price fields', function () {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $request = new LicenceSaveModelRequest;
        $validator = Validator::make([
            'licence_model' => [
                'school_licence_enabled' => true,
                'school_price_per_year' => '199.00',
                'admin_licence_enabled' => true,
                'admin_price_per_year' => '59',
                'admin_role_names' => ['admin'],
                'user_licence_enabled' => true,
                'user_price_per_year' => '29',
                'user_role_names' => ['teacher'],
            ],
        ], $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('licence_model.school_price_per_year'))->toBeTrue();
    });

    it('rejects zero structured price fields', function () {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $request = new LicenceSaveModelRequest;
        $validator = Validator::make([
            'licence_model' => [
                'school_licence_enabled' => true,
                'school_price_per_year' => '0',
                'admin_licence_enabled' => true,
                'admin_price_per_year' => '59',
                'admin_role_names' => ['admin'],
                'user_licence_enabled' => true,
                'user_price_per_year' => '29',
                'user_role_names' => ['teacher'],
            ],
        ], $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('licence_model.school_price_per_year'))->toBeTrue();
    });

    it('rejects invalid structured storage tariff fields', function () {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $request = new LicenceSaveModelRequest;
        $validator = Validator::make([
            'licence_model' => [
                'school_licence_enabled' => true,
                'school_price_per_year' => '199',
                'school_included_storage_gb' => '0',
                'school_extra_storage_step_gb' => '100.5',
                'school_extra_storage_step_price' => '-5',
                'admin_licence_enabled' => true,
                'admin_price_per_year' => '59',
                'admin_role_names' => ['admin'],
                'admin_included_storage_gb' => '10',
                'admin_extra_storage_step_gb' => '0',
                'admin_extra_storage_step_price' => '0',
                'user_licence_enabled' => true,
                'user_price_per_year' => '29',
                'user_role_names' => ['teacher'],
                'user_included_storage_gb' => '-1',
                'user_extra_storage_step_gb' => '100',
                'user_extra_storage_step_price' => '1.5',
            ],
        ], $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('licence_model.school_included_storage_gb'))->toBeTrue()
            ->and($validator->errors()->has('licence_model.school_extra_storage_step_gb'))->toBeTrue()
            ->and($validator->errors()->has('licence_model.school_extra_storage_step_price'))->toBeTrue()
            ->and($validator->errors()->has('licence_model.admin_extra_storage_step_gb'))->toBeTrue()
            ->and($validator->errors()->has('licence_model.admin_extra_storage_step_price'))->toBeTrue()
            ->and($validator->errors()->has('licence_model.user_included_storage_gb'))->toBeTrue()
            ->and($validator->errors()->has('licence_model.user_extra_storage_step_price'))->toBeTrue();
    });

    it('allows empty role arrays for inactive admin and user licences', function () {
        $request = new LicenceSaveModelRequest;
        $validator = Validator::make([
            'licence_model' => [
                'school_licence_enabled' => true,
                'school_price_per_year' => '199',
                'admin_licence_enabled' => false,
                'admin_price_per_year' => '',
                'admin_role_names' => [],
                'user_licence_enabled' => false,
                'user_price_per_year' => '',
                'user_role_names' => [],
            ],
        ], $request->rules(), $request->messages());

        expect($validator->passes())->toBeTrue();
    });

    it('accepts wildcard user role selection for all current and future roles', function () {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $request = new LicenceSaveModelRequest;
        $validator = Validator::make([
            'licence_model' => [
                'school_licence_enabled' => false,
                'school_price_per_year' => null,
                'admin_licence_enabled' => true,
                'admin_price_per_year' => '10',
                'admin_role_names' => ['admin'],
                'user_licence_enabled' => true,
                'user_price_per_year' => '29',
                'user_role_names' => ['*'],
            ],
        ], $request->rules(), $request->messages());

        expect($validator->passes())->toBeTrue();
    });
});

// ============================================================================
// LicenceDeleteLicencesRequest
// ============================================================================

describe('LicenceDeleteLicencesRequest', function () {
    it('requires authentication', function () {
        $request = new LicenceDeleteLicencesRequest;
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new LicenceDeleteLicencesRequest;
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
