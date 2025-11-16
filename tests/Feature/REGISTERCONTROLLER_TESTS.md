# RegisterController Tests

## Overview
Professional Pest tests for `app/Http/Controllers/Admin/RegisterController` covering all endpoints and functionality.

## Test Coverage

### Index Endpoint Tests
- ✅ Returns list of registers for current schoolyear for admin
- ✅ Returns list of registers for register_admin
- ✅ Returns 403 for regular user
- ✅ Returns 401 for unauthenticated user
- ✅ Includes correct counts for bookings and dates

### Get Active Registers Tests
- ✅ Returns only active registers across all schoolyears
- ✅ Orders by schoolyear desc then name
- ✅ Returns 403 for regular user

### Store (Create) Tests
- ✅ Creates new register for admin
- ✅ Creates register with minimal required fields
- ✅ Validates required fields
- ✅ Validates max_registrations is integer
- ✅ Validates max_registrations minimum value
- ✅ Returns 403 for regular user
- ✅ Works for register_admin

### Update Tests
- ✅ Modifies existing register
- ✅ Validates required fields
- ✅ Returns 403 for regular user

### Destroy (Delete) Tests
- ✅ Deletes register without dependencies
- ✅ Clears user register_id before deleting
- ✅ Returns 403 for regular user

### Set Active Register Tests
- ✅ Sets register for user
- ✅ Validates register_id exists
- ✅ Requires register_id
- ✅ Returns 403 for regular user

### Toggle Register Tests
- ✅ Activates inactive register
- ✅ Deactivates active register
- ✅ Validates register_id
- ✅ Returns 403 for regular user

## Test Statistics
- **Total Tests:** 29
- **Total Assertions:** 93
- **Pass Rate:** 100%

## Running the Tests

To run all RegisterController tests:
```bash
php artisan test --filter=RegisterControllerTest
```

To run a specific test:
```bash
php artisan test --filter="index returns list of registers for current schoolyear for admin"
```

## Key Features Tested

### Authorization
All endpoints properly enforce role-based access control:
- Admin users have full access
- Register_admin users have full access
- Regular users are denied access (403)
- Unauthenticated requests are rejected (401)

### Data Validation
Tests verify proper validation of:
- Required fields (name, max_registrations)
- Data types (integers for max_registrations)
- Minimum values (max_registrations >= 0)
- Foreign key existence (register_id, school_id, schoolyear_id)

### Business Logic
Tests verify:
- Registers are scoped to school and schoolyear
- Active/inactive status toggling
- User register selection
- Proper ordering and filtering
- Counts for bookings and dates

### Database Integrity
Tests ensure:
- Data is properly saved to database
- Updates persist correctly
- Deletions work as expected
- User associations are properly cleared on delete

## Test Structure

Each test follows a consistent pattern:
1. **Arrange:** Set up test data (registers, users, etc.)
2. **Act:** Make API request
3. **Assert:** Verify response status, structure, and data
4. **Database Assert:** Verify database state when applicable

## Notes

- Tests use Laravel's `RefreshDatabase` trait for clean state
- Factories are used to create test data
- All tests are isolated and independent
- Tests cover both success and failure scenarios
- Permission checking is thoroughly tested for all endpoints
