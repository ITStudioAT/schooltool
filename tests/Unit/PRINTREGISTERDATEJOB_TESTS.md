# PrintRegisterDateJob Tests

## Overview
Comprehensive Pest test suite for the `PrintRegisterDateJob` queue job that handles generating and emailing date-based register PDFs.

## Test Coverage

### Job Structure Tests
- **job can be instantiated**: Verifies the job can be created with user and data parameters
- **job implements ShouldQueue interface**: Confirms the job is queueable
- **job can be dispatched to queue**: Tests that the job can be dispatched to the queue system

### Job Execution Tests
- **job sends notification email when handled**: Ensures the job sends a notification when executed
- **job notification contains correct email data**: Validates email metadata (from address, subject, markdown template)
- **job uses PrintRegisterService to generate PDF**: Confirms PDF generation uses the correct service
- **job retrieves correct register from data**: Verifies the job fetches the correct register by ID
- **job accesses user selected school**: Tests that the job accesses the user's selected school correctly
- **job notification routes to correct email address**: Confirms email is sent to the correct address

### Data Handling Tests
- **job handles multiple register dates**: Validates the job works with registers containing multiple dates
- **job attaches PDF file to notification**: Ensures the generated PDF is attached to the email
- **job uses school logo in email**: Verifies the school logo is included in the email

### Error Handling Tests
- **job throws exception when register not found**: Tests that invalid register IDs throw ModelNotFoundException

### Serialization Tests
- **job can be serialized and unserialized**: Validates the job can be serialized for queue storage

### Email Content Tests
- **job subject line includes register name and date indicator**: Confirms subject contains register name and "Pdf-Datei (Tag)"

## Test Setup

### Dependencies
- Uses `RefreshDatabase` trait to reset database between tests
- Mocks `Pdf` facade to avoid actual PDF generation during tests
- Uses `Notification::fake()` to capture notifications without sending emails

### Test Data
Each test creates:
- A school with long name, short name, and logo
- A schoolyear associated with the school
- A register associated with school and schoolyear
- A test user with email and school association
- Test data array with register_id

## Running Tests

```bash
# Run all PrintRegisterDateJob tests
php artisan test --filter=PrintRegisterDateJobTest

# Run a specific test
php artisan test --filter="job can be instantiated"
```

## Test Results
All 15 tests passing with 27 assertions.
