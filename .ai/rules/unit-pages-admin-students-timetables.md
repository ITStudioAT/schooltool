---
paths:
  - '{app/Services/StudentsTimetables/StudentTimetableV3StudentInformationService.php,resources/js/pages/admin/studentsTimetables/timetableV3/**,tests/Feature/StudentsTimetablesModuleTest.php,tests/ui/unit/pages/admin/studentsTimetables/**}'
---

# Unit Pages Admin Students Timetables

## Page 3B separates student and main modules
On manual timetable page 3B with a selected student, the read-only catalog defaults to Studierenden Module (the five student-connected status groups). The adjacent Hauptmodule card switches to main_module_selection_groups: all selectable modules for the same study program without student progression or eligibility filtering. Catalog switching must not change or persist automatic module/course selection. Without a student, show the single Hauptmodule catalog.
