---
paths:
  - '{app/Http/Middleware/RestrictStudentsTimetablesImpersonation.php,app/Http/Controllers/Admin/**,routes/{api,web}.php,resources/routes/homepage.js,resources/js/stores/homepage/HomepageStore.js}'
---

# Stores Homepage

## Keep timetable-card impersonation timetable-only
The Timetable V3 student-card switch must set the students_timetables.restricted_impersonation session marker. While that Lab404 impersonation is active, redirect generic /student routes and reject /api/homepage/student/*; expose the scope in impersonation status so the homepage router also redirects client-side. Clear the marker when ordinary global impersonation starts and whenever impersonation stops; normal student sessions and global super-admin impersonation remain unrestricted.
