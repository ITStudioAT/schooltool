<?php

/**
 * Admin User Form Request Tests
 *
 * Tests for user management, profile, and role-related requests.
 */

use App\Http\Requests\Admin\ConfirmRequest;
use App\Http\Requests\Admin\IndexUserRequest;
use App\Http\Requests\Admin\IndexUserWithRoleRequest;
use App\Http\Requests\Admin\SaveUserRoleRequest;
use App\Http\Requests\Admin\SaveUserRolesRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateProfileRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Requests\Admin\UpdateUserWithCodeRequest;
use App\Http\Requests\Admin\UserDeleteUsersRequest;
use App\Http\Requests\Admin\UserIndexRequest;
use App\Http\Requests\Admin\UserStoreUserRequest;
use App\Http\Requests\Admin\UserUpdateUserRequest;
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

function validateUserRequest(string $requestClass, array $data): Illuminate\Validation\Validator
{
    $request = new $requestClass;

    return Validator::make($data, $request->rules());
}

// ============================================================================
// ConfirmRequest
// ============================================================================

describe('ConfirmRequest', function () {
    it('authorizes all requests', function () {
        $request = new ConfirmRequest;
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid user ids array', function () {
        $validator = validateUserRequest(ConfirmRequest::class, [
            'ids' => [$this->user->id],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('passes with multiple valid user ids', function () {
        $user2 = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);

        $validator = validateUserRequest(ConfirmRequest::class, [
            'ids' => [$this->user->id, $user2->id],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when ids is missing', function () {
        $validator = validateUserRequest(ConfirmRequest::class, []);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('ids'))->toBeTrue();
    });

    it('fails when ids is not an array', function () {
        $validator = validateUserRequest(ConfirmRequest::class, [
            'ids' => $this->user->id,
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('fails when ids contains non-existent user', function () {
        $validator = validateUserRequest(ConfirmRequest::class, [
            'ids' => [99999],
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('fails when ids contains non-integer values', function () {
        $validator = validateUserRequest(ConfirmRequest::class, [
            'ids' => ['not-an-id'],
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// IndexUserRequest
// ============================================================================

describe('IndexUserRequest', function () {
    it('authorizes all requests', function () {
        $request = new IndexUserRequest;
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid search model', function () {
        $validator = validateUserRequest(IndexUserRequest::class, [
            'search_model' => [
                'is_active' => '1',
                'is_confirmed' => '0',
                'is_verified' => '2',
                'is_2fa' => '1',
                'search_string' => 'test',
            ],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('passes with empty data', function () {
        $validator = validateUserRequest(IndexUserRequest::class, []);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when is_active has invalid value', function () {
        $validator = validateUserRequest(IndexUserRequest::class, [
            'search_model' => [
                'is_active' => '3',
            ],
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('fails when search_string exceeds max length', function () {
        $validator = validateUserRequest(IndexUserRequest::class, [
            'search_model' => [
                'search_string' => str_repeat('a', 256),
            ],
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// IndexUserWithRoleRequest
// ============================================================================

describe('IndexUserWithRoleRequest', function () {
    it('authorizes all requests', function () {
        $request = new IndexUserWithRoleRequest;
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// StoreUserRequest
// ============================================================================

describe('StoreUserRequest', function () {
    it('authorizes all requests', function () {
        $request = new StoreUserRequest;
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid data', function () {
        $validator = validateUserRequest(StoreUserRequest::class, [
            'last_name' => 'Mustermann',
            'first_name' => 'Max',
            'email' => 'new@example.com',
            'is_active' => true,
            'is_confirmed' => false,
            'is_verified' => false,
            'is_2fa' => false,
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('passes with minimal required data', function () {
        $validator = validateUserRequest(StoreUserRequest::class, [
            'last_name' => 'Mustermann',
            'email' => 'minimal@example.com',
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when last_name is missing', function () {
        $validator = validateUserRequest(StoreUserRequest::class, [
            'email' => 'test@example.com',
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('last_name'))->toBeTrue();
    });

    it('fails when email is missing', function () {
        $validator = validateUserRequest(StoreUserRequest::class, [
            'last_name' => 'Mustermann',
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('email'))->toBeTrue();
    });

    it('fails when email is invalid', function () {
        $validator = validateUserRequest(StoreUserRequest::class, [
            'last_name' => 'Mustermann',
            'email' => 'not-an-email',
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('fails when last_name exceeds max length', function () {
        $validator = validateUserRequest(StoreUserRequest::class, [
            'last_name' => str_repeat('a', 256),
            'email' => 'test@example.com',
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('allows nullable first_name', function () {
        $validator = validateUserRequest(StoreUserRequest::class, [
            'last_name' => 'Mustermann',
            'first_name' => null,
            'email' => 'test@example.com',
        ]);

        expect($validator->passes())->toBeTrue();
    });
});

// ============================================================================
// UpdateUserRequest
// ============================================================================

describe('UpdateUserRequest', function () {
    it('authorizes all requests', function () {
        $request = new UpdateUserRequest;
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// UpdateProfileRequest
// ============================================================================

describe('UpdateProfileRequest', function () {
    it('authorizes all requests', function () {
        $request = new UpdateProfileRequest;
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// UpdateUserWithCodeRequest
// ============================================================================

describe('UpdateUserWithCodeRequest', function () {
    it('authorizes all requests', function () {
        $request = new UpdateUserWithCodeRequest;
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// UserIndexRequest
// ============================================================================

describe('UserIndexRequest', function () {
    it('requires authentication', function () {
        $request = new UserIndexRequest;
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new UserIndexRequest;
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// UserStoreUserRequest
// ============================================================================

describe('UserStoreUserRequest', function () {
    it('requires authentication', function () {
        $request = new UserStoreUserRequest;
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new UserStoreUserRequest;
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// UserUpdateUserRequest
// ============================================================================

describe('UserUpdateUserRequest', function () {
    it('requires authentication', function () {
        $request = new UserUpdateUserRequest;
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new UserUpdateUserRequest;
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// UserDeleteUsersRequest
// ============================================================================

describe('UserDeleteUsersRequest', function () {
    it('requires authentication', function () {
        $request = new UserDeleteUsersRequest;
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new UserDeleteUsersRequest;
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// SaveUserRoleRequest
// ============================================================================

describe('SaveUserRoleRequest', function () {
    it('authorizes all requests', function () {
        $request = new SaveUserRoleRequest;
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// SaveUserRolesRequest
// ============================================================================

describe('SaveUserRolesRequest', function () {
    it('authorizes all requests', function () {
        $request = new SaveUserRolesRequest;
        expect($request->authorize())->toBeTrue();
    });
});
