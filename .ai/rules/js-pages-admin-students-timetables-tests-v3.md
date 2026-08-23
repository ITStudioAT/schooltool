---
paths:
  - '{app/Services/StudentsTimetables/StudentTimetableV3StudentInformationService.php,app/Services/StudentsTimetables/StudentTimetablesStudentOverviewService.php,resources/js/pages/admin/studentsTimetables/testsV3/**}'
---

# Js Pages Admin Students Timetables Tests V3

## Keep Tests V3 batch payload lean
Batch up to 100 students per request. Preserve canonical module group membership, counts, codes, and names, but skip timetable-course enrichment and the main-module catalogue because Tests V3 compares only module groups.
