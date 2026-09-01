---
paths:
  - '{resources/js/pages/admin/studentsTimetables/timetableV3/TimetableV3.vue,tests/ui/unit/pages/admin/studentsTimetables/TimetableV3.test.ts}'
---

# Students Timetables Timetable V3 Ui Unit Pages Admin Students Timetables

## Restore the selected V3 student from the route on F5
Timetable V3 route query planning_mode=with_student plus student_code is the reload fallback when the workspace draft is missing, mismatched, or unavailable. Rehydrate the complete selected-student card from the scoped robot-student list and persist it back into the workspace; never clear a URL-selected student merely because state loading failed.
