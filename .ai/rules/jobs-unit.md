---
paths:
  - 'app/Jobs/ImportTeachersListJob.php,tests/Unit/ImportTeachersListJobTest.php'
---

# Jobs Unit

## Normalize imported teacher emails to lowercase
Treat teacher-list email matching case-insensitively and persist imported teacher emails in lowercase. Re-importing an uppercase address must update the existing user or preregistration record without creating a duplicate.
