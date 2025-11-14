# HomepageRoutingService Test Documentation

This document describes the comprehensive Pest test suite for `App\Services\HomepageRoutingService`.

## Test Overview

The HomepageRoutingService determines the appropriate homepage redirect URL based on school and licence parameters. The test suite covers all functionality including:

- Default homepage routing without parameters
- School validation and routing
- Licence validation and routing
- Error handling for invalid inputs
- Integration scenarios with multiple schools and licences
- Edge cases and return value validation

## Test Structure

### Test Groups

#### 1. `checkRoute without school parameter` Tests (4 tests)
Tests the default behavior when no school is provided.

- **returns default homepage redirect when no school is provided**: Validates default `/homepage` redirect
- **returns default homepage redirect when school is empty string**: Tests empty string handling
- **returns default homepage redirect when school is false**: Tests boolean false handling
- **ignores licence parameter when no school is provided**: Ensures licence is ignored without school

#### 2. `checkRoute with invalid school` Tests (3 tests)
Tests error handling for non-existent schools.

- **returns error when school does not exist**: Validates error response for missing school
- **returns error for school with special characters**: Tests handling of invalid characters
- **is case sensitive for school short_name**: Confirms case-sensitive school lookup

#### 3. `checkRoute with valid school only` Tests (6 tests)
Tests successful school routing without licence.

- **returns success with school parameter when school exists**: Basic school routing
- **handles school with single letter short_name**: Tests minimal valid name
- **handles school with long short_name**: Tests extended school names
- **handles school with numbers in short_name**: Validates alphanumeric names
- **ignores licence when licence parameter is empty**: Tests empty licence parameter
- **ignores licence when licence parameter is false**: Tests false licence parameter

#### 4. `checkRoute with school and invalid licence` Tests (4 tests)
Tests error handling for invalid licence scenarios.

- **returns error when licence does not exist**: Validates error for missing licence
- **returns error when school does not have the licence**: Tests unauthorized licence access
- **returns error when school licence has expired**: Validates expiration checking
- **returns error when school licence expired today**: Tests same-day expiration

#### 5. `checkRoute with school and valid licence` Tests (5 tests)
Tests successful routing with both school and valid licence.

- **returns success with both school and licence parameters**: Basic dual parameter routing
- **returns success when licence is valid in far future**: Tests long-term validity
- **returns success when licence expires later today**: Tests same-day validity
- **handles licence with hyphenated name**: Tests licence name formatting
- **handles licence with underscore in name**: Tests alternative name formatting

#### 6. `checkRoute with null valid_until` Tests (1 test)
Tests handling of unlimited/perpetual licences.

- **handles school licence with null valid_until**: Documents current behavior with null expiry dates

#### 7. `checkRoute integration tests` Tests (4 tests)
Tests complex real-world scenarios.

- **handles multiple schools with same licence name**: Tests licence isolation between schools
- **handles school with multiple licences**: Validates multiple licence support per school
- **handles school with expired and valid licences**: Tests mixed licence states
- **uses LicenceService internally for licence validation**: Verifies service integration

#### 8. `checkRoute edge cases` Tests (5 tests)
Tests boundary conditions and special cases.

- **handles whitespace in school parameter**: Tests trimming/validation of whitespace
- **handles null values correctly**: Validates explicit null parameter handling
- **returns consistent response structure on success**: Verifies success response format
- **returns consistent response structure on error**: Verifies error response format
- **handles URL special characters in school name**: Tests URL-safe character handling

#### 9. `checkRoute return value validation` Tests (4 tests)
Tests response structure and consistency.

- **always returns an array**: Validates return type consistency
- **always includes status key**: Ensures status is always present
- **status is either ok or error**: Validates status value constraints
- **includes redirect on success and msg on error**: Verifies conditional key presence

## Code Coverage

### Covered Functionality

✅ Default homepage redirect without parameters  
✅ School lookup by short_name  
✅ School validation and error handling  
✅ Case-sensitive school matching  
✅ Licence lookup by name  
✅ School-licence relationship validation  
✅ Licence expiration checking  
✅ URL parameter construction  
✅ LicenceService integration  
✅ Error message generation (German)  
✅ Response structure consistency  
✅ Multiple schools with same licence  
✅ Multiple licences per school  
✅ Mixed valid/expired licence states  
✅ Edge cases (null, empty, whitespace)  
✅ Special characters in parameters  

### Test Statistics

- **Total Tests**: 36
- **Total Assertions**: 111
- **Test Duration**: ~0.95 seconds
- **Pass Rate**: 100%

## Setup and Cleanup

### beforeEach Hook
- Instantiates fresh HomepageRoutingService
- Database is automatically refreshed per test (RefreshDatabase trait)

## Running the Tests

### Run all HomepageRoutingService tests:
```bash
php artisan test --filter=HomepageRoutingServiceTest
```

### Run specific test group:
```bash
php artisan test --filter="HomepageRoutingServiceTest::checkRoute without school parameter"
php artisan test --filter="HomepageRoutingServiceTest::integration tests"
```

### Run with coverage:
```bash
php artisan test --filter=HomepageRoutingServiceTest --coverage
```

## Key Testing Patterns

