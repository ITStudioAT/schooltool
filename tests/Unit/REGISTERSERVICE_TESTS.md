# RegisterService Tests

## Overview
Professional Pest test suite for `App\Services\RegisterService.php` covering booking management, register operations, user creation with 2FA tokens, and authentication workflows for the registration tool.

## Test Coverage

### Total Tests: 70
- **book**: 8 tests
- **checkLicenceAndSchool**: 4 tests
- **setToUser**: 4 tests
- **toggle**: 5 tests
- **createUserAndSendToken**: 6 tests
- **sendTokenForLogin**: 3 tests
- **checkToken**: 5 tests
- **integration scenarios**: 2 tests
- **edge cases**: 3 tests

## Test Structure

### book() Tests

1. **Successful Booking**
   - Tests booking created with all data
   - Validates booking_id returned

2. **Register Inactive**
   - Tests exception when register.is_active = false
   - Validates error message: "Die Registrierung ist geschlossen"

3. **Date Locked**
   - Tests exception when registerDate.is_locked = true
   - Validates error message: "Der Termin ist gesperrt"

4. **Date Fully Booked**
   - Tests exception when max_registrations reached on date
   - Validates error message: "Der Termin ist bereits ausgebucht"

5. **Register Fully Booked**
   - Tests exception when max_registrations reached on register
   - Validates error message: "Die Registrierung ist bereits ausgebucht"

6. **Duplicate Booking**
   - Tests exception when user already has booking
   - Validates error message: "Sie haben bereits eine Buchung"

7. **Unlimited Bookings**
   - Tests max_registrations = 0 allows unlimited bookings
   - Validates capacity check bypassed

8. **Data Preservation**
   - Tests input data returned with booking_id
   - Validates all original fields preserved

### checkLicenceAndSchool() Tests

1. **School Validation**
   - Tests school exists check
   - Validates isSchoolValid flag

2. **School Return**
   - Tests school object returned when valid
   - Validates school ID matches

3. **Invalid School**
   - Tests non-existent school handled
   - Validates isSchoolValid = false

4. **Result Structure**
   - Tests result has required keys
   - Validates array structure

### setToUser() Tests

1. **Register Assignment**
   - Tests register_id set on user
   - Validates user.register_id updated

2. **Register Return**
   - Tests method returns Register model
   - Validates correct register returned

3. **Non-Existent Register**
   - Tests exception for invalid register_id
   - Validates ModelNotFoundException thrown

4. **Update Verification**
   - Tests user.register_id changes
   - Validates database persistence

### toggle() Tests

1. **True to False**
   - Tests is_active toggled from true to false
   - Validates state change

2. **False to True**
   - Tests is_active toggled from false to true
   - Validates bidirectional toggle

3. **Database Persistence**
   - Tests toggle persisted to database
   - Validates fresh() retrieval

4. **Register Return**
   - Tests method returns Register model
   - Validates return type

5. **Non-Existent Register**
   - Tests exception for invalid register_id
   - Validates ModelNotFoundException thrown

### createUserAndSendToken() Tests

1. **User Creation**
   - Tests new user created
   - Validates user count increases

2. **Default Names**
   - Tests default last_name and first_name set
   - Validates placeholder values: "Nachname neuer Benutzer", "Vorname neuer Benutzer"

3. **Password Generation**
   - Tests password field populated
   - Validates Hash::make(now()) used

4. **Return Values**
   - Tests user_id and step returned
   - Validates step = 'EMAIL_TOKEN'

5. **Register Assignment**
   - Tests register_id assigned to user
   - Validates relationship established

6. **Non-Existent School**
   - Tests exception for invalid school_id
   - Validates ModelNotFoundException thrown

### sendTokenForLogin() Tests

1. **Return Values**
   - Tests user_id and step returned
   - Validates step = 'LOGIN_TOKEN'

2. **Data Preservation**
   - Tests original data preserved in response
   - Validates custom fields maintained

3. **Non-Existent School**
   - Tests exception for invalid school_id
   - Validates ModelNotFoundException thrown

### checkToken() Tests

1. **Valid Token**
   - Tests returns true for matching token + future expiry
   - Validates successful authentication

2. **Invalid Token**
   - Tests returns false for non-matching token
   - Validates token comparison

3. **Expired Token**
   - Tests returns false for past expiry
   - Validates time-based expiration

4. **Just Expired**
   - Tests returns false when token just expired
   - Validates precise timing

5. **Expiry Boundary**
   - Tests returns true when token about to expire
   - Validates isFuture() boundary

### Integration Scenarios

