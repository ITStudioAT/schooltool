---
paths:
  - '{app/Http/Controllers/Admin/StudentsTimetables/StudentsTimetablesController.php,app/Http/Controllers/Homepage/StudentsTimetablesStudentController.php,resources/views/pdfs/students-timetable-overview.blade.php,tests/Feature/StudentsTimetablesModuleTest.php}'
---

# Views Pdfs Feature

## Show the authoritative school name throughout timetable PDFs
Derive school_name server-side from the authenticated user's selected school. Show it on the information cover and as the first item in every timetable PDF header/meta line; do not trust a client-provided school name.
