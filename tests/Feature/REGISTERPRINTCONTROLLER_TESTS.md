# RegisterPrintController Tests

## Overview
Comprehensive Pest tests for `app/Http/Controllers/Admin/RegisterPrintController.php`

## Test Coverage

### Print Excel Endpoint (`POST /api/admin/registers/print_excel`)
- ✅ Admin can print Excel and job is dispatched
- ✅ Register admin can print Excel and job is dispatched
- ✅ Regular user cannot print Excel (403 Forbidden)
- ✅ Unauthenticated user cannot print Excel (401 Unauthorized)
- ✅ Validation: requires register_id
- ✅ Validation: requires valid register_id (exists in database)
- ✅ Validation: requires integer register_id

### Print Supervisor Endpoint (`POST /api/admin/registers/print_supervisor`)
- ✅ Admin can print Supervisor and job is dispatched
- ✅ Register admin can print Supervisor and job is dispatched
- ✅ Regular user cannot print Supervisor (403 Forbidden)
- ✅ Unauthenticated user cannot print Supervisor (401 Unauthorized)
- ✅ Validation: requires register_id
- ✅ Validation: requires valid register_id (exists in database)
- ✅ Validation: requires integer register_id

### Print Date Endpoint (`POST /api/admin/registers/print_date`)
- ✅ Admin can print Date and job is dispatched
- ✅ Register admin can print Date and job is dispatched
- ✅ Regular user cannot print Date (403 Forbidden)
- ✅ Unauthenticated user cannot print Date (401 Unauthorized)
- ✅ Validation: requires register_id
- ✅ Validation: requires valid register_id (exists in database)
- ✅ Validation: requires integer register_id

### General Edge Cases
- ✅ Print endpoints return 204 No Content on success
- ✅ All print endpoints dispatch correct jobs with correct parameters
- ✅ Multiple users can print simultaneously
- ✅ Print endpoints work with inactive registers

## Test Statistics
- **Total Tests**: 25
- **Total Assertions**: 80
- **All Tests Passing**: ✅

## Running the Tests

### Run all RegisterPrintController tests:
```bash
php artisan test --filter=RegisterPrintControllerTest
```

### Run specific test:
```bash
php artisan test --filter="admin can print Excel and job is dispatched"
```

### Run with coverage:
```bash
php artisan test --filter=RegisterPrintControllerTest --coverage
```

## Key Features Tested

### Authorization & Permissions
- Role-based access control (admin, register_admin)
- Proper 403 responses for unauthorized users
- Proper 401 responses for unauthenticated requests

### Job Dispatching
- PrintRegisterExcelJob dispatched with correct user and data
- PrintRegisterSupervisorJob dispatched with correct user and data
- PrintRegisterDateJob dispatched with correct user and data
- Queue facade properly mocked and verified

### Request Validation
- Required fields validation
- Type validation (integer)
- Database existence validation (register must exist)

### HTTP Responses
- 204 No Content on successful job dispatch
- 401 Unauthorized for unauthenticated requests
- 403 Forbidden for unauthorized users
- 422 Unprocessable Entity for validation errors

## Test Data Setup
Each test creates:
- School with logo and names
- Schoolyear linked to school
- Roles (admin, register_admin, register_user, user)
- Register (active and inactive variants)
- Multiple test users with different roles
- Queue facade faking for job verification

## Dependencies
- Laravel Testing Framework
- Pest PHP
- Spatie Permission Package
- Laravel Queue Facade

## Notes
- Tests use `RefreshDatabase` trait for database isolation
- Queue is faked to prevent actual job execution
- All API endpoints use JSON requests/responses
- Tests verify both success and failure scenarios
- Edge cases include inactive registers and concurrent users