### 1. Database Setup
Tests use Laravel factories to create test data:
```php
$school = School::factory()->create(['short_name' => 'TEST']);
$licence = Licence::create(['name' => 'premium-app', 'long_name' => 'Premium Application']);
SchoolLicence::create([
    'school_id' => $school->id,
    'licence_id' => $licence->id,
    'valid_until' => Carbon::tomorrow(),
]);
```

### 2. Response Structure Validation
Tests validate both success and error response formats:
```php
// Success response
expect($result)->toBeArray()
    ->and($result['status'])->toBe('ok')
    ->and($result['redirect'])->toBe('/homepage/?school=TEST');

// Error response
expect($result)->toBeArray()
    ->and($result['status'])->toBe('error')
    ->and($result['msg'])->toContain('konnte nicht gefunden werden');
```

### 3. Time-based Testing
Carbon is used for precise date/time testing:
```php
'valid_until' => Carbon::yesterday(),    // Expired
'valid_until' => Carbon::tomorrow(),     // Valid
'valid_until' => Carbon::now()->addYears(10), // Long-term valid
```

### 4. Service Mocking
Integration tests verify LicenceService usage:
```php
$licenceService = Mockery::mock(LicenceService::class);
$licenceService->shouldReceive('checkLicence')
    ->once()
    ->andReturn(['status' => 'ok', 'redirect' => '&licence=test-app']);
```

## Dependencies

- **Laravel Framework**: Database and model interactions
- **Pest PHP**: Testing framework
- **Carbon**: Date/time manipulation
- **Mockery**: Service mocking for integration tests

## Response Formats

### Success Response
```php
[
    'status' => 'ok',
    'redirect' => '/homepage/?school=TEST&licence=app-name'
]
```

### Error Response
```php
[
    'status' => 'error',
    'msg' => 'Die Schule konnte nicht gefunden werden.'
]
```

## Error Messages (German)

The service returns German error messages:

1. **'Die Schule konnte nicht gefunden werden.'** - School not found
2. **'Die Lizenz konnte nicht gefunden werden.'** - Licence not found
3. **'Die Schule hat für die App keine Lizenz.'** - School doesn't have licence
4. **'Die Lizenz für die App ist abgelaufen.'** - Licence has expired

## URL Construction Logic

The service builds URLs progressively:

1. Base: `/homepage`
2. With school: `/homepage/?school=SCHOOLNAME`
3. With school + licence: `/homepage/?school=SCHOOLNAME&licence=LICENCENAME`

## Edge Cases Covered

1. **Null Parameters**: Both school_load and licence_load can be null
2. **Empty Strings**: Treated as falsy values
3. **Boolean False**: Treated as no parameter provided
4. **Whitespace**: Treated as invalid school name
5. **Case Sensitivity**: School lookup is case-sensitive
6. **Special Characters**: URL special characters are preserved (not encoded by service)
7. **Non-existent Schools**: Returns error immediately
8. **Non-existent Licences**: Returns error after school validation
9. **Expired Licences**: Checked via Carbon date comparison
10. **Null valid_until**: Currently causes error (documented behavior)
11. **Multiple Licences**: Each licence is independently validated
12. **Concurrent Schools**: Different schools can have same licence name

## Integration with LicenceService

The HomepageRoutingService delegates licence validation to LicenceService:

1. **checkLicence($school, $licence_load)**: Validates licence exists, school has it, and it's not expired
2. Returns structure: `['status' => 'ok|error', 'redirect'|'msg' => '...']`
3. Redirect format from LicenceService: `'&licence=LICENCENAME'`

## Known Issues and Limitations

### Null valid_until Handling
The current implementation doesn't properly handle `null` values for `valid_until` (which should represent unlimited/perpetual licences). The `checkLicence` method in LicenceService attempts to parse `null` with `Carbon::parse()`, which causes issues. The test documents this behavior.

**Note**: The `isLicenceValid` method in LicenceService properly handles `null` valid_until, but `checkLicence` does not. This inconsistency is documented but not fixed per testing guidelines.

## Best Practices Demonstrated

1. **Comprehensive Coverage**: All code paths and conditions tested
2. **Clear Naming**: Test names describe exact behavior being validated
3. **Logical Grouping**: Related tests grouped with `describe()` blocks
4. **Edge Case Testing**: Boundary conditions and special values tested
5. **Integration Testing**: Service interactions verified with mocks
6. **Response Consistency**: Structure validation for all responses
7. **Database Isolation**: RefreshDatabase ensures clean state per test
8. **German Language Support**: Error messages tested in original German
9. **Real-world Scenarios**: Integration tests simulate actual usage patterns
10. **Documentation**: Comments explain non-obvious behaviors

## Future Enhancements

Potential areas for additional testing:
- URL encoding of special characters in parameters
- SQL injection prevention in school short_name lookup
- Performance testing with large numbers of schools/licences
- Concurrent request handling
- Invalid date format handling in valid_until
- Middleware integration testing
- Route parameter validation
- User permission checking (if applicable)

## Notes

- Tests use Laravel's RefreshDatabase trait for isolation
- All tests are independent and can run in any order
- German error messages are preserved as-is
- School lookup is case-sensitive by design
- Service does not URL-encode parameters
- No authentication/authorization is tested (may be handled elsewhere)
- Tests document current behavior, including known limitations
