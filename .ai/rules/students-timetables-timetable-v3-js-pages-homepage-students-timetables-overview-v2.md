---
paths:
  - '{app/Services/StudentsTimetables/StudentTimetableV3StudentInformationService.php,resources/js/pages/admin/studentsTimetables/timetableV3/**,resources/js/pages/homepage/studentsTimetables/overviewV2/**}'
  - '{app/Services/StudentsTimetables/StudentTimetableV3TimetableService.php,resources/js/pages/admin/studentsTimetables/timetableV3/**,resources/js/pages/homepage/studentsTimetables/overviewV2/**}'
---

# Students Timetables Timetable V3 Js Pages Homepage Students Timetables Overview V2

## Keep Alle Module independent of student status
The main_module_selection_groups catalog must include every module from the current study-program catalog plus every module linked to the selected student. Completion, exemption, failure, progression, and selection eligibility may affect Studierenden Module or badges, but must never remove a module from Alle Module. Deduplicate the combined catalog by normalized concrete module code.

## Limit automatic planning by combinations instead of modules or hours
Supersedes the 10-module and 30-hour limits. Automatic selection displays selected modules, logical Unterrichte, and theoretical combinations in that order; hours stay separate. Count grouped Unterricht offers once (all underlying keys selected), and define theory as one selected Unterricht per module before time/conflict checks. Drafts may exceed 100,000 combinations; generation must reject values above 100,000 in UI and backend using overflow-safe multiplication without enumeration. Exactly 100,000 is allowed. Preserve the legacy Robot default cap of 200,000.
