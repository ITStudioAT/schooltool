---
paths:
  - '{app/Services/StudentsTimetables/StudentTimetableV3StudentInformationService.php,app/Services/StudentsTimetables/StudentTimetablesStudentOverviewService.php,resources/js/pages/admin/studentsTimetables/testsV3/**}'
---

# Js Pages Admin Students Timetables Tests V3

## Keep Tests V3 batch payload lean
Batch up to 100 students per request. Preserve canonical module group membership, counts, codes, and names, but skip timetable-course enrichment and the main-module catalogue because Tests V3 compares only module groups.

## Preload Tests V3 batches at endpoint capacity
Tests V3 sends up to 100 student codes per sequential request. Before canonical per-student summaries, bulk-prime Import116 students and completed recognition history on the same StudentTimetablesStudentOverviewService instance that performs the calculations; do not preload through a separate history-service instance or rehydrate shared subject rows per student.
