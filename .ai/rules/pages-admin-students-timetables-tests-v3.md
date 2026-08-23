---
paths:
  - '{app/Services/StudentsTimetables/StudentTimetableV3StudentInformationService.php,app/Http/Controllers/Admin/StudentsTimetables/StudentTimetableV3StudentInformationController.php,resources/js/pages/admin/studentsTimetables/testsV3/**}'
---

# Pages Admin Students Timetables Tests V3

## Batch Tests V3 through the canonical calculation
Tests V3 sends student codes in batches and returns only module_selection_groups. Every batch item must use the same StudentTimetableV3 student selection summary and transformer as the normal student-information endpoint; never introduce a test-only calculation or build the unrelated main-module catalogue.
