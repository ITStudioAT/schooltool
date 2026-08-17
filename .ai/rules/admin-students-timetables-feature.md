---
paths:
  - 'resources/js/pages/admin/studentsTimetables/timetableV3/**,resources/views/pdfs/students-timetable-overview.blade.php,app/Http/Controllers/Admin/StudentsTimetables/StudentsTimetablesController.php,tests/ui/unit/pages/admin/studentsTimetables/**,tests/Feature/StudentsTimetablesModuleTest.php'
---

# Admin Students Timetables Feature

## Show manual PDF course title above its identifier
On manual timetable PDF page 2, render the uppercase full course title as row 1 and the compact canonical identifier (for example E3-2Q-REIS) as row 2. Keep time in the period column, hide standard 1-wöchig, retain non-standard recurrence as a separate detail, and preserve two-column multi-course cells. This supersedes the older identifier-only cell rule.
