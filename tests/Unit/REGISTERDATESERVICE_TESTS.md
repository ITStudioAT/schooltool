# RegisterDateService Tests

## Overview
Professional Pest test suite for `App\Services\RegisterDateService.php` covering date loading, date creation with complex time slot generation, and safe deletion of register dates.

## Test Coverage

### Total Tests: 40
- **loadDays**: 9 tests
- **createDates**: 17 tests
- **deleteRegisterDates**: 8 tests
- **integration scenarios**: 3 tests
- **edge cases**: 4 tests

## Test Structure

### loadDays() Tests

1. **Empty Result**
   - Tests returns empty array when no dates exist
   - Validates default behavior

2. **Basic Loading**
   - Tests dates are loaded for specific register
   - Validates date retrieval

3. **Date Grouping**
   - Tests multiple time slots on same date grouped
   - Validates groupBy('date') behavior

4. **Bookings Count**
   - Tests bookings_count included for each date
   - Validates withCount relationship

5. **Bookings Sum**
   - Tests bookings from multiple times on same date summed
   - Validates aggregation across grouped dates

6. **Chronological Ordering**
   - Tests dates returned in order by date
   - Validates orderBy('date') clause

7. **Day Name Addition**
   - Tests day name added to each date
   - Validates Carbon locale formatting

8. **Null Date Filtering**
   - Tests dates with null date value excluded
   - Validates whereNotNull('date')

9. **Register Isolation**
   - Tests only dates for specified register loaded
   - Validates register_id filtering

### createDates() Tests

1. **Single Day Creation**
   - Tests dates created for one day
   - Validates basic functionality

2. **Multiple Days**
   - Tests dates created across multiple weekdays
   - Validates day filtering (Mon, Wed, Fri)

3. **Multiple Time Slots**
   - Tests multiple time slots per day
   - Validates slot generation (10-11, 11-12, 12-13, 13-14)

4. **Pause Between Slots**
   - Tests pause time respected between slots
   - Validates cursor advancement logic

5. **Multiple Supervisors**
   - Tests separate dates created for each supervisor
   - Validates supervisor iteration

6. **Checkbox Format**
   - Tests accepts monday=1, wednesday=1 format
   - Validates legacy checkbox input

7. **Legacy Supervisor Format**
   - Tests supervisor_1 to supervisor_5 accepted
   - Validates backward compatibility

8. **Validation - Zero Minutes**
   - Tests returns false when min_per_date is 0
   - Validates required field check

9. **Validation - No Days**
   - Tests returns false when no days specified
   - Validates day requirement

10. **Validation - No Supervisors**
    - Tests returns false when no supervisors
    - Validates supervisor requirement

11. **Date Swap**
    - Tests auto-swaps if end date before start date
    - Validates date range normalization

12. **Upsert Behavior**
    - Tests duplicate dates not created
    - Validates upsert with unique keys

13. **Max Registrations**
    - Tests max_registrations value set correctly
    - Validates field assignment

14. **Date Until Default**
    - Tests date_until defaults to date_from
    - Validates optional parameter

15. **Overnight Windows**
    - Tests handles time_until < time_from (overnight)
    - Validates day addition for overnight

16. **Case Insensitivity**
    - Tests day names normalized to lowercase
    - Validates case-insensitive comparison

17. **Empty Supervisor Filtering**
    - Tests empty/null supervisor values removed
    - Validates array_filter logic

### deleteRegisterDates() Tests

1. **Basic Deletion**
   - Tests dates without bookings deleted
   - Validates successful deletion

2. **Protected Deletion**
   - Tests dates with bookings NOT deleted
   - Validates booking protection

3. **Selective Deletion**
   - Tests mixed: deletes without bookings, keeps with bookings
   - Validates whereDoesntHave vs whereHas

4. **School Filtering**
   - Tests only deletes dates for specified school
   - Validates multi-tenancy

5. **Register Filtering**
   - Tests only deletes dates for specified register
   - Validates register isolation

6. **Empty Array**
   - Tests handles empty date array gracefully
   - Validates whereIn with empty array

7. **No Matches**
   - Tests returns true even when no dates match
   - Validates consistent return value

### Integration Scenarios

1. **Create and Load Workflow**
   - Tests complete flow: create dates → load days
   - Validates end-to-end integration

