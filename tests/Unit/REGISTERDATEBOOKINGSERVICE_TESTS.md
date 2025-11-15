# RegisterDateBookingService Tests

## Overview
Professional Pest test suite for `App\Services\RegisterDateBookingService.php` covering booking management, user creation/updates, and email notifications for register date bookings.

## Test Coverage

### Total Tests: 34
- **deleteBookings**: 8 tests
- **updateOrCreateUser**: 10 tests
- **createBooking**: 9 tests
- **integration scenarios**: 3 tests
- **edge cases**: 4 tests

## Test Structure

### deleteBookings() Tests

1. **Single Deletion Without Notification**
   - Tests booking is deleted without sending email
   - Validates notification is not sent

2. **Single Deletion With Notification**
   - Tests booking deletion with email notification
   - Validates StandardEmail notification sent

3. **Multiple Bookings Deletion**
   - Tests multiple bookings can be deleted in one call
   - Validates batch deletion functionality

4. **Notification Email Content**
   - Tests email has correct subject: "Stornierung Termin"
   - Validates correct markdown template used

5. **Booking Details in Notification**
   - Tests all booking details included in notification
   - Validates register name, student info, date, time, note

6. **School and Register Filtering**
   - Tests only bookings from current school/register deleted
   - Validates security boundary enforcement

7. **Empty Array Handling**
   - Tests service handles empty booking array
   - Validates no errors thrown

8. **School Information in Notification**
   - Tests school name used in notification "from" field
   - Validates school branding in emails

### updateOrCreateUser() Tests

1. **New User Creation**
   - Tests user created when email doesn't exist
   - Validates all user attributes set correctly

2. **Email Verification on Creation**
   - Tests email_verified_at set for new users
   - Validates confirmed_at set automatically

3. **Password Generation**
   - Tests password created for new users
   - Validates password field not null

4. **Role Assignment on Creation**
   - Tests register_user role assigned to new users
   - Validates role relationship

5. **Existing User Update**
   - Tests existing user updated, not duplicated
   - Validates user attributes can be changed

6. **Conditional Email Verification**
   - Tests email_verified_at set only if null
   - Validates existing verification not overwritten

7. **Conditional Confirmation**
   - Tests confirmed_at set only if null
   - Validates existing confirmation preserved

8. **Preserve Verification Dates**
   - Tests existing email_verified_at not changed
   - Validates timestamp integrity

9. **Role Assignment on Update**
   - Tests register_user role assigned to existing users
   - Validates role synchronization

10. **School Isolation**
    - Tests users filtered by school_id
    - Validates multi-tenancy security

### createBooking() Tests

1. **Booking Creation**
   - Tests booking created with all fields
   - Validates foreign keys set correctly

2. **No Notification When False**
   - Tests is_notify: false prevents email
   - Validates notification control

3. **No Notification When Unset**
   - Tests default behavior (no notification)
   - Validates opt-in notification pattern

4. **Notification When True**
   - Tests is_notify: true sends email
   - Validates notification triggered

5. **Notification Subject and Template**
   - Tests email subject: "Buchung Termin"
   - Validates correct markdown template

6. **Booking Details in Notification**
   - Tests all booking data included in email
   - Validates complete information sent

7. **is_notify Removal**
   - Tests is_notify not stored in database
   - Validates data sanitization

8. **School Information**
   - Tests school name in notification
   - Validates school branding

9. **Relationship Loading**
   - Tests booking relationships accessible
   - Validates eager loading works

### Integration Scenarios

1. **Complete Workflow with New User**
   - Tests full flow: create user → create booking → notify
   - Validates end-to-end functionality

2. **Booking Deletion Workflow**
   - Tests creating and deleting bookings
   - Validates lifecycle management

3. **Update User and Create Booking**
   - Tests updating existing user then booking
   - Validates user update before booking

### Edge Cases

1. **Minimal Booking Data**
   - Tests booking with only required fields
   - Validates flexibility

2. **Minimal User Data**
   - Tests user creation with minimum info
   - Validates required fields only

3. **Null Note in Notification**
   - Tests notification works with null note
   - Validates null handling

4. **Optional Fields**
   - Tests booking with null optional fields
   - Validates field optionality

## Test Dependencies

### Models
- RegisterDateBooking (HasFactory)
- Register (HasFactory)
- RegisterDate (HasFactory)
- School (HasFactory)
- Schoolyear (HasFactory)
- User (HasFactory)
- Role (Spatie Permission)

### Notifications
- StandardEmail (queued notification)

### Configuration
- `config/schooltool.php` - noreply_email

## Running Tests

```bash
# Run all RegisterDateBookingService tests
php artisan test --filter=RegisterDateBookingServiceTest

# Run specific test group
php artisan test --filter=RegisterDateBookingServiceTest::deleteBookings

# Run with coverage
php artisan test --filter=RegisterDateBookingServiceTest --coverage
```

## Test Patterns Used

- **describe/it syntax**: Organized tests by method
- **beforeEach**: Setup test data and fake notifications
- **Notification::fake()**: Mock notification sending
- **RefreshDatabase**: Clean database per test
- **Factory pattern**: Consistent test data
- **Anonymous notifiable**: Test route notifications

## Coverage Areas

✅ Booking deletion (single and batch)  
✅ Email notifications (opt-in)  
✅ User creation and updates  
✅ Role assignment  
✅ Email verification handling  
✅ Multi-tenancy (school filtering)  
✅ Notification content validation  
✅ Relationship loading  
✅ Data sanitization (is_notify)  
✅ Edge cases (null values, minimal data)  

## Key Behaviors Tested

### Notification System
Email notifications are opt-in:
- Deletion: notify parameter controls email
- Creation: is_notify field controls email
- Uses StandardEmail notification
- Includes complete booking details
- Uses school branding

### User Management
Flexible user handling:
- Creates new users automatically
- Updates existing users
- Sets verification/confirmation automatically
- Assigns register_user role
- School-scoped queries

### Security
Multi-tenancy enforcement:
- Bookings filtered by school_id and register_id
- Users scoped to school
- Cannot delete other school's bookings
- Data isolation validated

### Data Handling
Clean data management:
- is_notify removed before storage
- Null values handled gracefully
- Optional fields supported
- Foreign keys validated

## Notification Structure

### Deletion Email
- **Subject**: "Stornierung Termin"
- **Template**: mails.admin.deleteRegisterDateBooking
- **Data**: register_name, student info, date, time, note

### Booking Email
- **Subject**: "Buchung Termin"
- **Template**: mails.admin.bookRegisterDateBooking
- **Data**: register_name, student info, date, time, note

## Notes

- Tests use Notification::fake() to avoid actual emails
- StandardEmail uses `$data` property (not `$mail`)
- Service doesn't handle null booking queries (tests adjusted)
- All notifications sent via route() to user's email
- Bookings require school_id, register_id filtering for security
- User passwords auto-generated with Hash::make(now())
