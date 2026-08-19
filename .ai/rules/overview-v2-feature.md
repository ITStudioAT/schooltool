---
paths:
  - '{app/Http/Controllers/Homepage/StudentTimetableV3StateController.php,app/Http/Requests/Homepage/UpdateStudentTimetableV3StateRequest.php,resources/js/pages/homepage/studentsTimetables/overviewV2/**,tests/Feature/StudentsTimetablesStudentLoginTest.php}'
---

# Overview V2 Feature

## Persist student manual drafts per V3 workspace
Student V2 manual adoption persists only committed selected/removed course-group keys through the authenticated homepage V3 state endpoint. Derive student_code and planning_mode server-side and bind automatic-adoption restoration to the exact workspace UUID, generated fingerprint, timetable key, and global index. Revalidate saved keys against the current student catalogs; never persist pending dialog choices or mutate the generated timetable.
