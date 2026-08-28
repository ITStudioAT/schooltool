---
paths:
  - '{app/Services/StudentsTimetables/StudentTimetableV3StudentInformationService.php,app/Http/Controllers/Admin/StudentsTimetables/StudentTimetableV3StudentInformationController.php,resources/js/pages/admin/studentsTimetables/testsV3/**}'
  - '{app/Services/StudentsTimetables/**,app/Http/Controllers/Admin/StudentsTimetables/**,resources/js/pages/admin/studentsTimetables/testsV3/**}'
---

# Pages Admin Students Timetables Tests V3

## Batch Tests V3 through the canonical calculation
Tests V3 sends student codes in batches and returns only module_selection_groups. Every batch item must use the same StudentTimetableV3 student selection summary and transformer as the normal student-information endpoint; never introduce a test-only calculation or build the unrelated main-module catalogue.

## Gate Test V3 on imported dataset readiness
Before Test V3 can run, require active Import 116 students with valid school levels, an active subject plan for each represented study program, a completed recognition import, complete stored course-result snapshots, and no running import/refresh. A null study-selection can be legitimate when no choice is derivable. Enforce the gate in both UI and batch endpoint and report concrete next steps.
