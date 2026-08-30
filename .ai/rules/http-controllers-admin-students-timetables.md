---
paths:
  - app/Http/Controllers/Admin/StudentsTimetables/TeacherAccountController.php
---

# Http Controllers Admin Students Timetables

## Keep the teacher role mandatory for roster entries
Persist registered roster membership with users.students_timetables_teacher_listed so a person remains visible when no TT role is selected. Every roster entry is a teacher by definition: imported source rows display as active teachers, registered imported users are assigned the teacher role, and that role cannot be removed through the roster API or UI. Active status remains toggleable for registered accounts, except that active admin and super_admin accounts cannot be deactivated; studentstimetables_admin and studentstimetables_moderator remain optional and mutually exclusive.

## Sort the teacher roster by person name
Sort the merged imported/registered teacher roster by last name, then first name, then short code. A missing or different short code must not move a person away from their alphabetical name position.
