---
paths:
  - 'app/Jobs/ImportTeachersListJob.php,tests/Unit/ImportTeachersListJobTest.php'
---

# Jobs Unit

## Normalize imported teacher emails to lowercase
Treat teacher-list email matching case-insensitively and persist imported teacher emails in lowercase. Re-importing an uppercase address must update the existing user or preregistration record without creating a duplicate.

## Assign teacher role to registered import matches
When a teacher-list import matches an existing same-school user, update their imported name, short code, and email, mark the account active and roster-listed, and assign the teacher role even if it was previously absent. Omitted non-admin teacher users remain inactive.

## Preserve short codes when the import omits the column
Kurz/Kürzel is optional in teacher-list imports. If the whole column is absent, keep an existing Teacher or User short code unchanged and store NULL for a new Teacher. If the column exists, its row value remains authoritative, including an explicitly blank value.
