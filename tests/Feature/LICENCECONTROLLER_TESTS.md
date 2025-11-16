# LicenceController Tests

## Overview
Professional Pest test suite for the `LicenceController` that handles licence management functionality. The controller provides endpoints for CRUD operations on application licences, with role-based access control for super admins and admins.

## Test Coverage

### Index Tests (✅ 9 tests)
- **index returns paginated licences for super admin**: Tests pagination structure for super admin users
- **index returns paginated licences for admin**: Validates admin users can access licence list
- **index denies access for regular user**: Ensures 403 for users without admin roles
- **index requires authentication**: Tests 401 for unauthenticated requests
- **index filters by search string**: Validates search functionality across licences
- **index searches in long_name field**: Tests search in long_name column
- **index searches in long_name and name fields**: Tests comprehensive search functionality
- **index orders results by long_name**: Validates alphabetical ordering
- **index returns empty data for no licences**: Tests empty state handling

### Store Tests (✅ 7 tests)
- **store creates new licence for super admin**: Tests successful licence creation
- **store denies access for admin user**: Ensures only super admins can create (403)
- **store denies access for regular user**: Validates 403 for non-super admin users
- **store requires authentication**: Tests 401 for unauthenticated requests
- **store validates required name field**: Tests validation for required fields
- **store validates unique name field**: Ensures unique constraint on name field
- **store accepts nullable fields**: Validates optional field handling

### Update Tests (✅ 7 tests)
- **update modifies existing licence for super admin**: Tests successful update
- **update denies access for admin user**: Ensures only super admins can update (403)
- **update denies access for regular user**: Validates 403 for non-super admin users
- **update requires authentication**: Tests 401 for unauthenticated requests
- **update validates required fields**: Tests validation for id and name fields
- **update allows same name for same licence**: Validates unique rule exception
- **update rejects duplicate name for different licence**: Tests unique constraint

### Load Licences Tests (✅ 6 tests)
- **load licences returns all licences for super admin**: Tests fetching all licences
- **load licences returns all licences for admin**: Validates admin access
- **load licences denies access for regular user**: Ensures 403 for non-admin users
- **load licences requires authentication**: Tests 401 for unauthenticated requests
- **load licences orders by long_name**: Validates alphabetical ordering
- **load licences returns empty array when no licences exist**: Tests empty state

### Delete Licences Tests (✅ 7 tests)
- **delete licences removes multiple licences for super admin**: Tests bulk deletion
- **delete licences denies access for admin user**: Ensures only super admins can delete (403)
- **delete licences denies access for regular user**: Validates 403 for non-super admin users
- **delete licences requires authentication**: Tests 401 for unauthenticated requests
- **delete licences prevents deletion if assigned to school**: Tests 409 conflict handling
- **delete licences validates licence ids exist**: Tests validation for existing IDs
- **delete licences accepts empty array**: Validates empty array handling

### Integration Tests (✅ 3 tests)
- **full licence crud workflow works correctly**: Tests complete CRUD lifecycle
- **pagination works correctly with multiple licences**: Validates pagination with large datasets
- **licence model attributes are correctly set**: Tests model attribute persistence

## Current Status
- **39 tests passing** ✅
- **112 assertions total**
- **0 tests failing** ✅

## Test Setup

### Dependencies
- Uses `RefreshDatabase` trait
- Creates School, Schoolyear in beforeEach
- Creates three user types: super_admin, admin, and regular user
- All users are confirmed and active

### Test Data
- School: "Test School"
- Super Admin: superadmin@example.com
- Admin: admin@example.com
- Regular User: user@example.com
- All passwords: password123

### Roles Configuration
Tests use Spatie Permission package with three role levels:
1. **super_admin**: Full access to all CRUD operations
2. **admin**: Read-only access (index and load licences)
3. **regular user**: No access (all requests return 403)

## API Endpoints Tested

### GET /api/admin/licences
Lists licences with pagination and search functionality.

**Query Parameters:**
- `search_string` (optional): Search in long_name field
- `page` (optional): Page number for pagination

**Response:**
```json
{
    "data": [
        {
            "id": 1,
            "name": "app_name",
            "long_name": "Application Name",
            "is_selectable": true,
            "price_per_year": 1000
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 1,
        "per_page": 15,
        "total": 1
    }
}
```

