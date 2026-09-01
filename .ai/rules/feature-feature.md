---
paths:
  - '{routes/api.php,tests/Feature/StudentsTimetablesModuleTest.php,tests/Feature/StudentsTimetablesStudentLoginTest.php}'
---

# Feature Feature

## Always start sessions for student timetable APIs
Wrap every /api/homepage/students-timetables/* route in StartSession. These endpoints serve cookie-authenticated student sessions and restricted Lab404 impersonation; they must not depend solely on Sanctum stateful-domain detection, which can differ on Cloudways.

## Licence student timetable APIs against the dedicated role
Use tool-licensed:StudentsTimetables,auto,studentstimetables_user on every licensed /api/homepage/students-timetables/* route. Imported accounts also have the generic student role, whose unrelated per-user licence requirement must not block the dedicated timetable area.
