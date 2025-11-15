# PrintRegisterService Tests

## Overview
Professional Pest test suite for `App\Services\PrintRegisterService.php` covering all three main methods: `printSupervisor()`, `printDate()`, and `printExcel()`.

## Test Coverage

### Total Tests: 30
- **printSupervisor**: 9 tests
- **printDate**: 9 tests  
- **printExcel**: 9 tests
- **Edge Cases**: 3 tests

## Test Structure

### printSupervisor() Tests

1. **Exception Handling**
   - Validates ModelNotFoundException when register doesn't exist

2. **PDF Generation**
   - Confirms PDF file is generated with correct path structure
   - Validates filename format: `{register_name}_{date}_{time}_betreuer.pdf`

3. **Data Retrieval**
   - Tests booking relationships (user, registerDate)
   - Validates correct view usage (`pdfs.registerSupervisor`)

4. **Sorting Order**
   - Verifies bookings ordered by: supervisor → date → time → last name

5. **Aggregation**
   - Tests totals calculated correctly by supervisor
   - Validates total count accuracy

6. **View Data Structure**
   - Confirms all required data keys present
   - Validates header/footer views with correct parameters

### printDate() Tests

1. **Exception Handling**
   - Validates ModelNotFoundException when register doesn't exist

2. **PDF Generation**
   - Confirms PDF file is generated with correct path
   - Validates filename format matches expected pattern

3. **Data Retrieval**
   - Tests proper relationships loaded
   - Validates correct view usage (`pdfs.registerDate`)

4. **Sorting Order**
   - Verifies bookings ordered by: date → supervisor → time → last name

5. **Aggregation**
   - Tests totals calculated correctly by date
   - Validates total count accuracy

6. **View Data Structure**
   - Confirms all required data keys present
   - Validates header/footer views configured properly

### printExcel() Tests

1. **Exception Handling**
   - Validates ModelNotFoundException when register doesn't exist

2. **File Generation**
   - Confirms Excel file created with correct filename format
   - Validates file exists and has content

3. **Data Ordering**
   - Tests bookings sorted by: date → time → supervisor → last name

4. **Excel Structure**
   - Validates headers: Datum, Von, Bis, Betreuer, Kind N.n., Kind V.n., Geb-Datum, Nachname, Vorname, Email, Telefon
   - Confirms all required fields exported

5. **Data Accuracy**
   - Tests user information included correctly
   - Validates student data exported

6. **Edge Cases**
   - Tests empty bookings handled gracefully
   - Validates multiple bookings exported correctly

### Edge Case Tests

1. **Null Supervisor Handling**
   - Tests printSupervisor with null supervisor values
   - Ensures totals calculated correctly with empty supervisor

2. **Null Date Handling**
   - Tests printDate with null date values
   - Validates graceful handling of missing dates

3. **Special Characters in Names**
   - Tests filename generation with special characters
   - Confirms Str::slug() properly sanitizes register names

## Test Dependencies

### Models
- Register (HasFactory)
- RegisterDate (HasFactory)
- RegisterDateBooking (HasFactory)
- School (HasFactory)
- Schoolyear (HasFactory)
- User (HasFactory)

### Factories Created
- `SchoolyearFactory`
- `RegisterFactory`
- `RegisterDateFactory`
- `RegisterDateBookingFactory`

### External Dependencies
- Spatie LaravelPDF (with Pdf::fake())
- Spatie SimpleExcel

## Running Tests

```bash
# Run all PrintRegisterService tests
php artisan test --filter=PrintRegisterServiceTest

# Run specific test group
php artisan test --filter=PrintRegisterServiceTest::printSupervisor

# Run with coverage
php artisan test --filter=PrintRegisterServiceTest --coverage
```

## Test Patterns Used

- **describe/it syntax**: Organized tests by method and behavior
- **beforeEach**: Setup shared test data (school, register, user)
- **afterEach**: Cleanup generated files
- **Pdf::fake()**: Mock PDF generation to avoid actual file creation in PDF tests
- **Factory pattern**: Use Laravel factories for consistent test data

## Coverage Areas

✅ Exception handling  
✅ File generation and naming  
✅ Data retrieval and relationships  
✅ Sorting and ordering  
✅ Aggregation calculations  
✅ View data structure  
✅ Edge cases (null values, special characters)  
✅ Multiple records handling  
✅ Empty data sets  

## Notes

- Tests use RefreshDatabase trait for clean database state
- Generated PDF/Excel files are cleaned up in afterEach hook
- Tests account for Windows path separators
- Pdf::fake() used to avoid actual PDF rendering during tests
