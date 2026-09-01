---
paths:
  - '{app/Http/Controllers/Admin/StudentsTimetables/TeacherAccountController.php,resources/js/pages/admin/settings/components/StudentsTimetablesTeachers.vue,tests/Feature/TeacherAccountRemoveTest.php}'
---

# Components Feature

## Remove roster membership without deleting registered users
Teacher-roster removal is school-scoped and self-removal is forbidden. Block removal while teacher-list group memberships or assigned teaching courses depend on the entry. Imported Teacher rows are deleted; registered User accounts and active state remain, while only teacher and Students Timetables roles/listed state plus matching Teacher source rows are removed.
