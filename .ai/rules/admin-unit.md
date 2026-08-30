---
paths:
  - '{app/Jobs/ImportTeachersListJob.php,app/Http/Controllers/Admin/TeachersListController.php,tests/{Unit/ImportTeachersListJobTest.php,Feature/TeachersListControllerTest.php}}'
---

# Admin Unit

## Synchronize registered teachers without schoolyear scope
Teacher-list imports are school-scoped snapshots, never schoolyear-scoped. Registered users with the teacher role are deactivated unless present in the import, imported registered teachers are reactivated without creating Teacher allowlist rows, and admin/super_admin accounts remain active. Only emails without an existing same-school user belong in the Teacher preregistration allowlist; hide legacy allowlist rows that already have a same-school user.

## Scope teacher-import status to school and user
Persist teacher-list import progress in cache under both the current school ID and importing user ID. Mark it running only after the initial upload request succeeds and finished before broadcasting success or failure, so the authenticated polling endpoint cannot expose another school's or user's import state.

## Sort the imported teacher list by person name
Order the teaching settings teacher list by last name, then first name, then short code. The short code is displayed as secondary information and must not be the primary alphabetical sort key.
