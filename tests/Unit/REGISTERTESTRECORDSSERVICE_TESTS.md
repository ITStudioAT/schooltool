# RegisterTestRecordsService Tests

## Overview
Professional Pest test suite for `App\Services\RegisterTestRecordsService.php` covering test data generation for register system including users, registers, register dates, and bookings with realistic distribution patterns.

## Test Coverage

### Total Tests: 45
- **checkRequirement**: 5 tests
- **checkOrCreateUsers**: 6 tests
- **createRegisterEntries**: 16 tests
- **integration scenarios**: 3 tests
- **data validation**: 4 tests
- **edge cases**: 4 tests
- **date calculations**: 2 tests
- **supervisor distribution**: 2 tests
- **booking distribution**: 3 tests

## Test Structure

### checkRequirement() Tests

1. **Missing School**
   - Tests returns false when school ID 1 doesn't exist
   - Validates requirement check

2. **Missing Schoolyear**
   - Tests returns false when schoolyear ID 1 doesn't exist
   - Validates dual requirement

3. **Both Exist**
   - Tests returns true when both school and schoolyear exist
   - Validates success condition

4. **Wrong School ID**
   - Tests returns false when schoolyear has wrong school_id
   - Validates relationship validation

5. **Specific IDs Required**
   - Tests returns false for other IDs (not 1)
   - Validates hardcoded ID requirement

### checkOrCreateUsers() Tests

1. **Create When None Exist**
   - Tests creates 500 users from zero
   - Validates initial population

2. **False When 500+ Exist**
   - Tests returns false when threshold met
   - Validates guard condition

3. **Create When Below Threshold**
   - Tests creates 500 more when <500 exist
   - Validates additive behavior

4. **Exactly 500**
   - Tests returns false at exact threshold
   - Validates boundary condition

5. **More Than 500**
   - Tests returns false, no additional users
   - Validates preservation of existing

6. **499 Users**
   - Tests creates 500 more (total 999)
   - Validates just below threshold

### createRegisterEntries() Tests

1. **Register Creation**
   - Tests creates register named "Gruppengespräche"
   - Validates basic creation

2. **Required Fields**
   - Tests all show_* and must_* fields set to 1
   - Validates field configuration

3. **Three Days**
   - Tests creates dates for 3 distinct days
   - Validates date spread

4. **Specific Weekdays**
   - Tests creates Monday, Tuesday, Wednesday
   - Validates weekday targeting

5. **24 Dates Total**
   - Tests creates exactly 24 register dates
   - Validates total count (3 days × 8 slots)

6. **8 Dates Per Day**
   - Tests each day has 8 time slots
   - Validates per-day distribution

7. **Two Groups**
   - Tests creates "Gruppe 1" and "Gruppe 2"
   - Validates supervisor groups

8. **Time Slots**
   - Tests creates 08:00, 09:00, 10:00, 11:00 slots
   - Validates hourly scheduling

9. **Max Registrations**
   - Tests all dates have max_registrations = 20
   - Validates capacity setting

10. **Booking Count**
    - Tests creates 480 total bookings
    - Validates 24 dates × 20 bookings

11. **Bookings Per Date**
    - Tests each date has exactly 20 bookings
    - Validates full capacity usage

12. **Unique Users**
    - Tests 480 unique user IDs assigned
    - Validates no duplication

13. **Student Data**
    - Tests bookings have first_name, last_name, birthdate
    - Validates required fields

14. **School/Schoolyear**
    - Tests all bookings have school_id=1, schoolyear_id=1
    - Validates relationships

15. **Return Value**
    - Tests method returns true
    - Validates success indicator

16. **Description**
    - Tests register has German description text
    - Validates content

### Integration Scenarios

1. **Full Workflow**
   - Tests checkRequirement → checkOrCreateUsers → createRegisterEntries
   - Validates end-to-end process

2. **Re-running Creation**
   - Tests multiple createRegisterEntries calls
   - Validates idempotency (creates additional registers)

3. **Existing Users**
   - Tests workflow when users already above threshold
   - Validates skip logic

### Data Validation

1. **Time Ranges**
   - Tests all dates have to > from
   - Validates time logic

2. **Birthdates**
   - Tests all student birthdates in past
   - Validates realistic data

3. **Register ID Assignment**
   - Tests all dates reference correct register
   - Validates foreign keys

4. **Register Date ID**
   - Tests all bookings reference correct date
   - Validates booking relationships

### Edge Cases

1. **Missing School**
   - Tests handles missing school gracefully
   - Validates error handling

2. **Threshold Boundary**
   - Tests exactly 500 users handled correctly
   - Validates edge condition

3. **Single User**
   - Tests creates 500 when 1 exists
   - Validates low count handling

4. **High IDs**
   - Tests handles users with high IDs
   - Validates ID range flexibility

### Date Calculations

1. **Next Monday**
   - Tests correctly calculates next Monday
   - Validates Carbon date logic

2. **Chronological Order**
   - Tests dates in ascending order
   - Validates temporal sequencing

### Supervisor Distribution

1. **Even Distribution**
   - Tests 12 dates per group (Gruppe 1 & 2)
   - Validates equal allocation

2. **Alternating Pattern**
   - Tests groups alternate within time slots
   - Validates concurrent scheduling

### Booking Distribution

1. **Unique Users**
   - Tests no user appears twice
   - Validates randomization

2. **High ID First**
   - Tests uses highest user IDs first
   - Validates selection algorithm

3. **Last Name Copy**
   - Tests copies user.last_name to student_last_name
   - Validates data inheritance

