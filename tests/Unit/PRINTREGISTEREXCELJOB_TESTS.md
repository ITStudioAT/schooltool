# PrintRegisterExcelJob Tests

## Overview
Comprehensive Pest test suite for the `PrintRegisterExcelJob` queue job that handles generating and emailing Excel files with register booking data.

## Test Coverage

### Job Structure Tests
- **job can be instantiated**: Verifies the job can be created with user and data parameters
- **job implements ShouldQueue interface**: Confirms the job is queueable
- **job can be dispatched to queue**: Tests that the job can be dispatched to the queue system

### Job Execution Tests
- **job sends notification email when handled**: Ensures the job sends a notification when executed
- **job notification contains correct email data**: Validates email metadata (from address, subject, markdown template)
- **job uses PrintRegisterService to generate Excel file**: Confirms Excel generation uses the correct service
- **job retrieves correct register from data**: Verifies the job fetches the correct register by ID
- **job accesses user selected school**: Tests that the job accesses the user's selected school correctly
- **job notification routes to correct email address**: Confirms email is sent to the correct address

### Data Handling Tests
- **job handles multiple register dates with bookings**: Validates the job works with registers containing multiple dates and bookings with student data
- **job attaches Excel file to notification**: Ensures the generated Excel file is attached to the email
- **job uses school logo in email**: Verifies the school logo is included in the email

### Error Handling Tests
- **job throws exception when register not found**: Tests that invalid register IDs throw ModelNotFoundException

### Serialization Tests
- **job can be serialized and unserialized**: Validates the job can be serialized for queue storage

### Email Content Tests
- **job subject line includes register name and Excel indicator**: Confirms subject contains register name and "Excel-Datei"
- **job uses correct email template**: Verifies the correct markdown template is used

### File Generation Tests
- **job creates Excel file with correct filename format**: Validates the Excel filename follows the expected pattern (slug_timestamp.xlsx)
- **job generates Excel file in correct directory**: Confirms files are created in app/private/excel
- **job handles empty register with no bookings**: Tests successful completion even with no booking data
- **job Excel file has correct structure with bookings**: Verifies Excel file contains data when bookings exist
- **job handles register with special characters in name**: Tests proper handling of special characters in register names
- **job attaches file with correct path**: Validates the attachment path is correct and file exists

## Test Setup

### Dependencies
- Uses `RefreshDatabase` trait to reset database between tests
- Uses `Notification::fake()` to capture notifications without sending emails
- Creates private/excel directory for test file generation

### Test Data
Each test creates:
- A school with long name, short name, and logo
- A schoolyear associated with the school
- A register associated with school and schoolyear
- A test user with email and school association
- Test data array with register_id

### Cleanup
- `afterEach` hook removes all generated Excel files after each test

## Excel File Structure
The generated Excel files contain the following columns:
- Datum (Date)
- Von (From time)
- Bis (To time)
- Betreuer (Supervisor)
- Kind N.n. (Student last name)
- Kind V.n. (Student first name)
- Geb-Datum (Student birthdate)
- Nachname (User last name)
- Vorname (User first name)
- Email
- Telefon (Phone)

## Running Tests

```bash
# Run all PrintRegisterExcelJob tests
php artisan test --filter=PrintRegisterExcelJobTest

# Run a specific test
php artisan test --filter="job creates Excel file with correct filename format"

# Run with verbose output
php artisan test --filter=PrintRegisterExcelJobTest -v
```

## Test Results
All 22 tests passing with 40 assertions.
