---
paths:
  - '{app/Http/Controllers/Admin/StudentsTimetables/**,app/Services/StudentsTimetables/TimetableImportService.php,resources/js/pages/admin/studentsTimetables/timetable/Timetable.vue,tests/{Feature,Unit,ui}/**}'
---

# Feature Unitui

## Block timetable dates outside the selected schoolyear
Timetable TXT previews compare the first and last valid TT dates inclusively with the authenticated user's personal schoolyear `from`/`until`. Keep mismatches visible in the preview with both ranges, but disable and server-reject confirmation; recheck before processing so no active timetable entries are written.