2. **Full Lifecycle**
   - Tests create → load → delete workflow
   - Validates complete CRUD operations

3. **Complex Multi-Day Multi-Supervisor**
   - Tests 5 days × 3 supervisors × 2 slots = 30 dates
   - Validates complex scenario handling

### Edge Cases

1. **Single Minute Slot**
   - Tests handles very short time slots (5 minutes)
   - Validates minimal duration

2. **Year-End Range**
   - Tests handles date ranges crossing year boundary
   - Validates date handling across years

3. **No Matching Weekdays**
   - Tests when date range has no matching weekdays
   - Validates zero results acceptable

4. **Non-Existent Register**
   - Tests loading dates for invalid register
   - Validates empty result handling

## Test Dependencies

### Models
- RegisterDate (HasFactory)
- RegisterDateBooking (HasFactory)
- Register (HasFactory)
- School (HasFactory)
- Schoolyear (HasFactory)
- User (HasFactory)

### External Libraries
- Carbon - Date/time manipulation
- CarbonPeriod - Date range iteration

## Running Tests

```bash
# Run all RegisterDateService tests
php artisan test --filter=RegisterDateServiceTest

# Run specific test group
php artisan test --filter=RegisterDateServiceTest::loadDays

# Run with coverage
php artisan test --filter=RegisterDateServiceTest --coverage
```

## Test Patterns Used

- **describe/it syntax**: Organized tests by method
- **beforeEach**: Setup test data (school, schoolyear, register)
- **RefreshDatabase**: Clean database per test
- **Factory pattern**: Consistent test data creation

## Coverage Areas

✅ Date loading with grouping  
✅ Bookings count aggregation  
✅ Complex time slot generation  
✅ Multiple supervisors per slot  
✅ Pause time between slots  
✅ Day of week filtering  
✅ Date range handling  
✅ Overnight time windows  
✅ Input format flexibility  
✅ Validation (required fields)  
✅ Upsert to prevent duplicates  
✅ Safe deletion (respects bookings)  
✅ Multi-tenancy (school/register filtering)  
✅ Edge cases (year boundaries, minimal slots)  

## Key Behaviors Tested

### Date Generation Algorithm
Complex slot generation:
- Iterates through date range (CarbonPeriod)
- Filters by weekday names
- Creates time slots based on duration + pause
- Generates entry per supervisor
- Uses upsert to avoid duplicates

### Input Format Flexibility
Multiple input formats supported:
- Array: `['days' => ['monday', 'wednesday']]`
- Checkboxes: `['monday' => '1', 'wednesday' => '1']`
- Single supervisor: `['supervisor' => 'Name']`
- Multiple supervisors: `['supervisors' => ['A', 'B']]`
- Legacy: `['supervisor_1' => 'A', 'supervisor_2' => 'B']`

### Safe Deletion
Booking protection:
- Queries dates with bookings separately
- Only deletes dates without bookings
- Returns list of blocked IDs
- Respects multi-tenancy boundaries

### Date Grouping
Smart aggregation in loadDays:
- Groups multiple time slots by date
- Sums bookings_count across slots
- Returns one entry per unique date
- Adds localized day names

## Time Slot Calculation

### Formula
```
total_window = time_until - time_from
slot_duration = min_per_date
slot_interval = min_per_date + pause
max_slots = floor(total_window / slot_interval)
```

### Example
- Window: 10:00 - 14:00 (240 minutes)
- Duration: 60 minutes
- Pause: 30 minutes
- Interval: 90 minutes
- Slots: 10:00-11:00, 11:30-12:30, 13:00-14:00 (3 slots)

## Upsert Unique Keys

RegisterDate uniqueness defined by:
- school_id
- schoolyear_id
- register_id
- supervisor
- date
- from
- to

## Notes

- Tests use Carbon for date manipulation
- Service normalizes day names to lowercase English
- Overnight windows handled by adding day to end time
- Reversed date ranges auto-swapped
- Empty/null values filtered from supervisors
- loadDays groups by date, not by individual slots
- Deletion respects booking relationships
- All operations respect school/register boundaries

## Validation Rules

Service returns `false` when:
- `min_per_date <= 0`
- `wantedDays` array empty
- `supervisors` array empty after filtering

Otherwise returns `true` even if no dates created.
