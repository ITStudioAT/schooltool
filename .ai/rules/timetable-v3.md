---
paths:
  - 'app/Services/StudentsTimetables/**,resources/js/pages/admin/studentsTimetables/timetableV3/**'
---

# Timetable V3

## Passed modules retain failed attempts
For V3 study information, a module with both a passing grade (1–4) and failures (N/5) belongs only to the passed group. Render every earlier failed attempt, including repeated identical grades, as additional badges on the same passed-module row. This grouping and attempt preservation must be calculated by the backend; Vue only renders the supplied grade list.
