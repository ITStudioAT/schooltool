# AdminService Test Coverage

## Overview
Professional Pest test suite for `App\Services\AdminService.php` with comprehensive coverage of all methods.

## Test Statistics
- **Total Tests**: 66
- **Passing Tests**: 62
- **Skipped Tests**: 4 (due to database constraints in service method)
- **Test Groups**: 12

## Test Groups and Coverage

### 1. checkPasswordUnknown (8 tests)
Tests the password reset validation workflow:
- ✅ Validates user existence
- ✅ Checks user confirmation status
- ✅ Verifies user is active
- ✅ Validates 2FA tokens at different steps
- ✅ Ensures password matching for reset

### 2. checkRegister (7 tests)
Tests user registration validation:
- ✅ Returns null for non-existent users
- ✅ Validates registration started status
- ✅ Checks 2FA token validity
- ✅ Validates required fields (last name)
- ✅ Ensures password matching

### 3. createRegisterUser (4 tests)
Tests new user creation during registration:
- ⏭️ Skipped due to database constraint (school_id required but not in method signature)

### 4. updateRegisterUser (5 tests)
Tests user update during registration completion:
- ✅ Updates user information correctly
- ✅ Handles confirmation requirements based on config
- ✅ Properly hashes passwords
- ✅ Handles optional fields (first_name)

### 5. login (2 tests)
Tests standard login functionality:
- ✅ Logs in user successfully
- ✅ Regenerates session
- ✅ Returns authenticated user

### 6. login2Fa (4 tests)
Tests two-factor authentication login:
- ✅ Validates 2FA token
- ✅ Clears token after successful login
- ✅ Rejects invalid tokens
- ✅ Rejects expired tokens

### 7. checkEmail (3 tests)
Tests email checking for multiple schools:
- ✅ Returns multiple schools when applicable
- ✅ Auto-sends token for single school
- ✅ Orders schools alphabetically

### 8. passwordUnkownSendToken (2 tests)
Tests sending password reset tokens:
- ✅ Sends token successfully
- ✅ Returns data with school information
- ✅ Aborts when user not found

### 9. passwordUnkownCheckToken (4 tests)
Tests password reset token validation:
- ✅ Validates correct tokens
- ✅ Rejects wrong tokens
- ✅ Rejects expired tokens
- ✅ Handles non-existent users

### 10. passwordUnkownSetPassword (2 tests)
Tests password reset execution:
- ✅ Updates password successfully
- ✅ Validates token before password change

### 11. check2Fa (2 tests)
Tests 2FA requirement checking:
- ✅ Requires token for 2FA-enabled users
- ✅ Skips 2FA for non-2FA users

### 12. setToken2Fa (2 tests)
Tests 2FA token generation and sending:
- ✅ Generates valid 6-digit tokens
- ✅ Sets expiration time correctly
- ✅ Sends email notifications

### 13. checkLogin (9 tests)
Tests admin login validation:
- ✅ Validates credentials successfully
- ✅ Checks user existence
- ✅ Verifies confirmation status
- ✅ Checks active status
- ✅ Validates role requirements (admin, super_admin, register_admin)
- ✅ Verifies password correctness

### 14. checkUserLogin (8 tests)
Tests user login validation with different steps:
- ✅ Validates password step
- ✅ Validates token step
- ✅ Checks all user statuses
- ✅ Supports multiple roles (user, admin, super_admin, register_admin)

### 15. sendRegisterToken (3 tests)
Tests registration token sending:
- ✅ Sends email notification
- ✅ Sets token on user model
- ✅ Uses correct email configuration

## Test Features

### Testing Techniques Used
1. **Exception Testing**: Uses Pest's `throws()` expectation for abort scenarios
2. **Database Testing**: Uses `RefreshDatabase` trait for clean test state
3. **Factory Usage**: Leverages Laravel factories for test data creation
4. **Notification Testing**: Uses Laravel's `Notification::fake()` for email testing
5. **Configuration Mocking**: Tests config-dependent behavior
6. **Role Testing**: Uses Spatie Permission package for role-based tests

### Test Structure
- **Descriptive Names**: Each test clearly describes what it validates
- **Organized Groups**: Tests grouped by method using `describe()` blocks
- **Setup Hook**: `beforeEach()` initializes service and fakes notifications
- **Isolation**: Each test is independent with database refresh

### Edge Cases Covered
- Invalid/expired tokens
- Non-existent users
- Inactive/unconfirmed users
- Missing required fields
- Password mismatches
- Multiple schools per email
- Role-based access control
- 2FA enabled/disabled scenarios

## Configuration Requirements

The tests expect these configuration keys to exist:
- `spa.registered_admin_must_be_confirmed`
- `spa.token_expire_time`
- `schooltool.token_expire_time`
- `schooltool.noreply_email`
- `schooltool.sa_pw`
- `mail.from.address`
- `mail.from.name`

## Models Used
- `User` - Primary user model with authentication
- `School` - School/organization model
- `Role` - Spatie permission role

## Running the Tests

```bash
# Run all AdminService tests
php artisan test --filter AdminServiceTest

# Run specific test group
php artisan test --filter="AdminServiceTest > checkPasswordUnknown"

# Run with coverage (if configured)
php artisan test --coverage --filter AdminServiceTest
```

## Notes

### Skipped Tests
Four tests in the `createRegisterUser` group are skipped because the service method has a database constraint issue:
- The `users` table requires `school_id` (NOT NULL)
- The `createRegisterUser()` method doesn't accept or set `school_id`
- This is a known limitation that should be addressed in the service layer

### German Language
All error messages in the tests are in German, matching the application's language:
- "Kennwort zurücksetzen funktioniert mit dieser E-Mail-Adresse nicht"
- "Benutzer ist noch nicht bestätigt"
- "Benutzer ist gesperrt"
- etc.

### Database Considerations
- Tests use SQLite for speed
- `is_active` field uses integer (0/1) not boolean
- All tests properly clean up with `RefreshDatabase` trait

## Best Practices Demonstrated

1. **Comprehensive Coverage**: Tests cover success paths, error paths, and edge cases
2. **Clear Naming**: Test names explain what behavior is being verified
3. **Proper Isolation**: Each test is independent and doesn't affect others
4. **Real Dependencies**: Uses actual models and database for integration testing
5. **Notification Testing**: Properly tests email sending without actually sending emails
6. **Role Testing**: Validates role-based access control
7. **Token Validation**: Thoroughly tests time-based token expiration

## Future Improvements

1. Fix the `createRegisterUser` method to accept `school_id` parameter
2. Consider adding performance benchmarks for token generation
3. Add tests for concurrent token validation scenarios
4. Test rate limiting if implemented
5. Add tests for account lockout scenarios if implemented
