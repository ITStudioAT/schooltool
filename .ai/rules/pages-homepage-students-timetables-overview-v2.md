---
paths:
  - '{app/Services/StudentsTimetables/StudentTimetablesStudentOverviewService.php,resources/js/pages/homepage/studentsTimetables/overviewV2/**}'
---

# Pages Homepage Students Timetables Overview V2

## Preserve an explicitly cleared student choice
A selected Studienauswahl chip must toggle off on a second click. Save that as an explicit null private override and distinguish it from a missing override key; missing falls back to imported/recognized defaults, while explicit null remains unselected until the student resets all overrides.
