# HealthController Tests

## Overview
Professional Pest test suite for the `HealthController` that handles queue health checking functionality. The controller provides endpoints to test queue workers and verify they are processing jobs correctly.

## Test Coverage

### Test Queue Endpoint Tests (✅ 6 tests)
- **test queue creates queue test record**: Validates queue test record creation in database
- **test queue returns valid test id**: Verifies returned test ID is valid UUID
- **test queue requires authentication**: Ensures endpoint requires authentication (401)
- **test queue dispatches job correctly**: Tests job dispatch mechanism
- **test queue sets correct initial status**: Verifies initial status handling
- **test queue includes dispatched timestamp**: Checks timestamp is included in response

### Check Queue Status Tests (✅ 8 tests)
- **check queue status returns status for valid test id**: Tests status retrieval for valid test
- **check queue status returns completed status**: Validates completed job status
- **check queue status calculates duration correctly**: Tests duration calculation between dispatch and completion
- **check queue status returns null duration for incomplete jobs**: Verifies null duration for pending jobs
- **check queue status returns 404 for non-existent test**: Tests 404 response for invalid test ID
- **check queue status returns 404 for another users test**: Ensures users can only access their own tests
- **check queue status requires authentication**: Validates authentication requirement (401)
- **check queue status requires test_id parameter**: Tests parameter validation

### Integration Tests (✅ 4 tests)
- **full queue test workflow works end to end**: Tests complete workflow from creation to completion
- **multiple queue tests can be created by same user**: Verifies multiple concurrent tests
- **queue test model uses uuid for primary key**: Validates UUID primary key format
- **queue test belongs to user**: Tests user relationship

### Response Structure Tests (✅ 3 tests)
- **check queue status includes all timestamps**: Validates all timestamp fields
- **test queue response has correct json structure**: Tests response structure for test queue endpoint
- **check queue status response has correct json structure**: Tests response structure for status check endpoint

### Edge Cases (✅ 2 tests)
- **check queue status handles missing processed_at for completed status**: Tests edge case handling
- **test queue creates record with current timestamp**: Validates timestamp accuracy

## Current Status
- **23 tests passing** ✅
- **83 assertions total**
- **0 tests failing** ✅

## Test Setup

### Dependencies
- Uses `RefreshDatabase` trait
- Creates School, Schoolyear, and User with admin role in beforeEach
- User has confirmed_at, email_verified_at, and is_active set

### Test Data
- School: "Test School"
- User: test@example.com with password 'password123'
- User has 'admin' role assigned
- All users are confirmed and active by default

### Key Features Tested
1. **Queue Test Creation**: Tests can be initiated and records are created
2. **UUID Primary Keys**: Tests use UUID format for IDs
3. **User Isolation**: Users can only access their own queue tests
4. **Status Tracking**: Tests track dispatched, completed, and processed states
5. **Duration Calculation**: Accurate calculation of processing time
6. **Timestamp Management**: Proper handling of dispatched_at and processed_at
7. **Authentication**: All endpoints require authentication
8. **Error Handling**: Proper 404 responses for invalid or unauthorized requests

## API Endpoints Tested

### GET /api/admin/test-queue
Initiates a queue test by creating a QueueTest record and dispatching a job.

**Response:**
```json
{
    "success": true,
    "testId": "uuid",
    "status": "dispatched",
    "dispatched_at": "2025-11-16T00:00:00.000000Z",
    "message": "Queue test initiated"
}
```

### GET /api/admin/test-queue/check?test_id={uuid}
Checks the status of a queue test.

**Response:**
```json
{
    "success": true,
    "status": "completed",
    "is_completed": true,
    "dispatched_at": "2025-11-16T00:00:00.000000Z",
    "processed_at": "2025-11-16T00:00:05.000000Z",
    "duration_seconds": 5
}
```

## Running Tests

```bash
# Run all HealthController tests
php artisan test --filter=HealthControllerTest

# Run specific test
php artisan test --filter="test queue creates queue test record"

# Run with verbose output
php artisan test --filter=HealthControllerTest -v
```

## Test Results
✅ All 23 tests passing with 83 assertions

## Architecture Notes

### Queue Test Model
- Uses UUID as primary key (non-incrementing)
- Belongs to User relationship
- Tracks status: 'dispatched' or 'completed'
- Records dispatched_at and processed_at timestamps
- Calculates duration between timestamps

### Controller Behavior
- `testQueue()`: Creates test record, dispatches closure job, returns test details
- `checkQueueStatus()`: Retrieves test by ID and user, calculates completion status and duration
- Both methods require authentication
- User can only access their own queue tests

### Queue Behavior in Tests
The application uses Laravel's queue system with closures. In the test environment with `QUEUE_CONNECTION=sync`, jobs execute immediately, so tests account for both dispatched and completed states.

## Test Coverage Breakdown

**Queue Creation**: 6 tests covering record creation, authentication, job dispatch, and response structure.

**Status Checking**: 8 tests covering status retrieval, completion detection, duration calculation, authentication, and error handling.

**Integration**: 4 tests covering end-to-end workflows, concurrent tests, UUID handling, and relationships.

**Response Validation**: 3 tests ensuring JSON structure and data completeness.

**Edge Cases**: 2 tests handling unusual scenarios like missing timestamps and timing accuracy.

## Important Notes

1. **Synchronous Queue**: Tests run with sync queue driver, so jobs complete immediately
2. **UUID Format**: All test IDs follow UUID v4 format
3. **User Isolation**: Strong emphasis on testing that users cannot access other users' tests
4. **Duration Precision**: Duration calculations are tested with reasonable ranges due to timing variations
5. **Authentication Required**: All endpoints require authenticated users

## Future Enhancement Opportunities

1. Test with different queue drivers (Redis, Database)
2. Add tests for failed job scenarios
3. Test job retry mechanisms
4. Add performance benchmarking
5. Test queue worker timeout scenarios
6. Add tests for concurrent job processing
