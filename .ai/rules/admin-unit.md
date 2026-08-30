---
paths:
  - '{app/Jobs/ImportTeachersListJob.php,app/Http/Controllers/Admin/TeachersListController.php,tests/{Unit/ImportTeachersListJobTest.php,Feature/TeachersListControllerTest.php}}'
---

# Admin Unit

## Synchronize registered teachers without schoolyear scope
Teacher-list imports are school-scoped snapshots, never schoolyear-scoped. Registered users with the teacher role are deactivated unless present in the import, imported registered teachers are reactivated without creating Teacher allowlist rows, and admin/super_admin accounts remain active. Only emails without an existing same-school user belong in the Teacher preregistration allowlist; hide legacy allowlist rows that already have a same-school user.
