# PrintRegisterSupervisorJob Tests

## Overview
Comprehensive Pest test suite for the `PrintRegisterSupervisorJob` queue job that handles generating and emailing supervisor-based register PDFs with booking totals grouped by supervisor.

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

### Supervisor-Specific Tests
- **job handles multiple supervisors with bookings**: Validates the job works with multiple different supervisors
- **job groups bookings by supervisor correctly**: Tests that bookings are properly grouped by supervisor
- **job handles bookings with null supervisor**: Ensures the job handles missing supervisor data gracefully
- **job orders bookings by supervisor then date**: Verifies correct ordering (supervisor → date → time → name)
- **job creates PDF with supervisor totals**: Confirms totals are calculated and included per supervisor

### Data Handling Tests
- **job attaches PDF file to notification**: Ensures the generated PDF is attached to the email
- **job uses school logo in email**: Verifies the school logo is included in the email

### Error Handling Tests
- **job throws exception when register not found**: Tests that invalid register IDs throw ModelNotFoundException

### Serialization Tests
- **job can be serialized and unserialized**: Validates the job can be serialized for queue storage

### Email Content Tests
- **job subject line includes register name and supervisor indicator**: Confirms subject contains register name and "Pdf-Datei (Betreuer)"
- **job uses correct email template**: Verifies the correct markdown template is used

### File Generation Tests
- **job handles empty register with no bookings**: Tests successful completion even with no booking data
- **job creates PDF with correct filename pattern**: Validates the PDF filename includes "_betreuer.pdf"
- **job PDF includes header with supervisor title**: Confirms PDF header includes supervisor title
- **job PDF includes footer with school name**: Verifies school name is in PDF footer
- **job handles register with special characters in name**: Tests proper handling of special characters
- **job attaches file with correct path**: Validates the attachment path is correct

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

### Cleanup
- `afterEach` hook removes all generated PDF files ending with "_betreuer.pdf"

## PDF Generation Details

### Ordering Logic
Bookings are ordered by:
1. Supervisor name (alphabetically)
2. Date (chronologically)
3. From time (chronologically)
4. User last name (alphabetically)

### PDF Structure
- **View**: `pdfs.registerSupervisor`
- **Header View**: `pdfs.registerSupervisor_header` with title including "Betreuer"
- **Footer View**: `pdfs.registerSupervisor_footer` with school long name
- **Format**: A4
- **Data Included**:
  - Register name
  - All bookings with user and register date details
  - Totals grouped by supervisor
  - Total count of all bookings

### Filename Pattern
- Pattern: `{register_slug}_{timestamp}_betreuer.pdf`
- Example: `test_register_2024_20251116_123456_betreuer.pdf`

## Running Tests

```bash
# Run all PrintRegisterSupervisorJob tests
php artisan test --filter=PrintRegisterSupervisorJobTest

# Run a specific test
php artisan test --filter="job groups bookings by supervisor correctly"

# Run with verbose output
php artisan test --filter=PrintRegisterSupervisorJobTest -v

# Run all Print Register job tests together
php artisan test --filter=PrintRegister
```

## Test Results
All 26 tests passing with 39 assertions.
