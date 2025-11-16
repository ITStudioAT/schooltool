# AdminController Tests

## Overview
Professional Pest test suite for the `AdminController` that handles authentication, registration, password reset, and configuration endpoints.

## Test Coverage

### Config Endpoint Tests (✅ All Passing - 8 tests)
- **config returns json response with app configuration**: Validates all configuration structure
- **config returns correct app information**: Checks title, company, and auth status
- **config returns user data when authenticated**: Verifies user data in config when logged in
- **config returns selected school when user has one**: Tests school data inclusion
- **config returns empty menu for unauthenticated user**: Confirms empty menu/roles for guests
- **config includes health check data**: Validates health status in config
- **config reflects authentication state correctly**: Tests auth state changes
- **authenticated user config includes navigation menu**: Verifies menu generation

### Login Tests (✅ All Passing - 5 tests)
- **login step email returns correct data for valid email**: Tests email validation and school lookup
- **login step email rejects non-existent email**: Confirms 401 for invalid emails
- **login step 2 authenticates user with correct credentials**: Tests successful login
- **login step 2 rejects incorrect password**: Validates password check returns 401
- **login step 2 handles 2fa enabled users**: Tests 2FA flow returns correct step

### Logout Tests (✅ All Passing - 3 tests)
- **execute logout logs out authenticated user**: Tests logout for authenticated users
- **execute logout works for unauthenticated user**: Confirms logout handles non-authenticated requests  
- **execute logout returns config data**: Validates config returned after logout

### Role Management Tests (✅ All Passing - 3 tests)
- **load roles returns roles for super admin**: Tests role listing for super admins
- **load roles denies access for non-super admin**: Confirms non-super admins get 403
- **load roles requires authentication**: Validates authentication requirement with 401

### Registration Tests (✅ All Passing - 3 tests)
- **register step 2 validates token correctly**: Tests email verification token
- **register step 2 rejects invalid token**: Tests incorrect token returns 401
- **register step 3 updates user with valid data**: Tests completing registration

### Password Reset Tests (✅ Passing - 1 test)
- **password reset validates user exists and is active**: Tests token validation for valid users

## Current Status
- **23 tests passing** ✅
- **71 assertions total**
- **0 tests failing** ✅

## Test Setup

### Dependencies
- Uses `RefreshDatabase` trait
- Creates School, Schoolyear, and User with admin role in beforeEach
- User has confirmed_at, email_verified_at, and is_active set

### Test Data
- School: "Test School" with logo
- User: test@example.com with password 'password123'
- User has 'admin' role assigned
- All users are confirmed and active by default

### Key Requirements Discovered
1. **Users need roles**: Login requires user has 'super_admin', 'admin', or 'register_admin' role
2. **2FA needs school object**: When testing 2FA, school object must include `long_name`
3. **Request validation is strict**: Form Request classes validate `step` fields precisely
4. **Token format**: Tokens must be exactly 6 digits
5. **School data structure**: Login step 2 needs `school` as object with `id` and `long_name`

## Running Tests

```bash
# Run all AdminController tests
php artisan test --filter=AdminControllerTest

# Run specific test
php artisan test --filter="config returns json response"

# Run with verbose output
php artisan test --filter=AdminControllerTest -v
```

## Test Results
✅ All 23 tests passing with 71 assertions

## Architecture Notes
- Tests use actual HTTP requests (Feature tests)
- Database constraints require users to have school_id
- Form Request classes validate step fields strictly  
- 2FA flow requires multiple endpoints
- Authentication uses Laravel Sanctum
- Role management uses Spatie Permission package

## Test Coverage Summary

**Configuration Endpoints**: Full coverage of config endpoint including auth states, school data, menu generation, and health checks.

**Authentication Flow**: Complete login flow testing including email validation, password checks, 2FA handling, and role-based access.

**Logout Functionality**: Tests both authenticated and unauthenticated logout scenarios.

**Role Management**: Tests super admin role listing, access control, and authentication requirements.

**Registration Flow**: Tests token validation and user data updates during registration.

**Password Reset**: Tests token-based password reset validation for active users.