### POST /api/admin/licences
Creates a new licence (super admin only).

**Request:**
```json
{
    "name": "app_name",
    "long_name": "Application Name",
    "is_selectable": true,
    "price_per_year": 1000
}
```

**Response:** 200 with licence resource

### PUT /api/admin/licences/{id}
Updates an existing licence (super admin only).

**Request:**
```json
{
    "id": 1,
    "name": "app_name",
    "long_name": "Updated Name",
    "is_selectable": false,
    "price_per_year": 1500
}
```

**Response:** 200 with updated licence resource

### POST /api/admin/licences/load_licences
Returns all licences without pagination.

**Response:** 200 with array of licence resources

### POST /api/admin/licences/delete_licences
Deletes multiple licences by ID (super admin only).

**Request:**
```json
[1, 2, 3]
```

**Response:** 204 No Content on success, 409 if assigned to schools

## Running Tests

```bash
# Run all LicenceController tests
php artisan test --filter=LicenceControllerTest

# Run specific test
php artisan test --filter="store creates new licence for super admin"

# Run with verbose output
php artisan test --filter=LicenceControllerTest -v
```

## Test Results
✅ All 39 tests passing with 112 assertions

## Architecture Notes

### Licence Model
- Uses `$guarded = []` (mass assignment enabled)
- Fields: id, name, long_name, is_selectable, price_per_year, timestamps
- Unique constraint on `name` field
- Related to SchoolLicence through many-to-many relationship

### Controller Behavior
- **index()**: Paginated list with search, requires admin or super_admin
- **store()**: Create new licence, requires super_admin
- **update()**: Modify existing licence, requires super_admin
- **loadLicences()**: Returns all licences, requires admin or super_admin
- **deleteLicences()**: Bulk delete with conflict checking, requires super_admin

### Access Control
Uses custom `userHasRole()` method from base controller to check roles:
- Returns 403 "Sie haben keine Berechtigung" for unauthorized access
- Validates user has required role before processing request

### Validation Rules
**Store Request:**
- `name`: required, string, max:255, unique
- `long_name`: nullable, string, max:255
- `is_selectable`: boolean
- `price_per_year`: nullable, integer

**Update Request:**
- `id`: required, integer, exists:licences
- `name`: required, string, max:255, unique (except own)
- `long_name`: nullable, string, max:255
- `is_selectable`: boolean
- `price_per_year`: nullable, integer

**Delete Request:**
- Array of integers that exist in licences table

## Test Coverage Breakdown

**Index/List Operations**: 9 tests covering pagination, search, filtering, ordering, access control, and empty states.

**Create Operations**: 7 tests covering successful creation, validation, uniqueness constraints, and role-based access control.

**Update Operations**: 7 tests covering modification, validation, unique constraint handling with exceptions, and access control.

**Load All Operation**: 6 tests covering bulk retrieval, ordering, empty states, and access control.

**Delete Operations**: 7 tests covering bulk deletion, conflict prevention with school assignments, validation, and access control.

**Integration**: 3 tests covering full CRUD workflows, pagination with large datasets, and model attribute persistence.

## Important Notes

1. **Super Admin Only**: Create, update, and delete operations require super_admin role
2. **Admin Read Access**: Admins can list and load licences but cannot modify
3. **Conflict Checking**: Cannot delete licences assigned to schools (returns 409)
4. **Search Functionality**: Searches only in long_name field (controller line 34-36)
5. **Ordering**: All endpoints return results ordered by long_name alphabetically
6. **Pagination**: Index uses configured pagination size from `config('schooltool.pagination')`

## Business Logic

### SchoolLicence Integration
Tests verify that licences cannot be deleted if they are assigned to schools through the `school_licences` pivot table. The `LicenceService` checks for existing assignments and returns a 409 Conflict status if any are found.

### Unique Constraints
The `name` field must be unique across all licences. Update operations allow keeping the same name for the same licence but reject duplicates for different licences.

### Optional Fields
Only `name` is required. All other fields (`long_name`, `is_selectable`, `price_per_year`) are optional and can be null.

## Future Enhancement Opportunities

1. Add tests for licence resource transformation
2. Test soft delete functionality if implemented
3. Add tests for licence activation/deactivation
4. Test concurrent deletion scenarios
5. Add tests for audit logging if implemented
6. Test licence usage statistics
7. Add tests for licence expiration handling