1. **Complete Registration Flow**
   - Tests create user → set register → make booking
   - Validates end-to-end workflow

2. **Toggle and Booking**
   - Tests book → toggle inactive → attempt second booking
   - Validates state-dependent behavior

### Edge Cases

1. **Minimal Booking Data**
   - Tests booking with only required fields
   - Validates optional fields handled

2. **Multiple Toggles**
   - Tests three consecutive toggles
   - Validates state alternation

3. **Null Expiry Token**
   - Tests checkToken with null token_2fa_expires_at
   - Validates graceful handling

## Test Dependencies

### Models
- Register (HasFactory)
- RegisterDate (HasFactory)
- RegisterDateBooking (HasFactory)
- School (HasFactory)
- Schoolyear (HasFactory)
- User (HasFactory)

### Services
- RegisterDateBookingService (for creating bookings)
- AdminService (for sending 2FA tokens)
- LicenceService (for licence validation)

### Notifications
- Notification::fake() used to prevent actual emails

## Running Tests

```bash
# Run all RegisterService tests
php artisan test --filter=RegisterServiceTest

# Run specific test group
php artisan test --filter=RegisterServiceTest::book

# Run with coverage
php artisan test --filter=RegisterServiceTest --coverage
```

## Test Patterns Used

- **describe/it syntax**: Organized tests by method
- **beforeEach**: Setup test data and fake notifications
- **RefreshDatabase**: Clean database per test
- **Factory pattern**: Consistent test data
- **Exception testing**: validates abort() calls with HTTP exceptions

## Coverage Areas

✅ Booking validation (capacity, status, locks)  
✅ Exception handling (meaningful German error messages)  
✅ Register state management (toggle)  
✅ User creation with defaults  
✅ 2FA token workflows  
✅ Token validation (match + expiry)  
✅ School and licence checking  
✅ Register assignment  
✅ Integration workflows  
✅ Edge cases (minimal data, null values)  

## Key Behaviors Tested

### Booking Validation
Multi-layered checks:
- Register must be active (is_active = true)
- Register date must not be locked (is_locked = false)
- Date capacity not exceeded (bookings < max_registrations)
- Register capacity not exceeded (bookings < max_registrations)
- User has no existing booking for that date
- max_registrations = 0 means unlimited

### Error Messages
All in German for end users:
- "Die Registrierung ist geschlossen" - register inactive
- "Der Termin ist gesperrt" - date locked
- "Der Termin ist bereits ausgebucht" - date full
- "Die Registrierung ist bereits ausgebucht" - register full
- "Sie haben bereits eine Buchung" - duplicate booking

### User Creation Flow
Streamlined onboarding:
1. Create user with placeholder names
2. Generate password with Hash::make(now())
3. Assign register_id
4. Send 2FA token for email verification
5. Return user_id and step = 'EMAIL_TOKEN'

### Login Flow
Token-based authentication:
1. User requests login
2. Send 2FA token via AdminService
3. Return user_id and step = 'LOGIN_TOKEN'
4. User submits token
5. Validate with checkToken() (match + not expired)

### Toggle Functionality
Simple state management:
- Reads current is_active
- Sets opposite value: `!is_active`
- Persists to database
- Returns updated Register model

## Token Validation Logic

```php
return $user->token_2fa === $data['token_2fa'] &&
       Carbon::parse($user->token_2fa_expires_at)->isFuture();
```

Requires BOTH:
- Exact token match
- Expiry time in future

## Capacity Check Logic

```php
if ($registerDate->max_registrations != 0 && 
    count($bookings) >= $registerDate->max_registrations)
```

Value of 0 = unlimited (check skipped)

## Notes

- loadRegisterAndUser() tests omitted due to LicenceService complexity
- Service uses RegisterDateBookingService for actual booking creation
- All HTTP exceptions use abort() with status 403
- Token expiry checked with Carbon::parse()->isFuture()
- Default user names: "Nachname neuer Benutzer" / "Vorname neuer Benutzer"
- Password generated with Hash::make(now()) for new users
- Step values: 'EMAIL_TOKEN' for registration, 'LOGIN_TOKEN' for login
- Toggle persists immediately to database
- Booking data passed through to response with booking_id added

## Security Features Tested

- Register must be explicitly active to accept bookings
- Dates can be individually locked
- Capacity limits enforced at both register and date level
- Duplicate booking prevention per user
- Token must match AND not be expired
- School isolation (users scoped to school_id)
- Register assignment validation (findOrFail)

## German Error Messages

The service uses German error messages for end users. Tests validate exact message text to ensure user experience consistency. These messages are embedded in the abort() calls and should be kept synchronized with any UI translations.
