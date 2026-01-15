<?php

/**
 * Admin School Form Request Tests
 *
 * Tests for school management requests.
 */

use App\Http\Requests\Admin\SchoolIndexRequest;
use App\Http\Requests\Admin\SchoolStoreRequest;
use App\Http\Requests\Admin\SchoolUpdateRequest;
use App\Http\Requests\Admin\SchoolSwitchSchoolRequest;
use App\Http\Requests\Admin\SchoolLoadSchoolLicencesRequest;
use App\Http\Requests\Admin\SchoolAddLicenceRequest;
use App\Http\Requests\Admin\SchoolDeleteLicenceRequest;
use App\Http\Requests\Admin\SchoolAddAdminRequest;
use App\Http\Requests\Admin\SchoolDeleteAdminRequest;
use App\Http\Requests\Admin\SchoolDeleteSchoolsRequest;
use App\Models\School;
use App\Models\User;
use App\Models\Schoolyear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->school = School::factory()->create([
        'long_name' => 'Test School',
        'short_name' => 'TS',
        'email' => 'school@example.com',
    ]);
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    $this->user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
});

function validateSchoolRequest(string $requestClass, array $data): \Illuminate\Validation\Validator
{
    $request = new $requestClass();
    return Validator::make($data, $request->rules());
}

// ============================================================================
// SchoolIndexRequest
// ============================================================================

describe('SchoolIndexRequest', function () {
    it('requires authentication', function () {
        $request = new SchoolIndexRequest();
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new SchoolIndexRequest();
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// SchoolStoreRequest
// ============================================================================

describe('SchoolStoreRequest', function () {
    it('requires authentication', function () {
        $request = new SchoolStoreRequest();
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new SchoolStoreRequest();
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid data', function () {
        $validator = validateSchoolRequest(SchoolStoreRequest::class, [
            'long_name' => 'New School Name',
            'short_name' => 'NSN',
            'email' => 'newschool@example.com',
            'upload_file' => null,
            'is_selectable' => true,
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when long_name is missing', function () {
        $validator = validateSchoolRequest(SchoolStoreRequest::class, [
            'short_name' => 'NSN',
            'email' => 'newschool@example.com',
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('long_name'))->toBeTrue();
    });

    it('fails when short_name is missing', function () {
        $validator = validateSchoolRequest(SchoolStoreRequest::class, [
            'long_name' => 'New School',
            'email' => 'newschool@example.com',
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('short_name'))->toBeTrue();
    });

    it('fails when email is missing', function () {
        $validator = validateSchoolRequest(SchoolStoreRequest::class, [
            'long_name' => 'New School',
            'short_name' => 'NSN',
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('email'))->toBeTrue();
    });

    it('fails when email is invalid', function () {
        $validator = validateSchoolRequest(SchoolStoreRequest::class, [
            'long_name' => 'New School',
            'short_name' => 'NSN',
            'email' => 'not-an-email',
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('fails when long_name exceeds max length', function () {
        $validator = validateSchoolRequest(SchoolStoreRequest::class, [
            'long_name' => str_repeat('a', 256),
            'short_name' => 'NSN',
            'email' => 'new@example.com',
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('fails when long_name is not unique', function () {
        $validator = validateSchoolRequest(SchoolStoreRequest::class, [
            'long_name' => 'Test School', // Already exists
            'short_name' => 'NSN',
            'email' => 'new@example.com',
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('long_name'))->toBeTrue();
    });

    it('fails when email is not unique', function () {
        $validator = validateSchoolRequest(SchoolStoreRequest::class, [
            'long_name' => 'New School',
            'short_name' => 'NSN',
            'email' => 'school@example.com', // Already exists
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('email'))->toBeTrue();
    });
});

// ============================================================================
// SchoolUpdateRequest
// ============================================================================

describe('SchoolUpdateRequest', function () {
    it('requires authentication', function () {
        $request = new SchoolUpdateRequest();
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new SchoolUpdateRequest();
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// SchoolSwitchSchoolRequest
// ============================================================================

describe('SchoolSwitchSchoolRequest', function () {
    it('requires authentication', function () {
        $request = new SchoolSwitchSchoolRequest();
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new SchoolSwitchSchoolRequest();
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid school_id', function () {
        $validator = validateSchoolRequest(SchoolSwitchSchoolRequest::class, [
            'school_id' => $this->school->id,
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when school_id is missing', function () {
        $validator = validateSchoolRequest(SchoolSwitchSchoolRequest::class, []);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('school_id'))->toBeTrue();
    });

    it('fails when school_id does not exist', function () {
        $validator = validateSchoolRequest(SchoolSwitchSchoolRequest::class, [
            'school_id' => 99999,
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// SchoolLoadSchoolLicencesRequest
// ============================================================================

describe('SchoolLoadSchoolLicencesRequest', function () {
    it('requires authentication', function () {
        $request = new SchoolLoadSchoolLicencesRequest();
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new SchoolLoadSchoolLicencesRequest();
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid school_id', function () {
        $validator = validateSchoolRequest(SchoolLoadSchoolLicencesRequest::class, [
            'school_id' => $this->school->id,
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when school_id is missing', function () {
        $validator = validateSchoolRequest(SchoolLoadSchoolLicencesRequest::class, []);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('school_id'))->toBeTrue();
    });
});

// ============================================================================
// SchoolAddLicenceRequest
// ============================================================================

describe('SchoolAddLicenceRequest', function () {
    it('requires authentication', function () {
        $request = new SchoolAddLicenceRequest();
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new SchoolAddLicenceRequest();
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// SchoolDeleteLicenceRequest
// ============================================================================

describe('SchoolDeleteLicenceRequest', function () {
    it('requires authentication', function () {
        $request = new SchoolDeleteLicenceRequest();
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new SchoolDeleteLicenceRequest();
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// SchoolAddAdminRequest
// ============================================================================

describe('SchoolAddAdminRequest', function () {
    it('requires authentication', function () {
        $request = new SchoolAddAdminRequest();
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new SchoolAddAdminRequest();
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// SchoolDeleteAdminRequest
// ============================================================================

describe('SchoolDeleteAdminRequest', function () {
    it('requires authentication', function () {
        $request = new SchoolDeleteAdminRequest();
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new SchoolDeleteAdminRequest();
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// SchoolDeleteSchoolsRequest
// ============================================================================

describe('SchoolDeleteSchoolsRequest', function () {
    it('requires authentication', function () {
        $request = new SchoolDeleteSchoolsRequest();
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new SchoolDeleteSchoolsRequest();
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid school ids as root array', function () {
        // SchoolDeleteSchoolsRequest validates root array elements with '*'
        $validator = validateSchoolRequest(SchoolDeleteSchoolsRequest::class, [
            $this->school->id,
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when root array contains non-existent school', function () {
        $validator = validateSchoolRequest(SchoolDeleteSchoolsRequest::class, [
            99999,
        ]);

        expect($validator->fails())->toBeTrue();
    });
});
