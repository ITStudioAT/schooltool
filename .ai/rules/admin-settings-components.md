---
paths:
  - '{app/Http/Controllers/Admin/StudentsTimetables/TeacherAccountController.php,app/Http/Requests/Admin/StudentsTimetables/UpdateTeacherRosterEntryRequest.php,resources/js/pages/admin/settings/components/StudentsTimetablesTeachers.vue}'
---

# Admin Settings Components

## Edit teacher roster identity within the school
Teacher-roster identity edits cover short code, names, and email for imported Teacher rows and registered User rows. Enforce school scope and email uniqueness across both teachers and users, preserve active state and roles, and keep any source Teacher row merged by the user's prior email synchronized.
