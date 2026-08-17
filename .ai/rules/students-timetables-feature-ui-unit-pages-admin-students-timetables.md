---
paths:
  - '{resources/views/pdfs/students-timetable-overview.blade.php,resources/js/pages/admin/studentsTimetables/timetableV3/TimetableV3.vue,app/Http/Controllers/Admin/StudentsTimetables/StudentsTimetablesController.php,tests/Feature/StudentsTimetablesModuleTest.php,tests/ui/unit/pages/admin/studentsTimetables/TimetableV3.test.ts}'
---

# Students Timetables Feature Ui Unit Pages Admin Students Timetables

## Append numbered manual PDF appointment summary
After the manual timetable page, append a summary page only when numbered multi-course cells exist. Repeat each !N/N reference per involved course, show the identifier when available plus the course title and every exact date/time, and color only each course's actual overlapping date/time entries red.
