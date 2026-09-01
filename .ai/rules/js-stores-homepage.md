---
paths:
  - '{app/Http/Middleware/RestrictStudentsTimetablesImpersonation.php,app/Http/Controllers/Admin/{ImpersonationController.php,StudentsTimetables/StudentsTimetablesController.php},resources/js/pages/{admin/studentsTimetables/timetableV3/TimetableV3.vue,homepage/App.vue},resources/js/stores/homepage/HomepageStore.js}'
---

# Js Stores Homepage

## Return timetable impersonation to its exact origin
When Timetable V3 opens the scoped student view, send and store the current relative Timetable V3 URL including query/hash. Accept only internal /admin/students-timetables/timetable-v3 paths and fall back to its overview for missing or unsafe targets. The stop response supplies this URL and the homepage shell must navigate to it; ordinary impersonation still returns to /admin.
