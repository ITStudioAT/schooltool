# RecordsCreateService Tests

## Overview
Professional Pest test suite for `App\Services\RecordsCreateService.php` covering the initialization and creation of core system records including schools, schoolyears, roles, and admin users.

## Test Coverage

### Total Tests: 37
- **initRecords**: 8 tests
- **firstOrCreateSchool (private method behavior)**: 2 tests
- **firstOrCreateSchoolyear (private method behavior)**: 3 tests
- **checkOrCreateAdminRoles (private method behavior)**: 3 tests
- **checkOrCreateAdmins (private method behavior)**: 3 tests
- **checkOrCreateAdmin (private method behavior)**: 6 tests
- **checkOrCreateSchoolyears (private method behavior)**: 3 tests
- **integration scenarios**: 5 tests
- **edge cases**: 4 tests

## Test Structure

### initRecords() Tests

1. **Initial School Creation**
   - Validates school is created when database is empty
   - Confirms correct school attributes (CDGym)

2. **Schoolyear Creation**
   - Tests initial schoolyear is created for new school
   - Validates schoolyear count increases

3. **Role Creation**
   - Ensures super_admin and admin roles are created
   - Tests role existence after initialization

4. **Super Admin User**
   - Validates super admin user creation (kron@naturwelt.at)
   - Confirms role assignment and user attributes

5. **Duplicate Prevention**
   - Tests multiple calls don't create duplicate schools
   - Validates idempotency of the service

6. **Multiple Schools**
   - Tests all existing schools are processed
   - Validates schoolyears and admins for each school

7. **Config Integration**
   - Tests schoolyears created from config
   - Validates config data is used correctly

8. **User Activation**
   - Confirms users are active and confirmed
   - Tests email verification and confirmation

### firstOrCreateSchool() Tests

1. **School Attributes**
   - Tests school created with correct name, logo, etc.
   - Validates is_selectable flag

2. **Existing School**
   - Tests existing school is returned, not duplicated
   - Validates school identity preservation

### firstOrCreateSchoolyear() Tests

1. **Default Values**
   - Tests schoolyear created with default 2025/26 values
   - Validates dates (from, until, sem_2_start)

2. **School Association**
   - Tests schoolyear is linked to correct school
   - Validates foreign key relationship

3. **Duplicate Prevention**
   - Tests no duplicate schoolyears for same school
   - Validates firstOrCreate behavior

### checkOrCreateAdminRoles() Tests

1. **Super Admin Role**
   - Tests super_admin role creation
   - Validates guard_name is 'web'

2. **Admin Role**
   - Tests admin role creation
   - Validates role attributes

3. **Idempotency**
   - Tests multiple calls don't duplicate roles
   - Validates role count remains consistent

### checkOrCreateAdmins() Tests

1. **Role Assignment**
   - Tests admin user gets super_admin role
   - Validates role relationship

2. **Multiple Schools**
   - Tests admin created for each school
   - Validates school-specific admin accounts

3. **Schoolyear Association**
   - Tests admin linked to schoolyear when available
   - Validates foreign key assignment

### checkOrCreateAdmin() Tests

1. **User Attributes**
   - Tests user created with correct name and email
   - Validates is_active flag

2. **Password**
   - Tests password set from environment
   - Validates password is not null

3. **Email Verification**
   - Tests email is verified and confirmed
   - Validates timestamp fields

4. **Duplicate Prevention**
   - Tests existing user not duplicated
   - Validates user identity preserved

5. **Role Assignment to Existing User**
   - Tests role assigned to pre-existing user
   - Validates role synchronization

6. **Null Schoolyear Handling**
   - Tests user creation works without schoolyear
   - Validates graceful null handling

### checkOrCreateSchoolyears() Tests

1. **Multiple Schoolyears**
   - Tests all config schoolyears are created
   - Validates count matches config

2. **Config Data**
   - Tests dates from config are applied correctly
   - Validates from, until, sem_2_start fields

3. **Idempotency**
   - Tests multiple calls don't duplicate schoolyears
   - Validates firstOrCreate behavior

### Integration Scenarios

1. **Empty Database**
   - Tests complete initialization from scratch
   - Validates all entities created

2. **Idempotency**
   - Tests service can be called multiple times safely
   - Validates record counts remain stable

3. **Complete Relationships**
   - Tests user has all required relationships
   - Validates school, schoolyear, and role links

4. **Partial Data**
   - Tests service handles existing partial data
   - Validates completion of missing records

5. **Multiple Schools Integrity**
   - Tests data integrity across multiple schools
   - Validates each school gets complete setup

### Edge Cases

1. **Empty Config**
   - Tests behavior with empty schoolyears config
   - Validates fallback behavior

2. **Missing Environment Variable**
   - Tests handling of missing SA_PW
   - Validates graceful degradation

3. **Preserve Existing Attributes**
   - Tests existing school attributes not overwritten
   - Validates data preservation

4. **User Without Schoolyear**
   - Tests user creation when schoolyear missing
   - Validates null handling

## Test Dependencies

### Models
- School (HasFactory)
- Schoolyear (HasFactory)
- User (HasFactory)
- Role (Spatie Permission)

### Configuration
- `config/schooltool.php` - schoolyears array

### Environment
- `SA_PW` - Super admin password

## Running Tests

```bash
# Run all RecordsCreateService tests
php artisan test --filter=RecordsCreateServiceTest

# Run specific test group
php artisan test --filter=RecordsCreateServiceTest::initRecords

# Run with coverage
php artisan test --filter=RecordsCreateServiceTest --coverage
```

## Test Patterns Used

- **describe/it syntax**: Organized tests by method and behavior
- **beforeEach**: Setup service instance
- **RefreshDatabase**: Clean database state for each test
- **Config mocking**: Test with different configurations
- **Factory pattern**: Create test data consistently

## Coverage Areas

✅ School initialization  
✅ Schoolyear creation from config  
✅ Role creation (super_admin, admin)  
✅ Admin user creation  
✅ Duplicate prevention  
✅ Multiple schools handling  
✅ Idempotency  
✅ Relationship integrity  
✅ Edge cases (null values, missing config)  
✅ Environment variable handling  
✅ Data preservation  

## Key Behaviors Tested

### Idempotency
The service can be called multiple times without creating duplicate records:
- Uses `firstOrCreate()` pattern
- Validates existing records before creation
- Safe for repeated execution

### Multi-School Support
Properly handles multiple schools:
- Processes all existing schools
- Creates schoolyears for each
- Creates admin user per school

### Configuration Driven
Uses configuration files:
- Reads schoolyears from config
- Applies dates and settings correctly
- Handles missing or empty config

### Data Integrity
Maintains proper relationships:
- Users linked to schools and schoolyears
- Roles properly assigned
- Foreign keys validated

## Notes

- Tests use RefreshDatabase trait for isolation
- Service is designed for application initialization
- All private methods are tested through public interface
- Tests verify both creation and retrieval paths
- Typo in service: `firstOrCReate` should be `firstOrCreate` (tested as-is)
