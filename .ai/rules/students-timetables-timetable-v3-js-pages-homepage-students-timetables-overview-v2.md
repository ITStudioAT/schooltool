---
paths:
  - '{app/Services/StudentsTimetables/StudentTimetableV3StudentInformationService.php,resources/js/pages/admin/studentsTimetables/timetableV3/**,resources/js/pages/homepage/studentsTimetables/overviewV2/**}'
---

# Students Timetables Timetable V3 Js Pages Homepage Students Timetables Overview V2

## Keep Alle Module independent of student status
The main_module_selection_groups catalog must include every module from the current study-program catalog plus every module linked to the selected student. Completion, exemption, failure, progression, and selection eligibility may affect Studierenden Module or badges, but must never remove a module from Alle Module. Deduplicate the combined catalog by normalized concrete module code.
