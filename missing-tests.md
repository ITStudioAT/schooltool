# High Priority (Critical Infrastructure)

✅ FileUploadService - COMPLETED (28 tests)
ImportTeachersListJob - Missing
Middleware: ApiAllowed ✅, WebAllowed ✅ (both tested)

# Medium Priority (Business Logic)

Models (14 missing) - Core data validation and relationships
Enums (3 missing) - Value objects and constants
Notifications (2 missing) - Email delivery system

# Lower Priority (Validation Layer)

Form Requests (121 missing) - Input validation
Controllers (5 missing) - Already covered indirectly through feature tests

# Total Missing Tests: 146

Controllers: 5
Jobs: 1
Models: 14
Form Requests: 121
Notifications: 2
Enums: 3

Note: Many controllers already have feature tests that cover their functionality end-to-end. The Form Requests would benefit from dedicated unit tests to ensure validation rules work correctly in isolation.
