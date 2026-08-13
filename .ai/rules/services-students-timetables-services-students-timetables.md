---
paths:
  - '{app/Services/StudentsTimetables/StudentTimetableV3*,app/Services/StudentsTimetables/RobotTimetableBackendSetupService.php}'
---

# Services Students Timetables Services Students Timetables

## Reject incomplete V3 timetable resolution
V3 generation must resolve each selected module to exactly one Robot course and every selected underlying timetable group to that same module before applying time constraints. Any missing, ambiguous, or cross-module resolution returns 422 and persists nothing; never silently calculate with a partial selection. Treat every multi-row Unterricht as indivisible: retain all of its dates/times or exclude the entire option. Bump the V3 algorithm fingerprint whenever resolution semantics change so old results cannot be reused.
