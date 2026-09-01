---
paths:
  - '{routes/api.php,tests/Feature/StudentsTimetablesModuleTest.php,tests/Feature/StudentsTimetablesStudentLoginTest.php}'
---

# Feature Feature

## Always start sessions for student timetable APIs
Wrap every /api/homepage/students-timetables/* route in StartSession. These endpoints serve cookie-authenticated student sessions and restricted Lab404 impersonation; they must not depend solely on Sanctum stateful-domain detection, which can differ on Cloudways.
