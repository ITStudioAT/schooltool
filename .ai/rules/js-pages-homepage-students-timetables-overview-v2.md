---
paths:
  - '{app/Http/Controllers/Homepage/StudentTimetableV3TimetableController.php,app/Http/Requests/Homepage/UpdateStudentTimetableV3TimetableRequest.php,resources/js/pages/homepage/studentsTimetables/overviewV2/OverviewV2.vue}'
---

# Js Pages Homepage Students Timetables Overview V2

## Keep student V3 calculation context server-derived
Student automatic timetable creation reuses StudentTimetableV3TimetableService through the authenticated homepage endpoint. Accept only workspace, module, and course keys from the browser; derive student_code, planning_mode, and private selection from the authenticated student overview. Keep results user/session scoped, paged at 100, and never call admin endpoints from the student UI.
