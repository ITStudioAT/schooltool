---
paths:
  - '{app/Services/AccessScopeService.php,routes/api.php,resources/js/pages/admin/studentsTimetables/StudentsTimetables.vue,tests/**}'
---

# Services Js Pages Admin Students Timetables

## Restrict Tests V3 to timetable admins
Tests V3 is available only to super_admin, admin, and studentstimetables_admin. Keep it hidden and redirect direct UI routes for studentstimetables_moderator, and enforce the dedicated students_timetables_tests_v3_access scope on test execution and summary PDF endpoints so direct API calls return 403.
