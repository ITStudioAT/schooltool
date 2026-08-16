---
paths:
  - '{app/Http/Requests/Admin/UpdateStudentTimetableV3StateRequest.php,resources/js/pages/admin/studentsTimetables/timetableV3/**,tests/Feature/StudentsTimetablesModuleTest.php}'
---

# Timetable V3 Feature

## Allow null student code without a student
V3 state saves in without_student mode send context.student_code as null. Keep that field nullable while still requiring a non-empty code for with_student and prohibiting a non-empty code for without_student.
