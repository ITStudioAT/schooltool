---
paths:
  - '{app/Services/StudentsTimetables/StudentTimetableExpectedModulesService.php,resources/js/pages/admin/studentsTimetables/testsV3/**}'
---

# Admin Students Timetables Tests V3

## Calculate Soll-Zusätzliche independently
For Tests V3, calculate expected additional modules independently from selected subject-plan tracks and stored result snapshots. Religion, branch, arts (BE/ME), and language choices are mandatory filters. Per module family, no result permits the first two available modules; the latest failure permits the next one; the latest passed/exempt result permits the next two. Compare this expected set with the V3 additional group and include it in overall OK/FAIL.

## Use progression Soll for all open groups
In Tests V3, Frühere, Aktuelle, and Zusätzliche must all use the independently calculated progression Soll set (no result: first two; latest failed: next one; latest passed/exempt: next two). Split that same set only by the student's current semester: lower semesters are Frühere, equal is Aktuelle, and higher is Zusätzliche. Do not use the legacy through-semester expected_modules set for these three comparisons.