## Test Dependencies

### Models
- School (HasFactory)
- Schoolyear (HasFactory)
- User (HasFactory)
- Register
- RegisterDate
- RegisterDateBooking

### External Libraries
- Carbon - Date calculations (next Monday/Tuesday/Wednesday)
- Faker - Random student first names and birthdates

## Running Tests

```bash
# Run all RegisterTestRecordsService tests
php artisan test --filter=RegisterTestRecordsServiceTest

# Run specific test group
php artisan test --filter=RegisterTestRecordsServiceTest::checkRequirement

# Run with coverage
php artisan test --filter=RegisterTestRecordsServiceTest --coverage
```

## Test Patterns Used

- **describe/it syntax**: Organized tests by method
- **beforeEach**: Setup service instance
- **RefreshDatabase**: Clean database per test
- **Factory pattern**: User creation
- **Assertion chaining**: Multiple expectations per test

## Coverage Areas

✅ Requirement validation (specific IDs)  
✅ User threshold checking (500 limit)  
✅ Bulk user creation (500 at a time)  
✅ Register creation with configuration  
✅ Multi-day date generation  
✅ Time slot creation (4 hours × 2 groups)  
✅ Booking distribution (480 bookings)  
✅ Unique user assignment  
✅ Realistic test data (names, birthdates)  
✅ Relationship integrity  
✅ Chronological ordering  
✅ Edge cases (boundaries, missing data)  

## Key Behaviors Tested

### Hardcoded Requirements
Service requires:
- School with ID = 1
- Schoolyear with ID = 1, school_id = 1
- Returns false if either missing

### User Threshold Logic
```php
if (User::count() >= 500) return false;
User::factory()->count(500)->create();
return true;
```

Additive creation:
- 0 users → creates 500 → returns true
- 499 users → creates 500 → returns true (total 999)
- 500+ users → creates 0 → returns false

### Test Data Structure
Schedule for 3 days (Mon, Tue, Wed):
- 4 time slots per day: 08:00-09:00, 09:00-10:00, 10:00-11:00, 11:00-12:00
- 2 groups per slot: "Gruppe 1", "Gruppe 2"
- Total: 3 days × 4 slots × 2 groups = 24 register dates

Bookings:
- 20 bookings per date
- Total: 24 dates × 20 = 480 bookings
- Uses 480 of 500 created users
- Each user assigned to exactly one date

### User Selection Algorithm
```php
$highestId = User::max('id');
$ids = range($highestId, $highestId - 499);
```

Picks users from highest ID downward:
- Creates array of 500 IDs
- Randomly selects without replacement
- Removes used IDs from pool
- Ensures each user booked once

### Date Calculation
Uses Carbon for next occurrence:
```php
Carbon::now()->next(Carbon::MONDAY)
Carbon::now()->next(Carbon::TUESDAY)
Carbon::now()->next(Carbon::WEDNESDAY)
```

Always future dates, relative to test execution time.

### Student Data Generation
Uses Faker for realistic data:
- `student_first_name`: fake()->firstName()
- `student_birthdate`: fake()->dateTimeBetween('-10 years', 'now')
- `student_last_name`: copied from user.last_name

## Register Configuration

The test register "Gruppengespräche" has all fields enabled:
- `show_phone = 1`, `must_phone = 1`
- `show_student_last_name = 1`, `must_student_last_name = 1`
- `show_student_first_name = 1`, `must_student_first_name = 1`
- `show_student_birthdate = 1`, `must_student_birthdate = 1`

Description includes German welcome text for parent registration.

## Data Consistency

### Relationship Validation
All records use:
- `school_id = 1`
- `schoolyear_id = 1`

Register dates:
- `register_id` → references created register
- `max_registrations = 20`

Bookings:
- `register_id` → from date
- `register_date_id` → specific time slot
- `user_id` → from user pool

### Time Slot Logic
Each slot is 1 hour:
- 08:00 - 09:00
- 09:00 - 10:00
- 10:00 - 11:00
- 11:00 - 12:00

Tests validate `from < to` for all dates.

## Use Case

This service is designed for:
- Development environment setup
- Demo/presentation data
- Load testing (480 bookings)
- UI/UX testing with realistic data
- Integration testing with complete dataset

Not intended for production use (hardcoded IDs, bulk creation).

## Notes

- Service creates additive data (can run multiple times)
- Each run creates new register with 24 dates
- Users never duplicated (pool exhausted after 480)
- 20 users remain unboked (500 - 480 = 20)
- Dates always in future (next Mon/Tue/Wed)
- Supervisor names in German ("Gruppe 1", "Gruppe 2")
- Description text in German for parents
- Randomization ensures varied booking patterns
- All times in 24-hour format
- Birthdates realistic for school children (0-10 years old)

## Performance Characteristics

Test execution validates:
- Creating 500 users efficiently
- Bulk inserting 24 dates
- Creating 480 bookings with relationships
- Random selection without collisions
- Database transaction integrity

Total objects created per run:
- 1 register
- 24 register dates
- 480 register date bookings
- 500 users (conditional)

## Limitations Tested

- Requires specific IDs (school=1, schoolyear=1)
- Uses exactly 480 of 500 users
- Hardcoded to 3 days (Mon, Tue, Wed)
- Fixed time slots (08:00-12:00)
- Fixed capacity (20 per slot)
- German language content
- Cannot customize date range
- Cannot specify different supervisors
- All bookings have same structure

These limitations are validated as expected behavior, not bugs.
