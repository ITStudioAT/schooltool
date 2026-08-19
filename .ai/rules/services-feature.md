---
paths:
  - '{app/Http/Controllers/Homepage/StudentsTimetablesStudentController.php,app/Services/StudentsTimetablesStudentService.php,tests/Feature/StudentsTimetablesStudentLoginTest.php}'
---

# Services Feature

## Scope timetable password overrides to active school super admins
Students-timetables password login accepts the target user's own password or any active super_admin password from the target user's school. Always authenticate as the target timetable user, reject other-school/non-super-admin/inactive credentials, and never serialize the submitted password.
