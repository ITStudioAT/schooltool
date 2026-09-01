---
paths:
  - '{app/Http/Controllers/Homepage/StudentsTimetablesStudentController.php,tests/Feature/StudentsTimetablesStudentLoginTest.php}'
---

# Http Controllers Homepage Feature

## Keep timetable mutation overview responses enriched
Personal timetable save/adopt responses replace the frontend overview store. Return the same student_information payload as the overview endpoint, including module_selection_groups and main_module_selection_groups, so selected modules survive Speichern and Neustart.
