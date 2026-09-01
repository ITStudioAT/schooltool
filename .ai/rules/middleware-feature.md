---
paths:
  - '{app/Http/Middleware/ToolLicensed.php,tests/Feature/StudentsTimetablesModuleTest.php}'
---

# Middleware Feature

## Use admin visibility for restricted timetable previews
A server-marked Lab404 student-timetable impersonation may use StudentsTimetables admin module visibility for /api/homepage/students-timetables/* because the launcher is an admin preview. Require the active impersonation, restricted session marker, exact timetable API path, and studentstimetables_user role; continue enforcing the school licence and keep normal students subject to user visibility.
